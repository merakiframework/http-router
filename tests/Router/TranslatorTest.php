<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Router\Config;
use Meraki\Http\Router\Translator;
use PHPUnit\Framework\TestCase;

/**
 * @covers Translator::
 */
final class TranslatorTest extends TestCase
{
	private const REQUEST_HANDLERS_NAMESPACE = 'Project\\Http';

	/**
	 * @test
	 * @dataProvider noParentResourceOrChildSegments
	 */
	public function can_translate_with_no_next_segment_and_no_parent_resource(
		string $expectedChildResource,
		string $expectedClass
	): void {
		$sut = $this->createTranslatorWithDefaultConfig();

		$result = $sut->translate('get', '', $expectedChildResource, false);

		$this->assertEquals($expectedClass, $result);
	}

	public function noParentResourceOrChildSegments(): array
	{
		return [
			'/' => ['', 'GetAction'],
			'/account' => ['', 'GetAction'],
		];
	}

	/**
	 * @test
	 * @dataProvider noChildSegmentsButParentResource
	 */
	public function can_translate_with_no_next_segment_and_parent_resource(
		string $expectedParentResource,
		string $expectedChildResource,
		string $expectedClass
	): void {
		$sut = $this->createTranslatorWithDefaultConfig();

		$result = $sut->translate('get', $expectedParentResource, $expectedChildResource, false);

		$this->assertEquals($expectedClass, $result);
	}

	public function noChildSegmentsButParentResource(): array
	{
		return [
			'/users/123/profile' => ['users', 'profile', 'GetOneAction'],
			'/users/123/friends' => ['users', 'friends', 'GetAllAction'],
			'/states/qld/suburbs/emerald/registered-businesses' => ['suburbs', 'registered-businesses', 'GetAllAction'],
		];
	}

	/**
	 * @test
	 * @dataProvider noParentResourceButChildSegments
	 */
	public function can_translate_with_next_segment_and_no_parent_resource(
		string $expectedChildResource,
		string $expectedClass
	): void {
		$sut = $this->createTranslatorWithDefaultConfig();

		$result = $sut->translate('get', '', $expectedChildResource, true);

		$this->assertEquals($expectedClass, $result);
	}

	public function noParentResourceButChildSegments(): array
	{
		return [
			'/archives/<2022>/<12>/<21>' => ['', 'GetAction'],
		];
	}

	/**
	 * @test
	 * @dataProvider parentResourceAndChildSegments
	 */
	public function can_translate_with_next_segment_and_parent_resource(
		string $expectedParentResource,
		string $expectedChildResource,
		string $expectedClass
	): void {
		$sut = $this->createTranslatorWithDefaultConfig();

		$result = $sut->translate('get', $expectedParentResource, $expectedChildResource, true);

		$this->assertEquals($expectedClass, $result);
	}

	public function parentResourceAndChildSegments(): array
	{
		return [
			'/states/qld/suburbs/emerald/registered-businesses/cleaning/pest-control' => ['suburbs', 'registered-businesses', 'GetOneAction'],
		];
	}

	private function createTranslatorWithDefaultConfig(): Translator
	{
		return new Translator(Config::create(self::REQUEST_HANDLERS_NAMESPACE));
	}
}
