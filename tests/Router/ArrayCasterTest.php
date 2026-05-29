<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayCaster::class)]
final class ArrayCasterTest extends TestCase
{
	private ArrayCaster $caster;
	private CasterChain $chain;
	private Type $arrayType;

	protected function setUp(): void
	{
		$this->caster = new ArrayCaster();
		$this->chain = new CasterChain([]);
		$this->arrayType = new Type('array', true, false);
	}

	#[Test()]
	public function supports_only_array(): void
	{
		$this->assertTrue($this->caster->supports(new Type('array', true, false)));
		$this->assertFalse($this->caster->supports(new Type('int', true, false)));
	}

	#[Test()]
	#[DataProvider('validArrays')]
	public function casts_homogeneous_csv_lists(string $value, array $expected): void
	{
		$result = $this->caster->cast([$value], $this->arrayType, $this->chain);

		$this->assertSame(CastStatus::Successful, $result->status);
		$this->assertIsArray($result->value);
		$this->assertTrue(array_is_list($result->value));
		$this->assertEquals($expected, $result->value);
		$this->assertSame(1, $result->consumed);
	}

	/**
	 * @return array<string, array{string, array<int, int|float|string>}>
	 */
	public static function validArrays(): array
	{
		return [
			'integers list' => ['1,2,3', [1, 2, 3]],
			'strings list' => ['one,two,three', ['one', 'two', 'three']],
			'floats list' => ['3.14159,1.61803,2.71828', [3.14159, 1.61803, 2.71828]],
		];
	}

	#[Test()]
	#[DataProvider('invalidArrays')]
	public function returns_cannot_cast_when_list_is_malformed_or_mixed(string $value): void
	{
		$result = $this->caster->cast([$value], $this->arrayType, $this->chain);

		$this->assertSame(CastStatus::CannotCast, $result->status);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidArrays(): array
	{
		return [
			'empty list' => [''],
			'leading comma in list' => [',two,three'],
			'trailing comma in list' => ['one,two,'],
			'missing element' => ['one,,three'],
			'different types' => ['1,one,3.14159'],
		];
	}
}
