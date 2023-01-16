<?php

declare(strict_types=1);

namespace Meraki\Http;

use Countable;

final class Segments implements Countable
{
	/**
	 * @param string[] $segments
	 */
	public function __construct(private array $segments)
	{

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
