<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Router\Config;

/**
 * The `Translator` class is responsible for taking a HTTP request method, and
 * some URL segments, and returning an appropriate request-handler name.
 *
 * $method = 'GET';
 * $urlPath = '/users/123/profile
 *
 * $className = $translator->translate($method, 'users', 'profile', false);
 *
 * $className === 'GetOneAction'; // true
 *
 * @internal
 * @copyright 2022 Nathan Bishop <nbish11@hotmail.com>
 * @license The MIT license.
 */
final class Translator
{
	/**
	 * @psalm-mutation-free
	 */
	public function __construct(private Config $config)
	{
	}

	public function translate(string $method, string $parentResource, string $currentResource, bool $hasNextSegment): string
	{
		$method = $this->config->inflector->classify($method);

		if (in_array($currentResource, $this->config->excludedWords)) {
			return $this->config->prefix
				. $method
				. $this->config->suffix;
		}

		if (in_array($currentResource, $this->config->excludedPluralWords)) {
			return $this->config->prefix
				. $method
				. $this->config->pluralIndicator
				. $this->config->suffix;
		}

		// Compound words (containing a word separator) are verb/action routes, not RESTful
		// resources, unless a custom inflection rule has been registered for this word.
		$hasCustomRule = array_key_exists($currentResource, $this->config->singularToPlural)
			|| array_key_exists($currentResource, $this->config->pluralToSingular);

		if (!$hasCustomRule && str_contains($currentResource, '-')) {
			return $this->config->prefix . $method . $this->config->suffix;
		}

		if ($parentResource !== '' && !$hasNextSegment) {
			if ($this->isPlural($parentResource) && $this->isSingular($currentResource)) {
				// Sub-resource/action route (e.g. /persons/schema → Persons\Schema\GetAction).
				// This is NOT a REST ID — the ID case is handled by the parent's GetOneAction
				// consuming the segment as an argument when no sub-namespace class exists.
				return $this->config->prefix
					. $method
					. $this->config->suffix;
			}

			if ($this->isPlural($parentResource) && $this->isPlural($currentResource)) {
				return $this->config->prefix
					. $method
					. $this->config->pluralIndicator
					. $this->config->suffix;
			}

			if ($this->isPlural($currentResource)) {
				return $this->config->prefix
					. $method
					. $this->config->pluralIndicator
					. $this->config->suffix;
			}
		}

		if ($parentResource !== '' && $hasNextSegment) {
			if ($this->isSingular($parentResource) && $this->isPlural($currentResource)) {
				return $this->config->prefix
					. $method
					. $this->config->suffix;
			}

			// if ($this->isPlural($parentResource) && $this->isPlural($currentResource)) {
			// 	return $this->config->prefix
			// 		. $method
			// 		. $this->config->pluralIndicator
			// 		. $this->config->suffix;
			// }
		}

		if ($this->isPlural($currentResource) && !$hasNextSegment) {
			return $this->config->prefix
				. $method
				. $this->config->pluralIndicator
				. $this->config->suffix;
		}

		if ($this->isPlural($currentResource) && $hasNextSegment) {
			return $this->config->prefix
				. $method
				. $this->config->singularIndicator
				. $this->config->suffix;
		}

		// default
		return $this->config->prefix
			. $method
			. $this->config->suffix;
	}

	public function urlSegmentToNamespaceSegment(string $word): string
	{
		return $this->config->inflector->classify($word);
	}

	/**
	 * @psalm-api
	 */
	public function namespaceSegmentToUrlSegment(string $word): string
	{
		return $this->config->inflector->urlize($word);
	}

	private function isSingular(string $word): bool
	{
		if (array_key_exists($word, $this->config->singularToPlural)) {
			return true;
		}

		return $word !== ''
			&& !array_key_exists($word, $this->config->pluralToSingular)
			&& $this->config->inflector->singularize($word) === $word;
	}

	private function isPlural(string $word): bool
	{
		if (array_key_exists($word, $this->config->pluralToSingular)) {
			return true;
		}

		return $word !== ''
			&& !array_key_exists($word, $this->config->singularToPlural)
			&& $this->config->inflector->pluralize($word) === $word;
	}
}
