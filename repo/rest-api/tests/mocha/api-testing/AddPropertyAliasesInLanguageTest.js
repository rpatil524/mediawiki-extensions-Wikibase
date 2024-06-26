'use strict';

const { assert, action } = require( 'api-testing' );
const { expect } = require( '../helpers/chaiHelper' );
const entityHelper = require( '../helpers/entityHelper' );
const { newAddPropertyAliasesInLanguageRequestBuilder: newRequest } = require( '../helpers/RequestBuilderFactory' );
const { makeEtag } = require( '../helpers/httpHelper' );
const { assertValidError } = require( '../helpers/responseValidator' );

describe( newRequest().getRouteDescription(), () => {
	let testPropertyId;
	let originalLastModified;
	let originalRevisionId;
	const existingEnglishAlias = 'first english alias';
	const existingFrenchAlias = 'first french alias';

	function assertValidResponse( response, aliases ) {
		assert.strictEqual( response.header[ 'content-type' ], 'application/json' );
		assert.isAbove( new Date( response.header[ 'last-modified' ] ), originalLastModified );
		assert.notStrictEqual( response.header.etag, makeEtag( originalRevisionId ) );
		assert.deepEqual( response.body, aliases );
	}

	function assertValid200Response( response, aliases ) {
		expect( response ).to.have.status( 200 );
		assertValidResponse( response, aliases );
	}

	function assertValid201Response( response, aliases ) {
		expect( response ).to.have.status( 201 );
		assertValidResponse( response, aliases );
	}

	before( async () => {
		const createEntityResponse = await entityHelper.createEntity( 'property', {
			datatype: 'string',
			aliases: {
				en: [ { language: 'en', value: existingEnglishAlias } ],
				fr: [ { language: 'fr', value: existingFrenchAlias } ]
			}
		} );
		testPropertyId = createEntityResponse.entity.id;

		const testPropertyCreationMetadata = await entityHelper.getLatestEditMetadata( testPropertyId );
		originalLastModified = new Date( testPropertyCreationMetadata.timestamp );
		originalRevisionId = testPropertyCreationMetadata.revid;

		// wait 1s before next test to ensure the last-modified timestamps are different
		await new Promise( ( resolve ) => {
			setTimeout( resolve, 1000 );
		} );
	} );

	describe( '20x success response ', () => {
		it( 'can add to an existing list of aliases with edit metadata omitted', async () => {
			const response = await newRequest( testPropertyId, 'en', [ 'next english alias' ] )
				.assertValidRequest()
				.makeRequest();

			assertValid200Response(
				response,
				[ existingEnglishAlias, 'next english alias' ]
			);
		} );

		it( 'can add aliases with edit metadata provided', async () => {
			const user = await action.robby(); // robby is a bot
			const tag = await action.makeTag( 'e2e test tag', 'Created during e2e test' );
			const editSummary = 'omg look i made an edit';

			const language = 'fr';
			const newAlias = 'fr-alias';

			const response = await newRequest( testPropertyId, language, [ newAlias ] )
				.withJsonBodyParam( 'tags', [ tag ] )
				.withJsonBodyParam( 'bot', true )
				.withJsonBodyParam( 'comment', editSummary )
				.withUser( user )
				.assertValidRequest()
				.makeRequest();

			assertValid200Response( response, [ existingFrenchAlias, newAlias ] );
			const editMetadata = await entityHelper.getLatestEditMetadata( testPropertyId );
			assert.deepEqual( editMetadata.tags, [ tag ] );
			assert.property( editMetadata, 'bot' );
			assert.strictEqual(
				editMetadata.comment,
				`/* wbsetaliases-add:1|${language} */ ${newAlias}, ${editSummary}`
			);
			assert.strictEqual( editMetadata.user, user.username );
		} );

		it( 'can create a new list of aliases with edit metadata omitted', async () => {
			const newAliases = [ 'first de alias', 'second de alias' ];
			const response = await newRequest( testPropertyId, 'de', newAliases )
				.assertValidRequest()
				.makeRequest();

			assertValid201Response( response, newAliases );
		} );
	} );

	describe( '400 error response', () => {
		it( 'invalid request data', async () => {
			const response = await newRequest( testPropertyId, 'en', 'invalid alias type' )
				.assertInvalidRequest()
				.makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.code, 'invalid-value' );
			assert.strictEqual( response.body.message, "Invalid value at '/aliases'" );
		} );

		it( 'invalid property id', async () => {
			const response = await newRequest( 'X123', 'en', [ 'new alias' ] )
				.assertInvalidRequest()
				.makeRequest();

			assertValidError(
				response,
				400,
				'invalid-path-parameter',
				{ parameter: 'property_id' }
			);
		} );

		it( 'comment too long', async () => {
			const comment = 'x'.repeat( 501 );
			const response = await newRequest( testPropertyId, 'en', [ 'new alias' ] )
				.withJsonBodyParam( 'comment', comment )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 400, 'comment-too-long' );
			assert.include( response.body.message, '500' );
		} );

		it( 'invalid edit tag', async () => {
			const invalidEditTag = 'invalid tag';
			const response = await newRequest( testPropertyId, 'en', [ 'new alias' ] )
				.withJsonBodyParam( 'tags', [ invalidEditTag ] )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 400, 'invalid-edit-tag' );
			assert.include( response.body.message, invalidEditTag );
		} );

		it( 'invalid edit tag type', async () => {
			const response = await newRequest( testPropertyId, 'en', [ 'new alias' ] )
				.withJsonBodyParam( 'tags', 'not an array' ).assertInvalidRequest().makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.code, 'invalid-value' );
			assert.deepEqual( response.body.context, { path: '/tags' } );
		} );

		it( 'invalid bot flag type', async () => {
			const response = await newRequest( testPropertyId, 'en', [ 'new alias' ] )
				.withJsonBodyParam( 'bot', 'not boolean' ).assertInvalidRequest().makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.code, 'invalid-value' );
			assert.deepEqual( response.body.context, { path: '/bot' } );
		} );

		it( 'invalid comment type', async () => {
			const response = await newRequest( testPropertyId, 'en', [ 'new alias' ] )
				.withJsonBodyParam( 'comment', 123 )
				.assertInvalidRequest()
				.makeRequest();

			expect( response ).to.have.status( 400 );
			assert.strictEqual( response.body.code, 'invalid-value' );
			assert.deepEqual( response.body.context, { path: '/comment' } );
		} );

		it( 'invalid language code', async () => {
			const response = await newRequest( testPropertyId, '1e', [ 'new alias' ] )
				.assertInvalidRequest()
				.makeRequest();

			assertValidError(
				response,
				400,
				'invalid-path-parameter',
				{ parameter: 'language_code' }
			);
		} );

		it( 'alias is empty', async () => {
			const response = await newRequest( testPropertyId, 'en', [ '' ] )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 400, 'alias-empty' );
			assert.strictEqual( response.body.message, 'Alias must not be empty' );
		} );

		it( 'alias list is empty', async () => {
			const response = await newRequest( testPropertyId, 'en', [] )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 400, 'alias-list-empty' );
			assert.strictEqual( response.body.message, 'Alias list must not be empty' );
		} );

		it( 'alias too long', async () => {
			// this assumes the default value of 250 from Wikibase.default.php is in place and
			// may fail if $wgWBRepoSettings['string-limits']['multilang']['length'] is overwritten
			const maxLength = 250;
			const alias = 'x'.repeat( maxLength + 1 );
			const response = await newRequest( testPropertyId, 'en', [ alias ] )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 400, 'alias-too-long', { value: alias, 'character-limit': maxLength } );
			assert.strictEqual( response.body.message, `Alias must be no more than ${maxLength} characters long` );

		} );

		it( 'alias contains invalid characters', async () => {
			const invalidAlias = 'tab characters \t not allowed';
			const response = await newRequest( testPropertyId, 'en', [ invalidAlias ] )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 400, 'invalid-alias', { alias: invalidAlias } );
			assert.include( response.body.message, invalidAlias );
		} );

		it( 'duplicate input aliases', async () => {
			const duplicateAlias = 'foo';
			const response = await newRequest( testPropertyId, 'en', [ duplicateAlias, 'foo', duplicateAlias ] )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 400, 'duplicate-alias', { alias: duplicateAlias } );
			assert.include( response.body.message, duplicateAlias );
		} );

		it( 'input alias already exist', async () => {
			const response = await newRequest( testPropertyId, 'en', [ existingEnglishAlias ] )
				.assertValidRequest()
				.makeRequest();

			assertValidError( response, 400, 'duplicate-alias', { alias: existingEnglishAlias } );
			assert.include( response.body.message, existingEnglishAlias );
		} );
	} );

	it( 'responds 404 if the property does not exist', async () => {
		const propertyId = 'P9999999';
		const response = await newRequest( propertyId, 'en', [ 'my property alias' ] )
			.assertValidRequest()
			.makeRequest();

		assertValidError( response, 404, 'property-not-found' );
		assert.include( response.body.message, propertyId );
	} );
} );
