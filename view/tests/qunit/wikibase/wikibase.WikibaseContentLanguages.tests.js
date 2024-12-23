( function ( wb, sinon ) {
	'use strict';

	var sandbox = sinon.sandbox.create();

	QUnit.module( 'wikibase.WikibaseContentLanguages', {
		afterEach: function () {
			sandbox.restore();
		}
	} );

	QUnit.test( 'constructor', ( assert ) => {
		assert.throws( () => {
			new wb.WikibaseContentLanguages(); // eslint-disable-line no-new
		}, 'instantiated without a language list' );

		assert.throws( () => {
			new wb.WikibaseContentLanguages( [ 'en' ] ); // eslint-disable-line no-new
		}, 'instantiated without a getName function' );
	} );

	QUnit.test( 'getAll', ( assert ) => {
		var expectedLanguages = [ 'ar', 'de', 'en', 'ko' ],
			getName = sandbox.stub().throws( 'should not be called in this test' ),
			allLanguages = ( new wb.WikibaseContentLanguages( expectedLanguages, getName ) ).getAll();

		expectedLanguages.forEach( ( languageCode ) => {
			assert.notStrictEqual( allLanguages.indexOf( languageCode ), -1 );
		} );
	} );

	QUnit.test( 'getName', ( assert ) => {
		var getName = sandbox.stub().withArgs( 'eo' ).returns( 'Esperanto' );

		assert.strictEqual(
			( new wb.WikibaseContentLanguages( [ 'eo' ], getName ) ).getName( 'eo' ),
			'Esperanto'
		);
	} );

	QUnit.test( 'getLanguageNameMap', ( assert ) => {
		var getName = sandbox.stub();
		getName.withArgs( 'en' ).returns( 'English' );
		getName.withArgs( 'eo' ).returns( 'Esperanto' );

		var result = ( new wb.WikibaseContentLanguages( [ 'en', 'eo' ], getName ) ).getLanguageNameMap();
		assert.deepEqual(
			result,
			{ en: 'English', eo: 'Esperanto' }
		);
	} );

	QUnit.test( 'getMonolingualTextLanguages', ( assert ) => {
		var allLanguages = ( wb.WikibaseContentLanguages.getMonolingualTextLanguages() ).getAll();

		[ 'abe', 'de', 'en', 'ko' ].forEach( ( languageCode ) => {
			assert.notStrictEqual( allLanguages.indexOf( languageCode ), -1 );
		} );
	} );

	QUnit.test( 'getTermLanguages', ( assert ) => {
		var allLanguages = ( wb.WikibaseContentLanguages.getTermLanguages() ).getAll();

		[ 'bag', 'de', 'en', 'ko' ].forEach( ( languageCode ) => {
			assert.notStrictEqual( allLanguages.indexOf( languageCode ), -1 );
		} );
	} );

}( wikibase, sinon ) );
