<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Router\Result;
use Meraki\Http\RequestTarget;
use Meraki\Http\Segments;
use Meraki\Http\Route;
use Meraki\Http\Router\Config;
use Meraki\Http\Router\Translator;
use Meraki\Http\Router\Exception\SignatureMismatch;
use Meraki\Http\Router\Exception\UnallowedVariadicParameter;
use InvalidArgumentException;
use Meraki\Http\Router\StringType;
use RuntimeException;

/**
 * @psalm-api
 */
final class Router
{
	/**
	 * @psalm-readonly
	 */
	public Config $config;

	private string $method = '';

	/** @psalm-suppress PropertyNotSetInConstructor */
	private RequestTarget $requestTarget;

	/** @psalm-suppress PropertyNotSetInConstructor */
	private Segments $segments;
	private string $ns = '';
	private Translator $translator;

	/**
	 * @var Route[]
	 */
	private array $matches = [];

	private string $requestHandler = '';
	private string $originalMethod = '';

	/**
	 * @var string[]
	 */
	private array $allowedMethods = [];
	private string $previouslyMatchedUrlSegment = '';
	private string $urlSegmentToMatch = '';
	private bool $missingRequiredSegment = false;

	public function __construct(string|Config $config)
	{
		if (is_string($config)) {
			$config = Config::create($config);
		}

		$this->config = $config;
		$this->translator = new Translator($this->config);
	}

	/**
	 * @todo implement 400 bad request for parameters
	 */
	public function route(string $method, string $requestTarget): Result
	{
		$this->method = $this->originalMethod = strtolower($method);
		$this->requestTarget = new RequestTarget($requestTarget);
		$this->segments = $this->requestTarget->getSegments();
		$this->ns = $this->config->namespace;
		$this->matches = [];
		$this->allowedMethods = [];
		$this->missingRequiredSegment = false;

		while (!$this->segments->isEmpty()) {
			$this->urlSegmentToMatch = $this->segments->pop() ?? '';
			$hasNextSegment = $this->segments->hasNext();
			$nsSegment = $this->getNamespaceSegmentFromUrlSegment($this->urlSegmentToMatch);
			$className = $this->translator->translate(
				$this->method,
				$this->previouslyMatchedUrlSegment,
				$this->urlSegmentToMatch,
				$hasNextSegment
			);

			$this->ns .= $nsSegment;
			$this->requestHandler = $this->ns . '\\' . $className;
			switch (true) {
				case in_array($this->method, $this->config->supportedMethods, true)
					&& class_exists($this->requestHandler):
					$this->previouslyMatchedUrlSegment = $this->urlSegmentToMatch;

					if ($hasNextSegment && $this->hasMoreSpecificRoute()) {
						break;
					}

					$this->buildRoute();
					break;

					// match a HEAD request to a 'GET' method request-handler
				case $this->method === 'head':
					$this->method = 'get'; // change method to try
					$this->segments->push($this->urlSegmentToMatch); // push segment back on stack to retry
					$this->ns = str_replace($nsSegment, '', $this->ns); // remove last segment tried
					continue 2;

				case count($this->matches) === 1 && $this->matches[0]->parameters->hasVariadic():
					$this->matches[0] = $this->matchVariadicParameters($this->matches[0]);
					break 2;

				case empty($this->matches):
					$this->findAllowedMethods($className, $hasNextSegment);
					break 2;

				default:
					$this->segments->push($this->urlSegmentToMatch);
					break 2;
			}
		}

		// var_dump($this->segmentMatches);

		if ($this->missingRequiredSegment) {
			return $this->badRequest();
		}

		if (!empty($this->matches) && $this->segments->isEmpty()) {
			return $this->found();
		}

		/** @psalm-suppress TypeDoesNotContainType */
		if ($this->allowedMethods !== []) {
			// Auto-synthesise an OPTIONS response listing the methods supported
			// at this URL (RFC 9110 §9.3.7) when no OptionsAction handler exists.
			if ($this->originalMethod === 'options') {
				return $this->optionsResponse();
			}

			return $this->methodNotAllowed();
		}

		return $this->notFound();
	}

	private function getNamespaceSegmentFromUrlSegment(string $urlSegment): string
	{
		if ($urlSegment === '') {
			return $this->config->rootPathSubNamespace;
		}

		return '\\' . $this->translator->urlSegmentToNamespaceSegment($urlSegment);
	}

	private function findAllowedMethods(string $className, bool $hasNextSegment): void
	{
		$namespace = str_replace($className, '', $this->requestHandler);

		foreach ($this->config->supportedMethods as $method) {
			// no need to check again, we already no it isn't allowed
			if ($method === $this->method) {
				continue;
			}

			$className = $this->translator->translate(
				$method,
				$this->previouslyMatchedUrlSegment,
				$this->urlSegmentToMatch,
				$hasNextSegment
			);

			if (class_exists($namespace . $className)) {
				$this->allowedMethods[] = $method;
			}
		}

		// if a 'GET' request is supported, so too is a 'HEAD' request
		if (in_array('get', $this->allowedMethods) && !in_array('head', $this->allowedMethods)) {
			$this->allowedMethods[] = 'head';
		}

		// OPTIONS is always available wherever any other method is — the
		// router auto-synthesises an OPTIONS response listing allowed methods
		// when no OptionsAction handler is defined.
		if (!empty($this->allowedMethods) && !in_array('options', $this->allowedMethods, true)) {
			$this->allowedMethods[] = 'options';
		}
	}

