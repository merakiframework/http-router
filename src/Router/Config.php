<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Router\Exception\InvalidArgument;
// use Meraki\Http\Router\Translator;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
// use Negotiation\Negotiator;
use Psr\Log\LoggerInterface as PsrLogger;
use Psr\Log\NullLogger;

final class Config
{
	/**
	 * @psalm-readonly-allow-private-mutation
	 */
	public PsrLogger $logger;

	/**
	 * @psalm-readonly-allow-private-mutation
	 */
	public Inflector $inflector;

	/**
	 * @psalm-readonly-allow-private-mutation
	 */
	public string $prefix = '';

	/**
	 * @psalm-readonly-allow-private-mutation
	 */
	public string $suffix = 'Action';

	/**
	 * @psalm-readonly-allow-private-mutation
	 */
	public string $pluralIndicator = 'All';

	/**
	 * @psalm-readonly-allow-private-mutation
	 */
	public string $singularIndicator = 'One';

	/**
	 * @psalm-readonly-allow-private-mutation
	 * @var string[]
	 */
	public array $excludedSingularWords = [];

	/**
	 * @psalm-readonly-allow-private-mutation
	 * @var string[]
	 */
	public array $excludedPluralWords = [];

	/**
	 * @psalm-readonly-allow-private-mutation
	 */
	public string $rootPathSubNamespace = '\\Home';

	/**
	 * @psalm-readonly-allow-private-mutation
	 */
	public string $invokeMethod = '__invoke';

	/**
	 * @psalm-readonly-allow-private-mutation
	 */
	public string $namespace = '';

	/**
	 * @psalm-readonly-allow-private-mutation
	 * @var array<string, callable>
	 */
	public array $typeValidators = [];

	private function __construct(string $namespace)
	{
		$this->setNamespace($namespace);
		$this->inflector = InflectorFactory::create()->build();
		$this->logger = new NullLogger();
		$this->typeValidators = self::defaultTypeValidators();
	}

	public static function create(string $namespace): self
	{
		return new self($namespace);
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function withRootPathSubNamespace(string $namespace): self
	{
		$cloned = clone $this;
		$cloned->rootPathSubNamespace = '\\' . trim($namespace, '\\');

		return $cloned;
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function withInflector(Inflector $inflector): self
	{
		$cloned = clone $this;
		$cloned->inflector = $inflector;

		return $cloned;
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function withLogger(PsrLogger $logger): self
	{
		$cloned = clone $this;
		$cloned->logger = $logger;

		return $cloned;
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function withNamespace(string $namespace): self
	{
		$cloned = clone $this;
		$cloned->setNamespace($namespace);

		return $cloned;
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function withPrefix(string $prefix): self
	{
		$cloned = clone $this;
		$cloned->prefix = $prefix;

		return $cloned;
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function withSuffix(string $suffix): self
	{
		$cloned = clone $this;
		$cloned->suffix = $suffix;

		return $cloned;
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function withPluralIndicator(string $name): self
	{
		$cloned = clone $this;
		$cloned->pluralIndicator = $name;

		return $cloned;
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function withSingularIndicator(string $name): self
	{
		$cloned = clone $this;
		$cloned->singularIndicator = $name;

		return $cloned;
	}

	/**
	 * @param list<string> $words
	 * @psalm-external-mutation-free
	 */
	public function excludeSingularWords(string $word, string ...$words): self
	{
		$cloned = clone $this;
		$cloned->excludedSingularWords = array_merge([$word], $words);

		return $cloned;
	}

	/**
	 * @param list<string> $words
	 * @psalm-external-mutation-free
	 */
	public function excludePluralWords(string $word, string ...$words): self
	{
		$cloned = clone $this;
		$cloned->excludedPluralWords = array_merge([$word], $words);

		return $cloned;
	}

	/**
	 * @psalm-external-mutation-free
	 */
	public function withInvokeMethod(string $method): self
	{
		$cloned = clone $this;
		$cloned->invokeMethod = $method;

		return $cloned;
	}

	public function __clone()
	{
		$this->inflector = clone $this->inflector;
		$this->logger = clone $this->logger;
	}

	private function setNamespace(string $namespace): void
	{
		if ($namespace === '') {
			throw InvalidArgument::namespaceValueIsMissing();
		}

		if ($namespace === '\\') {
			throw InvalidArgument::namespaceCannotBeInGlobalScope();
		}

		$this->namespace = trim($namespace, '\\');
	}

	/**
	 * @psalm-mutation-free
	 * @return array<string, callable>
	 */
	private static function defaultTypeValidators(): array
	{
		return [
			'int' => 'ctype_digit',
			'string' => function (string $segment): bool {
				return $segment !== '';
			},
		];
	}
}
