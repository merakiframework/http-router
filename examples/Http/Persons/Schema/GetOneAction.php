<?php
declare(strict_types=1);

namespace Project\Http\Persons\Schema;

use Laminas\Diactoros\Response\TextResponse;

final class GetOneAction
{
    public function __invoke(): TextResponse
    {
        return new TextResponse('GET /persons/schema');
    }
}
