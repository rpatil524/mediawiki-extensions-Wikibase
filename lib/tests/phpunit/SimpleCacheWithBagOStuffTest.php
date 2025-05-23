<?php
declare( strict_types=1 );

namespace Wikibase\Lib\Tests;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Wikibase\Lib\SimpleCacheWithBagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;

/**
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @covers \Wikibase\Lib\SimpleCacheWithBagOStuff
 */
class SimpleCacheWithBagOStuffTest extends SimpleCacheTestCase {

	/** @var string[] */
	protected $skippedTests = [
		'testClear' => 'Not possible to implement for BagOStuff',
		'testBinaryData' => 'This cache does not support binary data',
	];

	/**
	 * @return CacheInterface that is used in the tests
	 */
	public function createSimpleCache() {
		return new SimpleCacheWithBagOStuff( new HashBagOStuff(), 'somePrefix', 'some secret' );
	}

	public function testUsesPrefixWhenSetting() {
		$inner = new HashBagOStuff();

		$prefix = 'somePrefix';
		$simpleCache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'some secret' );

		$simpleCache->set( 'test', 'value' );
		$key = $inner->makeKey( $prefix, 'test' );
		$this->assertNotFalse( $inner->get( $key ) );
	}

	public function testUsesPrefixWhenSettingMultiple() {
		$inner = new HashBagOStuff();

		$prefix = 'somePrefix';
		$simpleCache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'some secret' );

		$simpleCache->setMultiple( [ 'test' => 'value' ] );
		$key = $inner->makeKey( $prefix, 'test' );
		$this->assertNotFalse( $inner->get( $key ) );
	}

	public function testGivenPrefixContainsForbiddenCharacters_ConstructorThrowsException() {
		$prefix = '@somePrefix';
		$inner = new HashBagOStuff();

		$this->expectException( \InvalidArgumentException::class );
		new SimpleCacheWithBagOStuff( $inner, $prefix, 'some secret' );
	}

	/**
	 * This test ensures that we cannot accidentally deserialize arbitrary classes
	 * because it is unsecure.
	 *
	 * @see https://phabricator.wikimedia.org/T161647
	 * @see https://secure.php.net/manual/en/function.unserialize.php
	 * @see https://www.owasp.org/index.php/PHP_Object_Injection
	 */
	public function testObjectsCanNotBeStored_WhenRetrievedGetIncompleteClass() {
		$initialValue = new \DateTime();

		$cache = $this->createSimpleCache();
		$cache->set( 'key', $initialValue );
		$gotValue = $cache->get( 'key' );

		$this->assertInstanceOf( \__PHP_Incomplete_Class::class, $gotValue );
		$this->assertFalse( $initialValue == $gotValue );
	}

	/**
	 * This test ensures that if data in cache storage is compromised we won't accidentally
	 * use it.
	 *
	 * @see https://phabricator.wikimedia.org/T161647
	 * @see https://secure.php.net/manual/en/function.unserialize.php
	 * @see https://www.owasp.org/index.php/PHP_Object_Injection
	 */
	public function testGet_GivenSignatureIsWrong_ReturnsDefaultValue() {
		$inner = new HashBagOStuff();

		$cache = new SimpleCacheWithBagOStuff( $inner, 'prefix', 'some secret' );
		$cache->set( 'key', 'some_string' );
		$key = $inner->makeKey( 'prefix', 'key' );
		$this->spoilTheSignature( $inner, $key );

		$got = $cache->get( 'key', 'some default value' );
		$this->assertEquals( 'some default value', $got );
	}

	public function testGetMultiple_GivenSignatureIsWrong_ReturnsDefaultValue() {
		$inner = new HashBagOStuff();

		$cache = new SimpleCacheWithBagOStuff( $inner, 'prefix', 'some secret' );
		$cache->set( 'key', 'some_string' );
		$key = $inner->makeKey( 'prefix', 'key' );
		$this->spoilTheSignature( $inner, $key );

		$got = $cache->getMultiple( [ 'key' ], 'some default value' );
		$this->assertEquals( [ 'key' => 'some default value' ], $got );
	}

	public function testGet_GivenSignatureIsWrong_LoggsTheEvent() {
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->atLeastOnce() )->method( 'alert' );

		$inner = new HashBagOStuff();

		$cache = new SimpleCacheWithBagOStuff( $inner, 'prefix', 'some secret' );
		$cache->setLogger( $logger );
		$cache->set( 'key', 'some_string' );
		$key = $inner->makeKey( 'prefix', 'key' );
		$value = $inner->get( $key );
		[ $signature, $data ] = json_decode( $value );
		$inner->set( $key, json_encode( [ 2, 'wrong signature', $data ] ) );

		$got = $cache->get( 'key', 'some default value' );
	}

	public function testCachedValueCannotBeDecoded_ReturnsDefaultValue(): void {
		$inner = new HashBagOStuff();
		$prefix = 'prefix';
		$key = 'key';
		$inner->set( $inner->makeKey( $prefix, $key ), '{' ); // incomplete JSON

		$cache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'secret' );
		$got = $cache->get( $key, 'some default value' );

		$this->assertSame( 'some default value', $got );
	}

	public function testCachedValueIsNotArray_ReturnsDefaultValue(): void {
		$inner = new HashBagOStuff();
		$prefix = 'prefix';
		$key = 'key';
		$inner->set( $inner->makeKey( $prefix, $key ), '{}' );

		$cache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'secret' );
		$got = $cache->get( $key, 'some default value' );

		$this->assertSame( 'some default value', $got );
	}

	public function testCachedValueHasNonStringSignature_ReturnsDefaultValue(): void {
		$inner = new HashBagOStuff();
		$prefix = 'prefix';
		$key = 'key';
		$inner->set( $inner->makeKey( $prefix, $key ), '[2,3,"4"]' );

		$cache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'secret' );
		$got = $cache->get( $key, 'some default value' );

		$this->assertSame( 'some default value', $got );
	}

	public function testCachedValueHasNonStringData_ReturnsDefaultValue(): void {
		$inner = new HashBagOStuff();
		$prefix = 'prefix';
		$key = 'key';
		$inner->set( $inner->makeKey( $prefix, $key ), '[2,"3",4]' );

		$cache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'secret' );
		$got = $cache->get( $key, 'some default value' );

		$this->assertSame( 'some default value', $got );
	}

	public function testCachedValueHasWrongLength_ReturnsDefaultValue(): void {
		$inner = new HashBagOStuff();
		$prefix = 'prefix';
		$key = 'key';
		$inner->set( $inner->makeKey( $prefix, $key ), '[]' );

		$cache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'secret' );
		$got = $cache->get( $key, 'some default value' );

		$this->assertSame( 'some default value', $got );
	}

	public function testCachedValueCannotBeUnserialized_ReturnsDefaultValue() {
		$inner = new HashBagOStuff();
		$brokenData = 'O:1';

		$correctSignature = hash_hmac( 'sha256', $brokenData, 'secret' );

		$cache = new SimpleCacheWithBagOStuff( $inner, 'prefix', 'secret' );
		$cache->set( 'key', 'some_string' );
		$key = $inner->makeKey( 'prefix', 'key' );
		$inner->set( $key, json_encode( [ 2, $correctSignature, $brokenData ] ) );

		$got = $cache->get( 'key', 'some default value' );
		$this->assertEquals( 'some default value', $got );
	}

	public function testSecretCanNotBeEmpty() {
		$inner = new HashBagOStuff();

		$this->expectException( \Exception::class );
		new SimpleCacheWithBagOStuff( $inner, 'prefix', '' );
	}

	protected function spoilTheSignature( HashBagOStuff $inner, string $key ): void {
		$value = $inner->get( $key );
		[ $signature, $data ] = json_decode( $value );
		$inner->set( $key, json_encode( [ 2, 'wrong signature', $data ] ) );
	}

	public function testSetTtl() {
		$inner = new HashBagOStuff();
		$now = microtime( true );
		$inner->setMockTime( $now );

		$prefix = 'someprefix';
		$cache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'some secret' );

		$result = $cache->set( 'key1', 'value', 1 );
		$this->assertTrue( $result, 'set() must return true if success' );
		$this->assertEquals( 'value', $cache->get( 'key1' ) );
		$now += 3;
		$this->assertNull( $cache->get( 'key1' ), 'Value must expire after ttl.' );
	}

	public function testSetMultipleTtl() {
		$inner = new HashBagOStuff();
		$now = microtime( true );
		$inner->setMockTime( $now );

		$prefix = 'someprefix';
		$cache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'some secret' );

		$cache->setMultiple( [ 'key2' => 'value2', 'key3' => 'value3' ], 1 );
		$this->assertEquals( 'value2', $cache->get( 'key2' ) );
		$this->assertEquals( 'value3', $cache->get( 'key3' ) );

		$now += 3;
		$this->assertNull( $cache->get( 'key2' ), 'Value must expire after ttl.' );
		$this->assertNull( $cache->get( 'key3' ), 'Value must expire after ttl.' );
	}

	public function testUTF8KeysAreValid() {
		$inner = new HashBagOStuff();

		$prefix = 'someprefix';
		$cache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'some secret' );

		$this->assertTrue( $cache->set( '🏄', 'some value' ) );
		$this->assertTrue( $cache->set( '⧼Lang⧽', 'some value' ) );
	}

	public function testBinaryDataNotSupported(): void {
		$inner = new HashBagOStuff();
		$prefix = 'somePrefix';
		$secret = 'some secret';
		$simpleCache = new SimpleCacheWithBagOStuff( $inner, $prefix, $secret );

		$invalidUtf8 = chr( 128 ); // unexpected continuation byte
		$simpleCache->set( 'test', $invalidUtf8 );

		$this->assertSame( 'default', $simpleCache->get( 'test', 'default' ) );
	}

}
