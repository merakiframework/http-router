<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

/**
 * Marker interface implemented by every exception this library throws — so
 * application code can `catch (Meraki\Http\Router\Exception $e)` to cover
 * router-originated errors uniformly.
 *
 * @psalm-api
 * @psalm-mutable
 */
interface Exception
{
}
