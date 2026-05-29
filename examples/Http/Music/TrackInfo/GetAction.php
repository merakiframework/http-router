<?php
declare(strict_types=1);

namespace Project\Http\Music\TrackInfo;

use Laminas\Diactoros\Response\TextResponse;

/**
 * DEMONSTRATES the ambiguity of mixing static (Action) and RESTful routing.
 *
 * This is a STATIC route: a GetAction binds only the segments that FOLLOW its
 * own namespace. Everything up to and including `.../track-info` is treated as
 * a literal path — as if the file existed there — and dynamic/RESTful matching
 * is bypassed entirely. So although the signature ($artist, $album, $trackName)
 * reads like /music/{artist}/{album}/track-info/{trackName}, that URL does NOT
 * resolve: the leading {artist}/{album} are never inherited into a static route
 * (only segments after `track-info` are bound).
 *
 * Rule of thumb: reach for a static route only for verbs, or to override a
 * dynamic route with a fixed one — e.g. a vanity item route /users/{username}
 * alongside a static /users/create the application always needs. Otherwise the
 * static/RESTful mix produces surprises like the one above.
 */
final class GetAction
{
	public function __invoke(string $artist, string $album, string $trackName)
	{
		// get track info of song by artist and from a specific album
		return new TextResponse("GET /music/$artist/$album/track-info/$trackName");
	}
}
