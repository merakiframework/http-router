<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringCaster::class)]
final class StringCasterTest extends TestCase
{
	private StringCaster $caster;
	private Type $stringType;

	protected function setUp(): void
	{
		$this->caster = new StringCaster();
		$this->stringType = new Type('string', true, false);
	}

	#[Test()]
	public function supports_only_string(): void
	{
		$this->assertTrue($this->caster->supports(new Type('string', true, false)));
		$this->assertFalse($this->caster->supports(new Type('int', true, false)));
	}

	#[Test()]
	#[DataProvider('anySegment')]
	public function returns_any_segment_verbatim_and_never_throws(string $value): void
	{
		$this->assertSame($value, $this->caster->cast($value, $this->stringType));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function anySegment(): array
	{
		return [
			'word' => ['daniel'],
			'digits' => ['1234'],
			'not-a-number' => ['not-a-number'],
			'compound' => ['pest-control'],
			'empty' => [''],
		];
	}
}
