<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

/**
 * Why a routing attempt produced no matched route.
 *
 * - MissingRequiredSegment: a handler exists but the URL is too short for its
 *   required parameters -> 400 Bad Request.
 * - UnprocessableValue: a handler's signature fits by arg count, but a URL
 *   segment can't cast to the parameter's type -> 422 Unprocessable Content.
 * - NoMatch: no handler matches this URL at all -> 404 / 405 / 204 (the router
 *   then inspects other methods to decide which).
 *
 * @internal
 * @psalm-internal Meraki\Http
 */
enum MatchFailure
{
	case MissingRequiredSegment;
	case UnprocessableValue;
	case NoMatch;
}
