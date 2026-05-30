<?php
declare(strict_types=1);

namespace Meraki\Http\Router\Exception;

use Meraki\Http\Route;
use Meraki\Http\Router\Exception;
use InvalidArgumentException;

/**
 * @psalm-api
 */
final class UnallowedVariadicParameter extends InvalidArgumentException implements Exception
{
	/**
	 * @psalm-mutation-free
	 */
	public function __construct(Route $pRoute, Route $cRoute)
	{
		parent::__construct("parent route '{$pRoute}' cannot contain variadic parameters when matched with '{$cRoute}'");
	}
}
