<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Router\Result;
use Meraki\Http\Router\Config;
use Meraki\Http\Router\StringType;
use Meraki\Http\Router\Exception\UnallowedVariadicParameter;
use Meraki\Http\Router\Exception\SignatureMismatch;
use RuntimeException;

/**
 * Class-driven HTTP router. The action class name encodes the developer's
 * intent — there is no inflection, pluralization, or URL guessing:
 *
 *   - `GetAllAction` => RouteType::Collection  — list a resource
 *   - `GetOneAction` => RouteType::Item        — single item from a collection
 *   - `GetAction`    => RouteType::Action      — verb / static route, no rules
 *
 * The algorithm:
 *
 *   1. Walk the URL segment by segment. For each segment, try to extend the
 *      current namespace (`current_ns + slugToClassName(segment)`). If any action
 *      class exists at the extended namespace, the segment is a namespace
 *      component; otherwise it's an argument for the most recent namespace.
 *
 *   2. Pick the action class for each non-pass-through level by trying the
 *      candidate suffixes (Action -> AllAction -> OneAction) and matching
 *      against the level's argument count + the inherited parameter chain.
 *
 * @psalm-api
 */
final class Router
{
	/** @psalm-readonly */
	public Config $config;

	/**
	 * @psalm-mutation-free
	 */
	public function __construct(string|Config $config)
	{
		if (is_string($config)) {
			$config = Config::create($config);
		}
		$this->config = $config;
	}

	public function route(string $method, string $requestTarget): Result
	{
		$originalMethod = strtolower($method);
		$target = new RequestTarget($requestTarget);
		$segments = $target->getSegments();

		// An empty path targets the root resource (rootPathSubNamespace, e.g.
		// \Home). getSegments() returns [] for "/" and "", so re-introduce the
		// empty segment the root lookup keys off of.
		if ($segments === []) {
			$segments = [''];
		}

		// Method not in supported list (CONNECT, TRACE, made-up methods)
		// always returns 405 (or 404 if the URL has no handlers at all).
		if (!in_array($originalMethod, $this->config->supportedMethods, true)) {
			return $this->respondMethodNotSupported($originalMethod, $target, $segments);
		}

		$matchResult = $this->tryMatch($segments, $originalMethod);

		// HEAD falls back to GET when no HeadAction is defined.
		if ($matchResult['matches'] === null
			&& $originalMethod === 'head'
			&& $matchResult['missingRequired'] === false
			&& $matchResult['castFailure'] === false) {
			$matchResult = $this->tryMatch($segments, 'get');
		}

		if ($matchResult['matches'] !== null && $matchResult['matches'] !== []) {
			$matches = $matchResult['matches'];
			$primary = array_pop($matches);
			return Result::found(
				$originalMethod,
				(string)$target,
				$primary,
				$matches
			);
		}

		if ($matchResult['missingRequired']) {
			return Result::badRequest(
				$originalMethod,
				(string)$target,
				$matchResult['lastHandler'] ?? '',
				[]
			);
		}

		if ($matchResult['castFailure']) {
			return Result::unprocessableContent(
				$originalMethod,
				(string)$target,
				$matchResult['lastHandler'] ?? '',
				[]
			);
		}

		// 404 vs 405 vs 204 (OPTIONS auto-synthesis)
		$allowed = $this->discoverAllowedMethods($segments, $originalMethod);
		if ($allowed === []) {
			return Result::notFound(
				$originalMethod,
				(string)$target,
				$matchResult['lastHandler'] ?? '',
				[]
			);
		}

		if ($originalMethod === 'options') {
			return Result::optionsResponse(
				$originalMethod,
				(string)$target,
				$allowed,
				$matchResult['lastHandler'] ?? '',
				[]
			);
		}

		return Result::methodNotAllowed(
			$originalMethod,
			(string)$target,
			$allowed,
			$matchResult['lastHandler'] ?? '',
			[]
		);
	}

