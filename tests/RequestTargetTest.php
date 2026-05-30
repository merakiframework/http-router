<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\RequestTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestTarget::class)]
final class RequestTargetTest extends TestCase
{
	#[Test()]
	#[DataProvider('paths')]
	public function get_segments_returns_expected_list(string $path, array $expected): void
	{
		$this->assertSame($expected, new RequestTarget($path)->getSegments());
	}

	public static function paths(): array
	{
		return [
			'/contacts' => ['/contacts', ['contacts']],
			'/contacts/ (trailing slash stripped)' => ['/contacts/', ['contacts']],
			'/ (root -> no segments)' => ['/', []],
			'// (double-slash root -> no segments)' => ['//', []],
			'/a/b' => ['/a/b', ['a', 'b']],
			'/a/b/ (NOT [a, b, ""])' => ['/a/b/', ['a', 'b']],
			'empty string' => ['', []],
		];
	}

	#[Test()]
	public function lowercases_the_path(): void
	{
		$this->assertSame(['contacts'], new RequestTarget('/CONTACTS')->getSegments());
	}

	#[Test()]
	public function string_cast_returns_the_normalised_path(): void
	{
		$this->assertSame('/a/b', (string)(new RequestTarget('/A/B/')));
	}
}
