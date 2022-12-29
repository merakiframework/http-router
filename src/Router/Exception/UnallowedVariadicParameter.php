<?php
declare(strict_types=1);

namespace Meraki\Http\Router\Exception;

use Meraki\Http\Route;
use InvalidArgumentException;

final class UnallowedVariadicParameter extends InvalidArgumentException
{
	public function __construct(Route $pRoute, Route $cRoute)
	{
		parent::__construct("parent route '{$pRoute}' cannot contain variadic parameters");
	}
}
