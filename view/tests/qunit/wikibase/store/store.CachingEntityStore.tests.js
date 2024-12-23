/**
 * @license GPL-2.0-or-later
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
( function () {
	'use strict';

	var CachingEntityStore = require( '../../../../resources/wikibase/store/store.CachingEntityStore.js' ),
		EntityStore = require( '../../../../resources/wikibase/store/store.EntityStore.js' );

	QUnit.module( 'wikibase.store.CachingEntityStore' );

	QUnit.test( 'Initialize', ( assert ) => {
		var entityStore = new CachingEntityStore();
		assert.true( entityStore.get instanceof Function, 'Entity store has get() method.' );
	} );

	QUnit.test( 'get() returns a jQuery promise', ( assert ) => {
		var store = new EntityStore();
		store.get = function ( entityId ) {
			return $.Deferred().resolve();
		};
		var entityStore = new CachingEntityStore( store ),
			promise = entityStore.get( 'id' );

		assert.true( promise.done instanceof Function, 'done() method exists.' );
	} );

	QUnit.test( 'upstream store is called', ( assert ) => {
		var store = new EntityStore();
		store.get = sinon.spy( ( entityId ) => $.Deferred().resolve() );
		var entityStore = new CachingEntityStore( store );

		var promise = entityStore.get( 'id' );

		return promise.done( ( entity ) => {
			sinon.assert.calledOnce( store.get );
			sinon.assert.calledWith( store.get, 'id' );
		} );
	} );

	QUnit.test( 'upstream store is called once', ( assert ) => {
		var store = new EntityStore();
		store.get = sinon.spy( ( entityId ) => $.Deferred().resolve() );
		var entityStore = new CachingEntityStore( store );

		var promise = entityStore.get( 'id' );

		return promise.done( ( entity ) => entityStore.get( 'id' ).done( () => {
			sinon.assert.calledOnce( store.get );
		} ) );
	} );

	QUnit.test( 'upstream store is called once for parallel calls', ( assert ) => {
		var store = new EntityStore();
		store.get = sinon.spy( ( entityId ) => {
			var deferred = $.Deferred();
			setTimeout( () => {
				deferred.resolve();
			}, 0 );
			return deferred.promise();
		} );
		var entityStore = new CachingEntityStore( store );

		var promise1 = entityStore.get( 'id' );
		var promise2 = entityStore.get( 'id' );

		return $.when( promise1, promise2 ).done( () => {
			sinon.assert.calledOnce( store.get );
		} );
	} );

}() );
