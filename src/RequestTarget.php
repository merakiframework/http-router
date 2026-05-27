<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Segments;

final class RequestTarget
{
	private string $path;

	/**
	 * @psalm-mutation-free
	 */
	public function __construct(string $path)
	{
		$path = strtolower($path);

		// Strip trailing slashes (except on the root path) so /archives/ behaves
		// identically to /archives. Without this, the empty trailing segment
		// produces a spurious lookup for the rootPathSubNamespace (e.g. \Home).
		if (strlen($path) > 1) {
			$path = rtrim($path, '/');
		}

		$this->path = $path;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function getSegments(): Segments
	{
		$segments = explode('/', $this->path);

		// remove first element which is always empty
		array_shift($segments);

		return new Segments($segments);
	}

	public function __toString(): string
	{
		return $this->path;
	}
}
