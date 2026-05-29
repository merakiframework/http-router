<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

/**
 * The outcome of a Caster attempt. Returned (not thrown) so the caller decides
 * how to map each case:
 *   - Successful      -> a value was produced
 *   - CannotCast      -> a segment was present but invalid for the type (-> 422)
 *   - IncompleteValue -> ran out of segments while a required part was unfilled (-> 400)
 *
 * @psalm-api
 */
enum CastStatus
{
	case Successful;
	case CannotCast;
	case IncompleteValue;
}
