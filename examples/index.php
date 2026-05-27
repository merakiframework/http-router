<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Meraki\Http\Router;
use Meraki\Http\Router\Config;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

$config = Config::create('Project\\Http\\')
	->excludePluralWords('music', 'registered-businesses', 'archives');
$router = new Router($config);
$request = ServerRequestFactory::fromGlobals();

$result = $router->route($request->getMethod(), $request->getRequestTarget());

/**
 * TESTING:
 *
 * $config->isNotResource('archives') will exclude 'archives' from being treated as a resource, so it won't be pluralized and will be treated as a single resource.
 * GET /archives				-> Archives\GetAllAction::__invoke()
 * GET /archives/2024			-> Archives\GetAllAction::__invoke(2024)
 * GET /archives/2024/06		-> Archives\GetAllAction::__invoke(2024, 6)
 * GET /archives/2024/06/17		-> Archives\GetAllAction::__invoke(2024, 6, 17)
 *
 * contact is singular so is treated as a 'verb' resource, so it won't be pluralized and will be treated as a single resource.
 * GET /contact 				-> Contact\GetAction::__invoke(null)			(nullable parameter means person is optional)
 * GET /contact/{person}		-> Contact\GetAction::__invoke($person)
 * GET /contact/{person}/email	-> Contact\Email\GetAction::__invoke($person)	(contact person by email)
 *
 * contacts is plural so is treated as a 'collection' resource, so it will be pluralized and will be treated as a collection resource.
 * GET /contacts				-> Contacts\GetAllAction::__invoke()
 *
 * default route is a special case where the request target is empty or just '/', so it will be treated as a single resource and will be routed to the default action.
 * this behvaiour cannot be changed and is not affected by the pluralization rules.
 * GET /						-> Default\GetAction::__invoke()
 *
 * music is plural and is considered a resource
 * GET /music					-> Music\GetAllAction::__invoke()
 * GET /music/{artist}			-> Music\GetOneAction::__invoke($artist)
 * GET /music/{artist}/{album}	-> Music\GetOneAction::__invoke($artist, $album)
 * GET /music/{artist}/{album}/track-info/{trackName}	-> Music\TrackInfo\GetAction::__invoke($artist, $album, $trackName)	this specific rout 'extends' the parent resource and is part of restful routing, so it will be treated as a nested resource and will be routed to the appropriate action based on the request target.
 *
 * this is a resource but there is one specific route that is not a resource and does not 'extend' the parent resource /persons/schema
 * $config->withInflectionRule('person', 'persons')  normally would pluralize 'person' to 'people'.
 * GET /persons				-> Persons\GetAllAction::__invoke()
 * GET /persons/schema		-> Persons\Schema\GetAction::__invoke()	// this is a specific route that does not 'extend' the parent resource and is not part of restful routing
 * GET /persons/{person}	-> Persons\GetOneAction::__invoke($person)
 *
 * GET /ping					-> Ping\GetAction::__invoke()
 *
 * GET /states					-> States\GetAllAction::__invoke()
 * GET /states/{state}			-> States\GetOneAction::__invoke($state)
 * GET /states/{state}/suburbs	-> States\Suburbs\GetAllAction::__invoke($state)	// this is a specific route that 'extends' the parent resource and is part of restful routing
 * GET /states/{state}/suburbs/{suburb}	-> States\Suburbs\GetOneAction::__invoke($state, $suburb)	// this is a specific route that 'extends' the parent resource and is part of restful routing
 * GET /states/{state}/suburbs/{suburb}/registered-businesses	-> States\Suburbs\RegisteredBusinesses\GetAllAction::__invoke($state, $suburb)	// this is a specific route that 'extends' the parent resource and is part of restful routing
 * GET /states/{state}/suburbs/{suburb}/registered-businesses{/*}	-> States\Suburbs\RegisteredBusinesses\GetAllAction::__invoke($state, $suburb, ...$offersTheseServices) // this is a specific route that 'extends' the parent resource and is part of restful routing, the splat operator allows for an unlimited number of parameters to be passed to the action, so it will find registered businesses in the suburb that offer any of the services specified in the request target, for example: GET /states/qld/suburbs/emerald/registered-businesses/cleaning/pest-control will find all registered businesses in the suburb of emerald in the state of qld that offer either cleaning or pest control services.
 *
 * GET /users					-> Users\GetAllAction::__invoke()
 * GET /users/{user}			-> Users\GetOneAction::__invoke($user)
 * GET /users/{user}/profile	-> Users\Profile\GetAction::__invoke($user)	// this is a specific route that 'extends' the parent resource and is singular
 * GET /users/{user}/likes		-> Users\Likes\GetAllAction::__invoke($user)	// this is a specific route that 'extends' the parent resource and is plural
 */

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

if ($response === null) {
	var_dump($result);die();
}

(new SapiEmitter())->emit($response);
