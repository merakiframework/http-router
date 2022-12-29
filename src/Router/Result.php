<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Route;

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
