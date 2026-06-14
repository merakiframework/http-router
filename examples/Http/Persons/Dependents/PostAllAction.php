<?php
declare(strict_types=1);

namespace Project\Http\Persons\Dependents;

use Laminas\Diactoros\Response\TextResponse;

/**
 * POST /persons/{pid}/dependents — create a dependent for a specific person.
 *
 * Demonstrates the addressing-method fallback: there is NO Persons\PostOneAction
 * (POST /persons/{id} has no natural REST meaning — POST goes to collections
 * to create), yet this route still works because the parent person is
 * addressed via Persons\GetOneAction (the canonical REST addresser) through
 * the default Config::$addressingFallbackMethods = ['get']. The matched
 * GetOneAction is used only for signature inheritance ($id) and is not invoked.
 */
final class PostAllAction
{
	public function __invoke(string $id)
	{
		return new TextResponse("POST /persons/{$id}/dependents");
	}
}
