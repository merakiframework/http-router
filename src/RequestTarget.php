<?php
declare(strict_types=1);

namespace Meraki\Http;

/**
 * @internal
 * @psalm-internal Meraki\Http
 */
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
	 * Split the (normalised) path into its ordered segments. A trailing slash —
	 * including the lone root slash — never yields an empty trailing segment, so
	 * `/` and `''` both return `[]`, consistent with `/a/b/` returning `['a','b']`.
	 *
	 * @psalm-mutation-free
	 * @return list<string>
	 */
	public function getSegments(): array
	{
		// $this->path keeps a lone "/" for the canonical string; strip it here
		// so the root path yields no segments, matching how all other trailing
		// slashes are treated.
		$path = rtrim($this->path, '/');

		$segments = explode('/', $path);

		// drop the leading '' that precedes the first slash; array_shift
		// re-indexes, so the result is already a list
		array_shift($segments);

		return $segments;
	}

	public function __toString(): string
	{
		return $this->path;
	}
}
