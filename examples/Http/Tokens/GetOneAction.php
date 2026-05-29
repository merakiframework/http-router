<?php
declare(strict_types=1);

namespace Project\Http\Tokens;

use Laminas\Diactoros\Response\TextResponse;
use Ramsey\Uuid\UuidInterface;

/**
 * UUID parameter: GET /tokens/{uuid} binds the segment via the default
 * UuidCaster (requires ramsey/uuid to be installed). A UuidInterface param
 * accepts any valid UUID; a version-specific type (e.g. UuidV4) would enforce it.
 */
final class GetOneAction
{
	public function __invoke(UuidInterface $id)
	{
		return new TextResponse('GET /tokens/' . $id->toString());
	}
}