	/**
	 * Top-level matching attempt. Returns either a list of matches (route chain)
	 * or null + diagnostic info indicating why no match was produced.
	 *
	 * @param list<string> $segments
	 * @return array{matches: Route[]|null, missingRequired: bool, castFailure: bool, lastHandler: string|null}
	 */
	private function tryMatch(array $segments, string $method): array
	{
		$levels = $this->walkSegments($segments, $method);

		$matches = [];
		$inheritedParams = [];
		$inheritedArgs = [];
		$lastHandler = null;

		// Tracks whether the previous level was matched (vs. pass-through).
		// Root counts as matched. Item-type classes (GetOneAction) require
		// $parentMatched to be true at nested levels — otherwise an inherited
		// ID semantic is being faked from local trailing segments.
		$parentMatched = true;

		for ($i = 1; $i < count($levels); $i++) {
			$level = $levels[$i];
			$isTerminal = ($i === count($levels) - 1);

			// Pass-through: a namespace level with no args that isn't the
			// terminal level needs no handler matched. Subsequent levels keep
			// the same inherited params/args.
			if ($level['args'] === [] && !$isTerminal) {
				$parentMatched = false;
				continue;
			}

			$candidate = $this->pickActionClass(
				$level['ns'],
				$method,
				$level['args'],
				$inheritedParams,
				array_values($inheritedArgs),
				$parentMatched
			);

			if (!$candidate['matched']) {
				// No class fits. Distinguish three sub-cases:
				//   (a) A signature would have fit by arg count but a segment
				//       couldn't cast -> 422 (URL well-formed, value invalid).
				//   (b) A signature requires more args than the URL provided
				//       -> 400 (missing required parameter, URL too short).
				//   (c) Otherwise -> 404 (no route at this URL).
				$lastHandler = $this->firstExistingCandidate($level['ns'], $method);
				$missingRequired = $this->anyExistingCandidateNeedsMoreArgs(
					$level['ns'],
					$method,
					count($inheritedArgs) + count($level['args'])
				);
				return [
					'matches' => null,
					'missingRequired' => $missingRequired,
					'castFailure' => $candidate['castFailure'],
					'lastHandler' => $lastHandler ?? '',
				];
			}

			$route = $candidate['route'];
			assert($route !== null);
			$matches[] = $route;
			$lastHandler = $route->requestHandler;
			$parentMatched = true;

			// Update inherited state for the next level. We absorb only the
			// params the candidate consumed beyond what it inherited.
			$inheritedParams = $candidate['paramsConsumed'];
			$inheritedArgs = $route->arguments;
		}

		if ($matches === []) {
			return [
				'matches' => null,
				'missingRequired' => false,
				'castFailure' => false,
				'lastHandler' => $lastHandler,
			];
		}

		return [
			'matches' => $matches,
			'missingRequired' => false,
			'castFailure' => false,
			'lastHandler' => $lastHandler,
		];
	}

	/**
	 * Walk URL segments, building a list of (namespace, args) levels.
	 *
	 * @param list<string> $segments
	 * @return non-empty-array<int, array{ns: string, args: list<string>}>
	 */
	private function walkSegments(array $segments, string $method): array
	{
		$levels = [['ns' => $this->config->namespace, 'args' => []]];

		foreach ($segments as $segment) {
			$currentNs = $levels[count($levels) - 1]['ns'];
			$nsSegment = $this->namespaceSegmentFor($segment);
			$candidate = $currentNs . $nsSegment;

			if ($this->anyActionClassExists($candidate, $method)) {
				// About to extend the namespace. If the current (parent)
				// namespace has a handler with variadic parameters, child
				// routes can't ever be reached — the variadic would always
				// absorb trailing segments. This is a configuration error in
				// the user's handler structure; throw to surface it.
				if ($this->namespaceHasVariadicHandler($currentNs, $method)) {
					$parentFqcn = $this->firstExistingCandidate($currentNs, $method);
					$childFqcn = $this->firstExistingCandidate($candidate, $method);
					assert($parentFqcn !== null);
					assert($childFqcn !== null);
					/** @psalm-suppress ArgumentTypeCoercion */
					throw new UnallowedVariadicParameter(
						new Route($parentFqcn, $this->config->invokeMethod),
						new Route($childFqcn, $this->config->invokeMethod)
					);
				}
				$levels[] = ['ns' => $candidate, 'args' => []];
			} else {
				$levels[count($levels) - 1]['args'][] = $segment;
			}
		}

		return $levels;
	}

