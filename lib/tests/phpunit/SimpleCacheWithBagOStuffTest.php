<?php

namespace Wikibase\Lib\Tests;

use Cache\IntegrationTests\SimpleCacheTest;
use HashBagOStuff;
use Psr\SimpleCache\CacheInterface;
use Wikibase\Lib\SimpleCacheWithBagOStuff;

/**
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class SimpleCacheWithBagOStuffTest extends SimpleCacheTest {

	use \PHPUnit4And6Compat;

	protected $skippedTests = [
		'testClear' => 'Not possible to implement for BagOStuff'
	];

	/**
	 * @return CacheInterface that is used in the tests
	 */
	public function createSimpleCache() {
		return new SimpleCacheWithBagOStuff( new HashBagOStuff(), 'somePrefix', 'some secret' );
	}

	public function testUsesPrefixWhenSetting() {
		$inner = new HashBagOStuff();

		$prefix = 'somePrefix_';
		$simpleCache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'some secret' );

		$simpleCache->set( 'test', 'value' );
		$this->assertNotFalse( $inner->get( 'somePrefix_test' ) );
	}

	public function testUsesPrefixWhenSettingMultiple() {
		$inner = new HashBagOStuff();

		$prefix = 'somePrefix_';
		$simpleCache = new SimpleCacheWithBagOStuff( $inner, $prefix, 'some secret' );

		$simpleCache->setMultiple( [ 'test' => 'value' ] );
		$this->assertNotFalse( $inner->get( 'somePrefix_test' ) );
	}

	public function testGivenPrefixContainsForbiddenCharacters_ConstructorThrowsException() {
		$prefix = '@somePrefix_';
		$inner = new HashBagOStuff();

		$this->setExpectedException( \InvalidArgumentException::class );
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
	public function testGet_GivenSignatureIsWrong_ThrowsAnException() {
		$inner = new HashBagOStuff();
		$initialValue = new \DateTime();

		$cache = new SimpleCacheWithBagOStuff( $inner, 'prefix_', 'some secret' );
		$cache->set( 'key', 'some_string' );
		$value = $inner->get( 'prefix_key', $initialValue );
		list( $signature, $data ) = json_decode( $value );
		$inner->set( 'prefix_key', json_encode( [ 'wrong signature', $data ] ) );

		$this->setExpectedException( \Exception::class );
		$cache->get( 'key' );
	}

	public function testCachedValueCannotBeUnserialized_ThrowsAnException() {
		$inner = new HashBagOStuff();
		$brokenData = 'O:1';

		$correctSignature = hash_hmac( 'sha256', $brokenData, 'secret' );

		$cache = new SimpleCacheWithBagOStuff( $inner, 'prefix_', 'secret' );
		$cache->set( 'key', 'some_string' );
		$inner->set( 'prefix_key', json_encode( [ $correctSignature, $brokenData ] ) );

		$this->setExpectedException( \Exception::class );

		$cache->get( 'key' );
	}

	public function testSecretCanNotBeEmpty() {
		$inner = new HashBagOStuff();

		$this->setExpectedException( \Exception::class );
		new SimpleCacheWithBagOStuff( $inner, 'prefix_', '' );
	}

}
