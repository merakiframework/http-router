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
			'/ping' => ['ping', 'GetAction'],
			'/pings' => ['pings', 'GetAllAction'],
			'/user' => ['user', 'GetAction'],
			'/users' => ['users', 'GetAllAction'],
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
			'/ping/123/profile' => ['ping', 'profile', 'GetAction'],
			'/pings/123/profile' => ['pings', 'profile', 'GetOneAction'],
			'/user/123/profile' => ['user', 'profile', 'GetAction'],
			'/users/123/profile' => ['users', 'profile', 'GetOneAction'],
			'/ping/123/likes' => ['ping', 'likes', 'GetAllAction'],
			'/pings/123/likes' => ['pings', 'likes', 'GetAllAction'],
			'/user/123/likes' => ['user', 'likes', 'GetAllAction'],
			'/users/123/likes' => ['users', 'likes', 'GetAllAction'],
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
			'/ping/1' => ['ping', 'GetAction'],
			'/pings/1' => ['pings', 'GetOneAction'],
			'/user/1' => ['user', 'GetAction'],
			'/users/1' => ['users', 'GetOneAction'],
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
			'/ping/1/profile/1' => ['ping', 'profile', 'GetAction'],
			'/pings/1/profile/1' => ['pings', 'profile', 'GetAction'],
			'/user/1/profile/1' => ['user', 'profile', 'GetAction'],
			'/users/1/profile/1' => ['users', 'profile', 'GetAction'],

			'/ping/1/likes/1' => ['ping', 'likes', 'GetAction'],
			'/pings/1/likes/1' => ['pings', 'likes', 'GetOneAction'],
			'/user/1/likes/1' => ['user', 'likes', 'GetAction'],
			'/users/1/likes/1' => ['users', 'likes', 'GetOneAction'],

			'/states/qld/suburbs/emerald/registered-businesses/cleaning/pest-control' => ['suburbs', 'registered-businesses', 'GetOneAction'],
		];
	}

	private function createTranslatorWithDefaultConfig(): Translator
	{
		return new Translator(Config::create(self::REQUEST_HANDLERS_NAMESPACE));
	}
}