	/**
	 * Pick the action class at $ns that best fits the supplied args and the
	 * inherited param chain. Returns the constructed Route + the param list it
	 * consumed (for use when computing the next level's inherited params).
	 *
	 * $parentMatched is false when the immediately-prior namespace level was a
	 * pass-through (no args matched). When that's the case, Item types
	 * (GetOneAction) are excluded — an Item route implies it inherits an ID
	 * from a real parent context, so a skipped parent invalidates it.
	 *
	 * Result shape:
	 *   ['matched' => true,  'route' => Route, 'paramsConsumed' => list, 'castFailure' => false]  on match
	 *   ['matched' => false, 'route' => null,  'paramsConsumed' => [],   'castFailure' => true]   when a candidate's
	 *       signature would have matched except a URL segment couldn't cast to the param's accepted types
	 *       (422 Unprocessable Content — the route exists, the value is invalid)
	 *   ['matched' => false, 'route' => null,  'paramsConsumed' => [],   'castFailure' => false]  no candidate fits
	 *       structurally (404, or 400 via the missing-required check in tryMatch)
	 *
	 * @param list<string> $localArgs
	 * @param list<RouteParameter> $inheritedParams
	 * @param list<mixed> $inheritedArgs
	 * @return array{matched: bool, route: ?Route, paramsConsumed: list<RouteParameter>, castFailure: bool}
	 */
	private function pickActionClass(
		string $ns,
		string $method,
		array $localArgs,
		array $inheritedParams,
		array $inheritedArgs,
		bool $parentMatched
	): array {
		$classified = $this->slugToClassName($method);
		$prefix = $this->config->prefix;
		$suffix = $this->config->suffix;

		// Candidate order: Action -> Collection -> Item. Try the least-implied
		// semantics first; the first whose signature actually fits the bound
		// args (inherited + local) wins. This lets `/persons/schema` map to a
		// GetAction (0 args) even when Persons\Schema\GetOneAction would also
		// happen to exist with 0 params.
		$candidates = [
			[$prefix . $classified . $suffix, RouteType::Action],
			[$prefix . $classified . $this->config->pluralIndicator . $suffix, RouteType::Collection],
			[$prefix . $classified . $this->config->singularIndicator . $suffix, RouteType::Item],
		];

		// Tracks an Item/Collection class we excluded because the parent
		// chain was broken (parent level was skipped). If we ultimately fail
		// to find any match at this level, we throw using this — surfacing
		// the misconfig instead of silently 404'ing.
		$skippedDueToBrokenChain = null;

		// Tracks whether any candidate's signature WOULD have matched the
		// URL's arg count, but a segment couldn't cast to the expected type.
		// If true after all candidates fail, the URL is malformed for an
		// existing route -> 422 Unprocessable Content rather than 404.
		$castFailure = false;

		foreach ($candidates as [$className, $type]) {
			$fqcn = $ns . '\\' . $className;

			// Both Item (GetOneAction) and Collection (GetAllAction) are
			// RESTful types that inherit from a parent route's signature. If
			// the immediately-prior level was a pass-through (skipped), the
			// inheritance chain is broken — there's no parent class for the
			// signature to extend. Only Action (GetAction) is exempt; it
			// carries no inherited-context semantics.
			if (!$parentMatched && ($type === RouteType::Item || $type === RouteType::Collection)) {
				if (class_exists($fqcn) && $skippedDueToBrokenChain === null) {
					$skippedDueToBrokenChain = $fqcn;
				}
				continue;
			}

			if (!class_exists($fqcn)) {
				continue;
			}

			/** @psalm-suppress ArgumentTypeCoercion */
			$route = new Route($fqcn, $this->config->invokeMethod);
			$params = $route->parameters;

			// Parent's params must be a prefix of this class's params (in
			// position). Bind inherited args first.
			$allParams = array_values(array_merge($params->required, $params->optional));

			if (count($inheritedParams) > count($allParams)) {
				continue;
			}

			// Local-args region of params: everything after the inherited prefix.
			$localParams = array_slice($allParams, count($inheritedParams));
			$variadic = $params->variadic;

			$boundArgs = $inheritedArgs;
			$remaining = $localArgs;

			$argMatchFailed = false;
			foreach ($localParams as $param) {
				if ($remaining === []) {
					if (in_array($param, $params->required, true)) {
						// Required local param with no arg to fill it.
						$argMatchFailed = true;
					}
					break;
				}
				try {
					/** @psalm-suppress MixedAssignment */
					$boundArgs[] = $this->castArg(array_shift($remaining), $param->types);
				} catch (RuntimeException) {
					$argMatchFailed = true;
					$castFailure = true;
					break;
				}
			}

			if ($argMatchFailed) {
				continue;
			}

			// Any args left over? They go to variadic, or this class doesn't fit.
			if ($remaining !== []) {
				if ($variadic === null) {
					continue;
				}
				foreach ($remaining as $extra) {
					try {
						/** @psalm-suppress MixedAssignment */
						$boundArgs[] = $this->castArg($extra, $variadic->types);
					} catch (RuntimeException) {
						$argMatchFailed = true;
						$castFailure = true;
						break;
					}
				}
				if ($argMatchFailed) {
					continue;
				}
			}

			$route = $route->withArguments(...$boundArgs)->withType($type);

			return [
				'matched' => true,
				'route' => $route,
				'paramsConsumed' => $allParams,
				'castFailure' => false,
			];
		}

		// No candidate matched. If we excluded a real Item/Collection class
		// because the parent wasn't addressed, the handler exists but is
		// unreachable — that's a configuration error.
		if ($skippedDueToBrokenChain !== null) {
			/** @psalm-suppress ArgumentTypeCoercion */
			throw SignatureMismatch::nestedRestfulRouteRequiresAddressedParent(
				new Route($skippedDueToBrokenChain, $this->config->invokeMethod)
			);
		}

		return [
			'matched' => false,
			'route' => null,
			'paramsConsumed' => [],
			'castFailure' => $castFailure,
		];
	}

