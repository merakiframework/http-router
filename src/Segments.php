<?php

declare(strict_types=1);

namespace Meraki\Http;

use Countable;

/**
 * @psalm-api
 */
final class Segments implements Countable
{
	/**
	 * @param string[] $segments
	 * @psalm-mutation-free
	 */
	public function __construct(private array $segments)
	{
	}

	/**
	 * @psalm-mutation-free
	 */
	public function hasNext(): bool
	{
		return isset($this->segments[0]);
	}

	/**
	 * @psalm-mutation-free
	 */
	public function peek(): ?string
	{
		if ($this->hasNext()) {
			return $this->segments[0];
		}

		return null;
	}

	/**
	 * @psalm-mutation-free
	 */
	public function valid(): bool
	{
		return isset($this->segments[0]);
	}

	/**
	 * @psalm-mutation-free
	 */
	public function current(): ?string
	{
		if ($this->valid()) {
			return $this->segments[0];
		}

		return null;
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function pop(): ?string
	{
		return array_shift($this->segments);
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function push(string $segment): void
	{
		array_unshift($this->segments, $segment);
	}

	/**
	 * @psalm-mutation-free
	 */
	#[\Override]
	public function count(): int
	{
		return count($this->segments);
	}

	/**
	 * @psalm-mutation-free
	 */
	public function isEmpty(): bool
	{
		return $this->count() === 0;
	}
}
