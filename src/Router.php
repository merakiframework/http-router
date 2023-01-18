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

final class Router
{
	/**
	 * @var string[]
	 */
	private array $supportedMethods = ['get', 'head', 'post', 'put', 'delete', 'connect', 'options', 'trace', 'patch'];

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
	private string $currentNamespaceSegment = '';
	private string $previousNamespaceSegment = '';

	private string $previouslyMatchedUrlSegment = '';
	private string $urlSegmentToMatch = '';

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
		$this->currentNamespaceSegment = '';
		$this->previousNamespaceSegment = '';

		while (!$this->segments->isEmpty()) {
			$this->urlSegmentToMatch = $this->segments->pop() ?: '';
			$hasNextSegment = $this->segments->hasNext();
			$nsSegment = $this->getNamespaceSegmentFromUrlSegment($this->urlSegmentToMatch);
			$this->previousNamespaceSegment = $this->currentNamespaceSegment;

			$className = $this->translator->translate(
				$this->method,
				$this->previouslyMatchedUrlSegment,
				$this->urlSegmentToMatch,
				$hasNextSegment
			);

			$this->ns .= $nsSegment;
			$this->requestHandler = $this->ns . '\\' . $className;
			switch (true) {
				case class_exists($this->requestHandler):
					$this->previouslyMatchedUrlSegment = $this->urlSegmentToMatch;
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

		if (!empty($this->matches) && $this->segments->isEmpty()) {
			return $this->found();
		}

		if (!empty($this->allowedMethods)) {
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

		foreach ($this->supportedMethods as $method) {
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

		for ($i = $requiredParamIndexStart, $l = count($all); $i < $l; $i++) {
			$param = $all[$i];
			$segmentToMatchParam = $this->segments->pop();

			// all required params have been matched to segments
			if (!$segmentToMatchParam) {
				break;
			}

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

	private function found(): Result
	{
		return Result::found(
			$this->originalMethod,
			(string)$this->requestTarget,
			array_shift($this->matches),
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

	private function notFound(): Result
	{
		return Result::notFound(
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
