<?php
declare(strict_types=1);

namespace Project\Http\Music;

use Laminas\Diactoros\Response\TextResponse;

final class GetAllAction
{
	public function __invoke(string $artist = null, string $album = null)
	{
		// all songs belonging to artist and in album
		if ($album) {
			return new TextResponse("GET /music/$artist/$album");
		}

		// all songs belonging to artist
		if ($artist) {
			return new TextResponse("GET /music/$artist");
		}

		// all songs
		return new TextResponse('GET /music');
	}
}
