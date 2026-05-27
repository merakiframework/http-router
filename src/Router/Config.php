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

/**
 * @psalm-api
 */
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
	public array $excludedWords = [];

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

	private function __construct(string $namespace)
	{
		$this->setNamespace($namespace);
		$this->inflector = InflectorFactory::create()->build();
		$this->logger = new NullLogger();
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
	 * @psalm-readonly-allow-private-mutation
	 * @var array<string,string>
	 */
	public array $singularToPlural = [];

	/**
	 * @psalm-readonly-allow-private-mutation
	 * @var array<string,string>
	 */
	public array $pluralToSingular = [];

	/**
	 * Override the inflector's singular↔plural mapping for a specific word.
	 * Pass the same word for both arguments to mark it as invariant (e.g. music→music).
	 * Custom rules also bypass the compound-word GetAction default, so you can use
	 * withInflectionRule('registered-businesses', 'registered-businesses') to treat a
	 * hyphenated compound word as a RESTful resource.
	 *
	 * @psalm-external-mutation-free
	 */
	public function withInflectionRule(string $singular, string $plural): self
	{
		$cloned = clone $this;
		$cloned->singularToPlural[$singular] = $plural;
		$cloned->pluralToSingular[$plural] = $singular;

		return $cloned;
	}

	/**
	 * @deprecated Use withInflectionRule() for inflection overrides. Compound words
	 *             now default to GetAction automatically without any configuration.
	 * @param list<string> $words
	 * @psalm-external-mutation-free
	 */
	public function excludeFromConversion(string $word, string ...$words): self
	{
		$cloned = clone $this;
		$cloned->excludedWords = array_merge([$word], $words);

		return $cloned;
	}

	/**
	 * @deprecated Use withInflectionRule($singular, $singular) for words that should
	 *             not follow standard singular/plural patterns.
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
	 * @deprecated Use withInflectionRule($singular, $plural) to register a custom
	 *             plural form, or withInflectionRule($word, $word) for invariant nouns
	 *             (e.g. music, sheep).
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

	/**
	 * @psalm-external-mutation-free
	 */
	public function __clone()
	{
		$this->inflector = clone $this->inflector;
		$this->logger = clone $this->logger;
	}

	/**
	 * @psalm-external-mutation-free
	 */
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
}