	private function buildRoute(): void
	{
		/** @psalm-suppress ArgumentTypeCoercion */
		$route = new Route($this->requestHandler, $this->config->invokeMethod);
		$requiredParamIndexStart = 0;

		// parent route exists
		if (count($this->matches) > 0) {
			$parentRoute = array_shift($this->matches);

			if ($parentRoute->parameters->hasVariadic()) {
				throw new UnallowedVariadicParameter($parentRoute, $route);
			}

			// check signatures match...
			/** @var int $index */
			foreach ($parentRoute->parameters->allExceptVariadic() as $index => $parentParam) {
				if (!isset($route->parameters->required[$index])) {
					throw SignatureMismatch::missingRequiredParameter($parentRoute, $route, $parentParam);
				}

				if (!$route->parameters->required[$index]->sameTypesAs($parentParam)) {
					throw SignatureMismatch::incorrectTypes(
						$parentRoute,
						$route,
						$parentParam,
						$route->parameters->required[$index]
					);
				}
			}

			$route = $route->withArguments(...$parentRoute->arguments, ...$route->arguments);
			$requiredParamIndexStart = $parentRoute->parameters->count();
		}

		$all = $route->parameters->allExceptVariadic();

		$requiredCount = count($route->parameters->required);

		for ($i = $requiredParamIndexStart, $l = count($all); $i < $l; $i++) {
			$param = $all[$i];
			$segmentToMatchParam = $this->segments->pop();

			// URL ran out of segments before the route's required params were satisfied.
			// The handler exists but the URL is too short for its signature.
			if ($segmentToMatchParam === null) {
				if ($i < $requiredCount) {
					$this->missingRequiredSegment = true;
					return;
				}
				break;
			}

			$argMatched = false;

			foreach ($param->types as $type) {
				try {
					/** @psalm-suppress MixedAssignment */
					$castedValue = StringType::fromString($segmentToMatchParam)->castTo($type);
					$route = $route->addArgument($castedValue);
					$argMatched = true;
					break; // move on to next argument
				} catch (RuntimeException $e) {
					continue; // try next type
				}
			}

			// URL segment was provided but no parameter type can accept it;
			// the route's signature doesn't match this URL. Push the segment back
			// so the outer loop can fall through to a notFound result.
			if (!$argMatched) {
				$this->segments->push($segmentToMatchParam);
				return;
			}
		}

		$this->matches[] = $route;
	}

	private function matchVariadicParameters(Route $route): Route
	{
		if (!$route->parameters->variadic) {
			return $route;
		}
		$param = $route->parameters->variadic;
		$segmentToMatchParam = $this->urlSegmentToMatch;

		do {
			foreach ($param->types as $type) {
				try {
					/** @psalm-suppress MixedAssignment */
					$castedValue = StringType::fromString($segmentToMatchParam)->castTo($type);
					$route = $route->addArgument($castedValue);
					break; // move on to next argument
				} catch (RuntimeException $e) {
					continue; // try next type
				}
			}
		} while ($segmentToMatchParam = $this->segments->pop());

		return $route;
	}

	private function hasMoreSpecificRoute(): bool
	{
		$nextSegment = $this->segments->peek();

		if ($nextSegment === null) {
			return false;
		}

		// Routes with variadic params rely on buildRoute()'s parent/child detection; don't bypass it.
		/** @psalm-suppress ArgumentTypeCoercion */
		if ((new Route($this->requestHandler, $this->config->invokeMethod))->parameters->hasVariadic()) {
			return false;
		}

		$hasNextNextSegment = count($this->segments) > 1;
		$nextNs = $this->ns . $this->getNamespaceSegmentFromUrlSegment($nextSegment);
		$nextClassName = $this->translator->translate(
			$this->method,
			$this->urlSegmentToMatch,
			$nextSegment,
			$hasNextNextSegment
		);

		return class_exists($nextNs . '\\' . $nextClassName);
	}

	private function found(): Result
	{
		$route = array_shift($this->matches);
		assert($route !== null);

		return Result::found(
			$this->originalMethod,
			(string)$this->requestTarget,
			$route,
			$this->matches
		);
	}

	private function notFound(): Result
	{
		return Result::notFound(
			$this->originalMethod,
			(string)$this->requestTarget,
			$this->requestHandler,
			$this->matches
		);
	}

	private function optionsResponse(): Result
	{
		return Result::optionsResponse(
			$this->originalMethod,
			(string)$this->requestTarget,
			$this->allowedMethods,
			$this->requestHandler,
			$this->matches
		);
	}

	private function badRequest(): Result
	{
		return Result::badRequest(
			$this->originalMethod,
			(string)$this->requestTarget,
			$this->requestHandler,
			$this->matches
		);
	}

	private function methodNotAllowed(): Result
	{
		return Result::methodNotAllowed(
			$this->originalMethod,
			(string)$this->requestTarget,
			$this->allowedMethods,
			$this->requestHandler,
			$this->matches
		);
	}
}
