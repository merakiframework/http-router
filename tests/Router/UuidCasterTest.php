<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Ramsey\Uuid\Rfc4122\UuidV4;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UuidCaster::class)]
final class UuidCasterTest extends TestCase
{
	private UuidCaster $caster;
	private CasterChain $chain;

	protected function setUp(): void
	{
		$this->caster = new UuidCaster();
		$this->chain = new CasterChain([]);
	}

	#[Test()]
	public function supports_the_uuid_interface_only(): void
	{
		$this->assertTrue($this->caster->supports(new Type(UuidInterface::class, false, false)));
		// ramsey returns a lazy instance from fromString() that isn't a concrete
		// version class, so version-specific param types are not handled.
		$this->assertFalse($this->caster->supports(new Type(UuidV4::class, false, false)));
		$this->assertFalse($this->caster->supports(new Type('int', true, false)));
		$this->assertFalse($this->caster->supports(new Type(\stdClass::class, false, false)));
	}

	#[Test()]
	public function casts_any_valid_uuid_for_the_interface(): void
	{
		$uuid = Uuid::uuid4()->toString();

		$result = $this->caster->cast([$uuid], new Type(UuidInterface::class, false, false), $this->chain);

		$this->assertSame(CastStatus::Successful, $result->status);
		$this->assertInstanceOf(UuidInterface::class, $result->value);
		$this->assertSame($uuid, $result->value->toString());
		$this->assertSame(1, $result->consumed);
	}

	#[Test()]
	public function rejects_a_malformed_uuid(): void
	{
		$result = $this->caster->cast(['not-a-uuid'], new Type(UuidInterface::class, false, false), $this->chain);

		$this->assertSame(CastStatus::CannotCast, $result->status);
	}
}
