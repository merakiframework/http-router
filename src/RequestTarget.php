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
		$this->path = strtolower($path);
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
