<?php
declare(strict_types=1);

namespace Meraki\Http\Router\Exception;

use Meraki\Http\Router\Exception;
use InvalidArgumentException;

class InvalidArgument extends InvalidArgumentException implements Exception
{
	public static function namespaceValueIsMissing(): self
	{
		return new self('A value was not provided for the namespace.');
	}

	public static function namespaceCannotBeInGlobalScope(): self
	{
		return new self('Namespace cannot be in the global scope.');
	}
}
