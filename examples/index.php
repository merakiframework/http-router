<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Http\Router;
use Meraki\Http\Router\Config;
use Laminas\Diactoros\ServerRequestFactory;
use Narrowspark\HttpEmitter\SapiEmitter;

$config = Config::create('Project\\Http\\')
	->excludePluralWords('music', 'registered-businesses', 'archives');
$router = new Router($config);
$request = ServerRequestFactory::fromGlobals();

$result = $router->route($request->getMethod(), $request->getRequestTarget());

switch ($result->status) {
	case 200:
		$route = $result->route;
		$class = new $route->requestHandler;
		$response = call_user_func_array([$class, $route->invokeMethod], $route->arguments);
		break;

	case 400:
		// bad request
		break;

	case 404:
		// not found
		break;

	case 405:
		// method not allowed
		break;

	default:
		// 500 internal server error
		break;
}

(new SapiEmitter())->emit($response);
