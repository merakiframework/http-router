<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Http\Router;
use Meraki\Http\Router\Config;
use Meraki\Http\Router\ValueObjectCaster;
use Meraki\Http\Router\Exception\SignatureMismatch;
use Meraki\Http\Router\Exception\UnallowedVariadicParameter;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

// The route catalogue (what each URL demonstrates) lives in examples/README.md.
// This file is the SAPI entry: serve it with `php -S 127.0.0.1:8000 examples/index.php`.

$config = Config::create('Project\\Http\\')
	->withAdditionalMethods('propfind')      // demonstrates WebDAV-style method registration
	->withCaster(new ValueObjectCaster());   // demonstrates the opt-in VO caster (/posts/...)
$router = new Router($config);
$request = ServerRequestFactory::fromGlobals();

try {
	$result = $router->route($request->getMethod(), $request->getRequestTarget());
} catch (UnallowedVariadicParameter | SignatureMismatch $e) {
	$response = new TextResponse(
		sprintf("500 Misconfigured handler tree (%s): %s\n", $e::class, $e->getMessage()),
		500
	);
	(new SapiEmitter())->emit($response);
	return;
}

$allow = ['Allow' => implode(', ', array_map('strtoupper', $result->allowedMethods ?? []))];

$response = match ($result->status) {
	200 => (function () use ($result) {
		$route = $result->route;
		assert($route !== null);
		$class = $route->requestHandler;
		$handler = new $class();
		return $handler->{$route->invokeMethod}(...$route->arguments);
	})(),
	204 => new TextResponse('', 204, $allow),
	400 => new TextResponse(sprintf("400 Bad Request: %s %s\n", strtoupper($result->method), $result->requestTarget), 400),
	404 => new TextResponse(sprintf("404 Not Found: %s %s\n", strtoupper($result->method), $result->requestTarget), 404),
	405 => new TextResponse(sprintf("405 Method Not Allowed: %s %s\n", strtoupper($result->method), $result->requestTarget), 405, $allow),
	422 => new TextResponse(sprintf("422 Unprocessable Content: %s %s\n", strtoupper($result->method), $result->requestTarget), 422),
	default => new TextResponse(sprintf("Unhandled status %d\n", $result->status), 500),
};

// HEAD must not return a body (router resolves HEAD to its GET handler).
if (strtolower($request->getMethod()) === 'head') {
	$response = $response->withBody(new \Laminas\Diactoros\Stream('php://memory'));
}

(new SapiEmitter())->emit($response);
