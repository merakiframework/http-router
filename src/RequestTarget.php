<?php
declare(strict_types=1);

namespace Meraki\Http;

use Countable;

final class RequestTarget implements Countable
{
	/**
	 * @var list<string>
	 */
	private array $segments;

	public function __construct(string $path)
	{
		$path = explode('/', $path);

		// remove first element which is always empty
		array_shift($path);

		$this->segments = $path;
	}

	public static function getSegments(string $path): self
	{
		return new self($path);
	}

	public function hasNext(): bool
	{
		return isset($this->segments[0]);
	}

	public function peek(): ?string
	{
		if ($this->hasNext()) {
			return $this->segments[0];
		}

		return null;
	}

	public function valid(): bool
	{
		return isset($this->segments[0]);
	}

	public function current(): ?string
	{
		if ($this->valid()) {
			return $this->segments[0];
		}

		return null;
	}

	public function pop(): ?string
	{
		return array_shift($this->segments);
	}

	public function push(string $segment): void
	{
		array_unshift($this->segments, $segment);
	}

	public function count(): int
	{
		return count($this->segments);
	}

	public function isEmpty(): bool
	{
		return $this->count() === 0;
	}
}
