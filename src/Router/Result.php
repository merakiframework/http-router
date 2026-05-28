<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Route;

/**
 * @psalm-api
 */
final class Result
{
	/**
	 * @var Route[]|null
	 */
	public ?array $closestMatches = null;

	public ?Route $route = null;
	public ?string $handlerThatMatchesRequest = null;

	/**
	 * @var string[]|null
	 */
	public ?array $allowedMethods = null;

	/**
	 * @psalm-mutation-free
	 */
	private function __construct(
		public int $status,
		public string $method,
		public string $requestTarget
	) {
	}

	/**
	 * @param Route[] $closestMatches
	 */
	public static function found(
		string $method,
		string $requestTarget,
		Route $route,
		array $closestMatches
	): self {
		$self = new self(200, $method, $requestTarget);
		$self->route = $route;
		$self->closestMatches = $closestMatches;
		$self->handlerThatMatchesRequest = null;
		$self->allowedMethods = null;

		return $self;
	}

	/**
	 * @param Route[] $closestMatches
	 */
	public static function badRequest(
		string $method,
		string $requestTarget,
		string $handlerThatMatchesRequest,
		array $closestMatches
	): self {
		$self = new self(400, $method, $requestTarget);
		$self->handlerThatMatchesRequest = $handlerThatMatchesRequest;
		$self->closestMatches = $closestMatches;
		return $self;
	}

	/**
	 * Returned when a URL is structurally well-formed and matches a handler's
	 * arg count, but a segment cannot be cast to the handler parameter's
	 * accepted types. e.g. `/archives/2026/not-a-number` where the month
	 * param is `?int`. The URL is fine but the value is unprocessable.
	 *
	 * @param Route[] $closestMatches
	 */
	public static function unprocessableContent(
		string $method,
		string $requestTarget,
		string $handlerThatMatchesRequest,
		array $closestMatches
	): self {
		$self = new self(422, $method, $requestTarget);
		$self->handlerThatMatchesRequest = $handlerThatMatchesRequest;
		$self->closestMatches = $closestMatches;
		return $self;
	}

	/**
	 * @param Route[] $closestMatches
	 */
	public static function notFound(
		string $method,
		string $requestTarget,
		string $handlerThatMatchesRequest,
		array $closestMatches
	): self {
		$self = new self(404, $method, $requestTarget);
		$self->handlerThatMatchesRequest = $handlerThatMatchesRequest;
		$self->closestMatches = $closestMatches;
		return $self;
	}

	/**
	 * Auto-synthesised response for OPTIONS requests when no OptionsAction
	 * handler is defined. Status 204 (No Content); the list of methods
	 * supported at this URL is provided via $allowedMethods.
	 *
	 * @param string[] $allowedMethods
	 * @param Route[] $closestMatches
	 */
	public static function optionsResponse(
		string $method,
		string $requestTarget,
		array $allowedMethods,
		string $handlerThatMatchesRequest,
		array $closestMatches
	): self {
		$self = new self(204, $method, $requestTarget);
		$self->allowedMethods = $allowedMethods;
		$self->handlerThatMatchesRequest = $handlerThatMatchesRequest;
		$self->closestMatches = $closestMatches;
		return $self;
	}

	/**
	 * @param string[] $allowedMethods
	 * @param Route[] $closestMatches
	 */
	public static function methodNotAllowed(
		string $method,
		string $requestTarget,
		array $allowedMethods,
		string $handlerThatMatchesRequest,
		array $closestMatches
	): self {
		$self = new self(405, $method, $requestTarget);
		$self->allowedMethods = $allowedMethods;
		$self->handlerThatMatchesRequest = $handlerThatMatchesRequest;
		$self->closestMatches = $closestMatches;
		return $self;
	}
}
