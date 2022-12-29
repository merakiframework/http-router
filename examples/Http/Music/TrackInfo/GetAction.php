<?php
declare(strict_types=1);

namespace Project\Http\Music\TrackInfo;

use Laminas\Diactoros\Response\TextResponse;

final class GetAction
{
	public function __invoke(string $artist, string $album, string $trackName)
	{
		// get track info of song by artist and from a specific album
		return new TextResponse("GET /music/$artist/$album/track-info/$trackName");
	}
}