	/**
	 * For a given segment, return the namespace fragment ("\Foo") that
	 * represents it. Empty segment (root path) maps to rootPathSubNamespace.
	 *
	 * @psalm-mutation-free
	 */
	private function namespaceSegmentFor(string $segment): string
	{
		if ($segment === '') {
			return $this->config->rootPathSubNamespace;
		}
		return '\\' . $this->slugToClassName($segment);
	}

	/**
	 * Does ANY action class exist at this namespace for the given method?
	 * (Tries Action, AllAction, OneAction suffixes.)
	 */
	private function anyActionClassExists(string $ns, string $method): bool
	{
		return $this->firstExistingCandidate($ns, $method) !== null;
	}

	/**
	 * Does the given namespace have any action handler (for the given method)
	 * whose signature declares a variadic parameter? Used to detect the
	 * misconfiguration where a child route lives under a parent whose
	 * variadic would always absorb the URL — meaning the child is unreachable.
	 */
	private function namespaceHasVariadicHandler(string $ns, string $method): bool
	{
		$classified = $this->slugToClassName($method);
		$prefix = $this->config->prefix;
		$suffix = $this->config->suffix;

		foreach ([
			$prefix . $classified . $suffix,
			$prefix . $classified . $this->config->pluralIndicator . $suffix,
			$prefix . $classified . $this->config->singularIndicator . $suffix,
		] as $candidate) {
			$fqcn = $ns . '\\' . $candidate;
			if (!class_exists($fqcn)) {
				continue;
			}
			/** @psalm-suppress ArgumentTypeCoercion */
			$route = new Route($fqcn, $this->config->invokeMethod);
			if ($route->parameters->hasVariadic()) {
				return true;
			}
		}
		return false;
	}

