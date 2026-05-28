<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Router\Exception\InvalidArgument;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
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
	 * HTTP methods the router will route to handler classes. Other methods
	 * receive a 405 Method Not Allowed. Extend this list with
	 * withAdditionalMethods() to enable WebDAV, PATCH-only APIs, or other
	 * non-standard verbs.
	 *
	 * @psalm-readonly-allow-private-mutation
	 * @var string[]
	 */
	public array $supportedMethods = ['get', 'head', 'post', 'put', 'delete', 'options', 'patch'];

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
	 * Register additional HTTP methods the router should route to handler
	 * classes. Useful for WebDAV (PROPFIND, PROPPATCH, MKCOL, COPY, MOVE,
	 * LOCK, UNLOCK) or other HTTP extensions. Method names are normalised
	 * to lowercase and deduplicated.
	 *
	 * @psalm-external-mutation-free
	 */
	public function withAdditionalMethods(string $method, string ...$methods): self
	{
		$cloned = clone $this;

		foreach ([$method, ...$methods] as $m) {
			$lower = strtolower($m);
			if (!in_array($lower, $cloned->supportedMethods, true)) {
				$cloned->supportedMethods[] = $lower;
			}
		}

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
