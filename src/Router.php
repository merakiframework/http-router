<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Router\Result;
use Meraki\Http\Router\Config;
use Meraki\Http\Router\Level;
use Meraki\Http\Router\MatchOutcome;
use Meraki\Http\Router\MatchFailure;
use Meraki\Http\Router\PickedAction;
use Meraki\Http\Router\CasterChain;
use Meraki\Http\Router\CastStatus;
use Meraki\Http\Router\Exception\UnallowedVariadicParameter;
use Meraki\Http\Router\Exception\SignatureMismatch;

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
		$method = strtolower($method);
		$target = new RequestTarget($requestTarget);

		// An empty path ("/" or "") targets the root resource. getSegments()
		// returns [] for those, so re-introduce the empty segment the root
		// lookup keys off of (namespaceSegmentFor('') -> rootPathSubNamespace).
		$segments = $target->getSegments() ?: [''];

		// Methods not in the supported list (CONNECT, TRACE, made-up verbs)
		// never match a handler: 405 if the URL has handlers, else 404.
		if (!in_array($method, $this->config->supportedMethods, true)) {
			return $this->respondMethodNotSupported($method, $target, $segments);
		}

		$outcome = $this->tryMatch($segments, $method);

		// HEAD falls back to GET only on a clean no-match (not on 400/422).
		if ($method === 'head' && $outcome->failure === MatchFailure::NoMatch) {
			$outcome = $this->tryMatch($segments, 'get');
		}

		if ($outcome->isMatch()) {
			$matches = $outcome->matches;
			$primary = array_pop($matches);
			assert($primary !== null); // isMatch() guarantees a non-empty chain
			return Result::found($method, (string)$target, $primary, $matches);
		}

		// isMatch() is false here, so the outcome carries a failure reason.
		$failure = $outcome->failure;
		assert($failure !== null);

		return match ($failure) {
			MatchFailure::MissingRequiredSegment =>
				Result::badRequest($method, (string)$target, $outcome->lastHandler, []),
			MatchFailure::UnprocessableValue =>
				Result::unprocessableContent($method, (string)$target, $outcome->lastHandler, []),
			MatchFailure::NoMatch =>
				$this->resolveNoMatch($method, $target, $segments, $outcome->lastHandler),
		};
	}

	/**
	 * Map a clean no-match to the right response: 404 when the URL has no
	 * handlers for any method, an auto-synthesised 204 for OPTIONS, otherwise
	 * 405 listing the methods that are available.
	 *
	 * @param list<string> $segments
	 */
	private function resolveNoMatch(string $method, RequestTarget $target, array $segments, string $lastHandler): Result
	{
		$allowed = $this->discoverAllowedMethods($segments, $method);

		if ($allowed === []) {
			return Result::notFound($method, (string)$target, $lastHandler, []);
		}

		if ($method === 'options') {
			return Result::optionsResponse($method, (string)$target, $allowed, $lastHandler, []);
		}

		return Result::methodNotAllowed($method, (string)$target, $allowed, $lastHandler, []);
	}

	/**
	 * Walk the URL once for a given method, matching a handler at each
	 * non-pass-through level and threading the inherited parameter chain.
	 *
	 * @param list<string> $segments
	 */
	private function tryMatch(array $segments, string $method): MatchOutcome
	{
		$levels = $this->walkSegments($segments, $method);

		$matches = [];
		$inheritedParams = [];
		$inheritedArgs = [];
		$lastHandler = '';

		// Tracks whether the previous level was matched (vs. pass-through).
		// Root counts as matched. Item-type classes (GetOneAction) require
		// $parentMatched to be true at nested levels — otherwise an inherited
		// ID semantic is being faked from local trailing segments.
		$parentMatched = true;

		// Level 0 is the bare config namespace (no handler lives there); start
		// matching from the first real level.
		for ($i = 1; $i < count($levels); $i++) {
			$level = $levels[$i];
			$isTerminal = ($i === count($levels) - 1);

			// Pass-through: a namespace level with no args that isn't the
			// terminal level needs no handler matched. Subsequent levels keep
			// the same inherited params/args.
			if ($level->args === [] && !$isTerminal) {
				$parentMatched = false;
				continue;
			}

			$picked = $this->pickActionClass(
				$level->namespace,
				$method,
				$level->args,
				$inheritedParams,
				array_values($inheritedArgs),
				$parentMatched
			);

			if ($picked->route === null) {
				// No class fits. Classify why, in priority order:
				//   castFailed       -> 422 (value present but un-castable)
				//   incomplete       -> 400 (ran out of segments mid-value)
				//   needs more args  -> 400 (URL too short for required params)
				//   otherwise        -> 404 (no route at this URL)
				$lastHandler = $this->firstExistingCandidate($level->namespace, $method) ?? '';

				if ($picked->castFailed) {
					return MatchOutcome::failed(MatchFailure::UnprocessableValue, $lastHandler);
				}

				if ($picked->incomplete) {
					return MatchOutcome::failed(MatchFailure::MissingRequiredSegment, $lastHandler);
				}

				$totalArgs = count($inheritedArgs) + count($level->args);
				if ($this->anyExistingCandidateNeedsMoreArgs($level->namespace, $method, $totalArgs)) {
					return MatchOutcome::failed(MatchFailure::MissingRequiredSegment, $lastHandler);
				}

				return MatchOutcome::failed(MatchFailure::NoMatch, $lastHandler);
			}

			$matches[] = $picked->route;
			$lastHandler = $picked->route->requestHandler;
			$parentMatched = true;

			// Inherit the params this level consumed so the next level's
			// signature must extend them.
			$inheritedParams = $picked->paramsConsumed;
			$inheritedArgs = $picked->route->arguments;
		}

		if ($matches === []) {
			return MatchOutcome::failed(MatchFailure::NoMatch, $lastHandler);
		}

		return MatchOutcome::matched($matches, $lastHandler);
	}

	/**
	 * Walk URL segments into a list of namespace levels. A segment that extends
	 * the current namespace (an action class exists there) starts a new level;
	 * any other segment is consumed as an argument of the current level. Level 0
	 * is always the bare config namespace.
	 *
	 * @param list<string> $segments
	 * @return non-empty-list<Level>
	 */
	private function walkSegments(array $segments, string $method): array
	{
		$levels = [];
		$currentNs = $this->config->namespace;
		$currentArgs = [];

		foreach ($segments as $segment) {
			$candidate = $currentNs . $this->namespaceSegmentFor($segment);

			if ($this->isNamespaceBoundary($candidate)) {
				$this->guardAgainstVariadicParent($currentNs, $candidate, $method);
				$levels[] = new Level($currentNs, $currentArgs);
				$currentNs = $candidate;
				$currentArgs = [];
			} else {
				$currentArgs[] = $segment;
			}
		}

		$levels[] = new Level($currentNs, $currentArgs);

		return $levels;
	}

	/**
	 * Returns true if an action class exists at a namespace segment for ANY supported method.
	 *
	 * Must be method-agnostic so that a namespace is discoverable via GET is also
	 * navigable when routing POST, DELETE, etc.
	 */
	private function isNamespaceBoundary(string $candidate): bool
	{
		foreach ($this->config->supportedMethods as $m) {
			if ($this->anyActionClassExists($candidate, $m)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Guard against extending past a parent whose handler has a variadic
	 * parameter: the variadic would always absorb the trailing segments, so any
	 * child route is permanently unreachable. That's a configuration error in
	 * the handler tree, so surface it rather than silently routing past it.
	 */
	private function guardAgainstVariadicParent(string $parentNs, string $childNs, string $method): void
	{
		if (!$this->namespaceHasVariadicHandler($parentNs, $method)) {
			return;
		}

		$parentFqcn = $this->firstExistingCandidate($parentNs, $method);
		$childFqcn = $this->firstExistingCandidate($childNs, $method);
		assert($parentFqcn !== null);
		assert($childFqcn !== null);

		throw new UnallowedVariadicParameter(
			$this->makeRoute($parentFqcn),
			$this->makeRoute($childFqcn)
		);
	}

	/**
	 * Pick the action class at $ns that best fits the supplied args and the
	 * inherited param chain.
	 *
	 * Candidates are tried in priority order (Action -> Collection -> Item):
	 * the first whose signature absorbs the bound args (inherited + local)
	 * wins. $parentMatched is false when the immediately-prior namespace level
	 * was a pass-through; Item/Collection types are then excluded, because they
	 * imply an inherited parent context a skipped parent can't provide.
	 *
	 * @param list<string> $localArgs
	 * @param list<RouteParameter> $inheritedParams
	 * @param list<mixed> $inheritedArgs
	 */
	private function pickActionClass(
		string $ns,
		string $method,
		array $localArgs,
		array $inheritedParams,
		array $inheritedArgs,
		bool $parentMatched
	): PickedAction {
		// Candidate order: Action -> Collection -> Item. Try the least-implied
		// semantics first; the first whose signature actually fits the bound
		// args (inherited + local) wins. This lets `/persons/schema` map to a
		// GetAction (0 args) even when Persons\Schema\GetOneAction would also
		// happen to exist with 0 params.
		$candidates = $this->candidateActions($method);

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

		// Tracks whether a candidate started consuming segments for a value but
		// ran out before a required (constructor) parameter was filled. If true
		// after all candidates fail -> 400 Bad Request (missing required param).
		$incomplete = false;

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

			$route = $this->makeRoute($fqcn);

			// An Action (GetAction) is a standalone route: it binds ONLY the
			// segments that follow its own namespace, never a parent's inherited
			// args. So `/users/1/profile` won't feed `1` into Users\Profile\GetAction.
			// Only the RESTful types (GetAll/GetOne) inherit the parent chain.
			$childInherits = $type !== RouteType::Action;
			$fit = $this->fitCandidate(
				$route->parameters,
				$childInherits ? $inheritedParams : [],
				$childInherits ? $inheritedArgs : [],
				$localArgs
			);

			if ($fit['incomplete']) {
				$incomplete = true;
				continue;
			}

			if ($fit['castFailed']) {
				$castFailure = true;
				continue;
			}

			if ($fit['args'] === null) {
				continue; // signature doesn't fit this URL structurally
			}

			return PickedAction::matched(
				$route->withArguments(...$fit['args'])->withType($type),
				$fit['params']
			);
		}

		// No candidate matched. If we excluded a real Item/Collection class
		// because the parent wasn't addressed, the handler exists but is
		// unreachable — that's a configuration error.
		if ($skippedDueToBrokenChain !== null) {
			throw SignatureMismatch::nestedRestfulRouteRequiresAddressedParent(
				$this->makeRoute($skippedDueToBrokenChain)
			);
		}

		return PickedAction::noMatch($castFailure, $incomplete);
	}

	/**
	 * Try to bind the inherited + local args to a candidate's signature.
	 *
	 * The candidate's parameters must begin with the inherited param chain
	 * (by position); the remaining segments are consumed left-to-right via the
	 * caster chain — a single parameter may consume more than one segment (a
	 * value object pulls one per constructor parameter). Overflow goes to a
	 * variadic. Returns the bound argument list on success, plus the full
	 * parameter list the next level should inherit.
	 *
	 * @param list<RouteParameter> $inheritedParams
	 * @param list<mixed> $inheritedArgs
	 * @param list<string> $localArgs
	 * @return array{args: list<mixed>|null, castFailed: bool, incomplete: bool, params: list<RouteParameter>}
	 *         args === null  -> signature doesn't fit structurally
	 *         castFailed     -> a value was present but couldn't cast (-> 422)
	 *         incomplete     -> ran out of segments mid-value (-> 400)
	 *         params         -> required+optional params (for inheritance on match)
	 * @psalm-mutation-free
	 */
	private function fitCandidate(
		RouteParameters $params,
		array $inheritedParams,
		array $inheritedArgs,
		array $localArgs
	): array {
		$allParams = array_values(array_merge($params->required, $params->optional));

		if (count($inheritedParams) > count($allParams)) {
			return ['args' => null, 'castFailed' => false, 'incomplete' => false, 'params' => $allParams];
		}

		// Params beyond the inherited prefix are filled by the local segments.
		$localParams = array_slice($allParams, count($inheritedParams));
		$boundArgs = $inheritedArgs;
		$chain = new CasterChain($this->config->casters);
		$offset = 0;

		foreach ($localParams as $param) {
			$available = array_slice($localArgs, $offset);

			if ($available === []) {
				// No segments left for this param: optional ones take defaults,
				// a required one means the signature doesn't fit.
				$fits = !in_array($param, $params->required, true);
				return $fits
					? ['args' => $boundArgs, 'castFailed' => false, 'incomplete' => false, 'params' => $allParams]
					: ['args' => null, 'castFailed' => false, 'incomplete' => false, 'params' => $allParams];
			}

			$result = $chain->cast($available, ...$param->types);

			if ($result->status === CastStatus::IncompleteValue) {
				return ['args' => null, 'castFailed' => false, 'incomplete' => true, 'params' => $allParams];
			}

			if ($result->status === CastStatus::CannotCast) {
				return ['args' => null, 'castFailed' => true, 'incomplete' => false, 'params' => $allParams];
			}

			/** @psalm-suppress MixedAssignment */
			$boundArgs[] = $result->value;
			$offset += $result->consumed;
		}

		// Leftover segments must be absorbed by a variadic (which may itself
		// consume them in chunks), or this class doesn't fit.
		$leftover = array_slice($localArgs, $offset);

		if ($leftover !== []) {
			if ($params->variadic === null) {
				return ['args' => null, 'castFailed' => false, 'incomplete' => false, 'params' => $allParams];
			}

			$variadic = $params->variadic;

			while ($leftover !== []) {
				$result = $chain->cast($leftover, ...$variadic->types);

				if ($result->status === CastStatus::IncompleteValue) {
					return ['args' => null, 'castFailed' => false, 'incomplete' => true, 'params' => $allParams];
				}

				if ($result->status === CastStatus::CannotCast) {
					return ['args' => null, 'castFailed' => true, 'incomplete' => false, 'params' => $allParams];
				}

				if ($result->consumed < 1) {
					return ['args' => null, 'castFailed' => false, 'incomplete' => false, 'params' => $allParams];
				}

				/** @psalm-suppress MixedAssignment */
				$boundArgs[] = $result->value;
				$leftover = array_slice($leftover, $result->consumed);
			}
		}

		return ['args' => $boundArgs, 'castFailed' => false, 'incomplete' => false, 'params' => $allParams];
	}

	/**
	 * @psalm-mutation-free
	 * @psalm-suppress ArgumentTypeCoercion
	 */
	private function makeRoute(string $fqcn): Route
	{
		return new Route($fqcn, $this->config->invokeMethod);
	}

	/**
	 * Candidate action class names for $method at a namespace, in priority
	 * order (Action -> Collection -> Item), each paired with its RouteType.
	 * Returns the short class names only; callers prepend the namespace.
	 *
	 * @psalm-mutation-free
	 * @return list<array{string, RouteType}>
	 */
	private function candidateActions(string $method): array
	{
		$base = $this->config->prefix . $this->slugToClassName($method);
		$suffix = $this->config->suffix;

		return [
			[$base . $suffix, RouteType::Action],
			[$base . $this->config->pluralIndicator . $suffix, RouteType::Collection],
			[$base . $this->config->singularIndicator . $suffix, RouteType::Item],
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
	 * The fully-qualified class names of candidate action handlers that
	 * actually exist at $ns for $method, in priority order. Yields class-strings
	 * only (no reflection), keeping the hot existence-check path cheap.
	 *
	 * @return iterable<string>
	 */
	private function existingHandlerClasses(string $ns, string $method): iterable
	{
		foreach ($this->candidateActions($method) as [$className]) {
			$fqcn = $ns . '\\' . $className;
			if (class_exists($fqcn)) {
				yield $fqcn;
			}
		}
	}

	private function firstExistingCandidate(string $ns, string $method): ?string
	{
		foreach ($this->existingHandlerClasses($ns, $method) as $fqcn) {
			return $fqcn;
		}
		return null;
	}

	/**
	 * Does the given namespace have any action handler (for the given method)
	 * whose signature declares a variadic parameter? Used to detect the
	 * misconfiguration where a child route lives under a parent whose
	 * variadic would always absorb the URL — meaning the child is unreachable.
	 */
	private function namespaceHasVariadicHandler(string $ns, string $method): bool
	{
		foreach ($this->existingHandlerClasses($ns, $method) as $fqcn) {
			if ($this->makeRoute($fqcn)->parameters->hasVariadic()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * For the current $ns, does any candidate action class require more args
	 * than we have supplied? (Used to distinguish 400 from 404.)
	 */
	private function anyExistingCandidateNeedsMoreArgs(string $ns, string $method, int $totalArgs): bool
	{
		foreach ($this->existingHandlerClasses($ns, $method) as $fqcn) {
			if (count($this->makeRoute($fqcn)->parameters->required) > $totalArgs) {
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
				$outcome = $this->tryMatch($segments, $candidateMethod);
			} catch (UnallowedVariadicParameter | SignatureMismatch) {
				continue;
			}
			if ($outcome->isMatch()) {
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
	 * @psalm-pure
	 */
	private function slugToClassName(string $slug): string
	{
		return str_replace([' ', '_', '-'], '', ucwords($slug, ' _-'));
	}
}