	private function firstExistingCandidate(string $ns, string $method): ?string
	{
		$classified = $this->slugToClassName($method);
		$prefix = $this->config->prefix;
		$suffix = $this->config->suffix;

		foreach ([
			$prefix . $classified . $suffix,
			$prefix . $classified . $this->config->pluralIndicator . $suffix,
			$prefix . $classified . $this->config->singularIndicator . $suffix,
		] as $candidate) {
			$fqcn = $ns . '\\' . $candidate;
			if (class_exists($fqcn)) {
				return $fqcn;
			}
		}
		return null;
	}

	/**
	 * For the current $ns, does any candidate action class require more args
	 * than we have supplied? (Used to distinguish 400 from 404.)
	 */
	private function anyExistingCandidateNeedsMoreArgs(string $ns, string $method, int $totalArgs): bool
	{
		$classified = $this->slugToClassName($method);
		$prefix = $this->config->prefix;
		$suffix = $this->config->suffix;

		foreach ([
			$prefix . $classified . $suffix,
			$prefix . $classified . $this->config->pluralIndicator . $suffix,
			$prefix . $classified . $this->config->singularIndicator . $suffix,
		] as $candidate) {
			$fqcn = $ns . '\\' . $candidate;
			if (!class_exists($fqcn)) {
				continue;
			}
			/** @psalm-suppress ArgumentTypeCoercion */
			$route = new Route($fqcn, $this->config->invokeMethod);
			$requiredCount = count($route->parameters->required);
			if ($requiredCount > $totalArgs) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Discover which methods have a handler available for this URL — used to
	 * decide between 404 and 405, and to populate the Allow header.
	 *
	 * @param list<string> $segments
	 * @return string[]
	 */
	private function discoverAllowedMethods(array $segments, string $currentMethod): array
	{
		$allowed = [];
		foreach ($this->config->supportedMethods as $candidateMethod) {
			if ($candidateMethod === $currentMethod) {
				continue;
			}
			// Discovery probes shouldn't surface configuration errors for
			// methods the caller didn't ask about — swallow misconfig
			// exceptions here so unrelated methods can still be enumerated.
			try {
				$result = $this->tryMatch($segments, $candidateMethod);
			} catch (UnallowedVariadicParameter | SignatureMismatch) {
				continue;
			}
			if ($result['matches'] !== null) {
				$allowed[] = $candidateMethod;
			}
		}

		// HEAD auto-available when GET is.
		if (in_array('get', $allowed, true) && !in_array('head', $allowed, true)) {
			$allowed[] = 'head';
		}

		// OPTIONS auto-available when any other method is.
		if ($allowed !== [] && !in_array('options', $allowed, true)) {
			$allowed[] = 'options';
		}

		return $allowed;
	}

	/**
	 * @param list<string> $segments
	 */
	private function respondMethodNotSupported(string $method, RequestTarget $target, array $segments): Result
	{
		$allowed = $this->discoverAllowedMethods($segments, $method);
		if ($allowed === []) {
			return Result::notFound($method, (string)$target, '', []);
		}
		return Result::methodNotAllowed($method, (string)$target, $allowed, '', []);
	}

	/**
	 * Cast a URL segment to one of the parameter's accepted types. Throws
	 * RuntimeException if no type works — null is NOT used as a "couldn't
	 * cast" sentinel because some params actually accept null.
	 *
	 * @param string[] $types
	 */
	private function castArg(string $value, array $types): mixed
	{
		foreach ($types as $type) {
			if ($type === 'null') {
				continue;
			}
			try {
				/** @psalm-suppress MixedAssignment */
				return StringType::fromString($value)->castTo($type);
			} catch (RuntimeException) {
				continue;
			} catch (\InvalidArgumentException) {
				continue;
			}
		}
		throw new RuntimeException(sprintf(
			"Cannot cast '%s' to any of: %s",
			$value,
			implode(', ', $types)
		));
	}

	/**
	 * @psalm-pure
	 */
	private function slugToClassName(string $slug): string
	{
		return str_replace([' ', '_', '-'], '', ucwords($slug, ' _-'));
	}
}
