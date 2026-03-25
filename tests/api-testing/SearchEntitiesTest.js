'use strict';

const { action, assert, utils, wiki } = require( 'api-testing' );

const ITEM_EN_LABEL = 'e2e-item-en-' + utils.uniq();
const ITEM_DE_LABEL = 'e2e-item-de-' + utils.uniq();
const PROP_EN_LABEL = 'e2e-prop-en-' + utils.uniq();

describe( 'wbsearchentities', () => {
	let mindy;
	let testItemId;
	let testPropertyId;
	let testItemConceptUri;

	before( 'set up admin', async () => {
		mindy = await action.mindy();
	} );

	before( 'create test item', async () => {
		const response = await mindy.action( 'wbeditentity', {
			new: 'item',
			token: await mindy.token( 'csrf' ),
			data: JSON.stringify( {
				labels: {
					en: { language: 'en', value: ITEM_EN_LABEL },
					de: { language: 'de', value: ITEM_DE_LABEL },
				},
			} ),
		}, 'POST' );
		testItemId = response.entity.id;
	} );

	before( 'create test property', async () => {
		const response = await mindy.action( 'wbeditentity', {
			new: 'property',
			token: await mindy.token( 'csrf' ),
			data: JSON.stringify( {
				datatype: 'string',
				labels: {
					en: { language: 'en', value: PROP_EN_LABEL },
				},
			} ),
		}, 'POST' );
		testPropertyId = response.entity.id;
	} );

	before( 'flush jobs', async () => {
		await wiki.runAllJobs();
		// Wait for OpenSearch to finish applying the index update after the jobs have run
		await new Promise( ( resolve ) => {
			setTimeout( resolve, 1000 );
		} );
	} );

	before( 'look up concept URI', async () => {
		const response = await mindy.action( 'query', {
			meta: 'siteinfo',
			siprop: 'general',
		} );
		const conceptBaseUri = response.query.general[ 'wikibase-conceptbaseuri' ];
		testItemConceptUri = conceptBaseUri + testItemId;
	} );

	it( 'finds item by English label', async () => {
		const response = await mindy.action( 'wbsearchentities', {
			search: ITEM_EN_LABEL,
			language: 'en',
			type: 'item',
		} );

		const result = response.search.find( ( r ) => r.id === testItemId );
		assert.isOk( result, 'item should appear in search results' );
		assert.equal( result.match.type, 'label' );
		assert.equal( result.match.language, 'en' );
		assert.equal( result.match.text, ITEM_EN_LABEL );
		assert.equal( result.display.label.value, ITEM_EN_LABEL );
		assert.equal( result.display.label.language, 'en' );
	} );

	it( 'finds property by English label', async () => {
		const response = await mindy.action( 'wbsearchentities', {
			search: PROP_EN_LABEL,
			language: 'en',
			type: 'property',
		} );

		const result = response.search.find( ( r ) => r.id === testPropertyId );
		assert.isOk( result, 'property should appear in search results' );
		assert.equal( result.match.type, 'label' );
		assert.equal( result.match.language, 'en' );
		assert.equal( result.match.text, PROP_EN_LABEL );
	} );

	it( 'finds item by entity ID', async () => {
		const response = await mindy.action( 'wbsearchentities', {
			search: testItemId,
			language: 'en',
			type: 'item',
		} );

		assert.equal( response.search[ 0 ].id, testItemId );
		assert.equal( response.search[ 0 ].match.type, 'entityId' );
		assert.notProperty( response.search[ 0 ].match, 'language' );
	} );

	it( 'finds item by concept URI', async () => {
		const response = await mindy.action( 'wbsearchentities', {
			search: testItemConceptUri,
			language: 'en',
			type: 'item',
		} );

		assert.equal( response.search[ 0 ].id, testItemId );
		assert.equal( response.search[ 0 ].match.type, 'entityId' );
	} );

	it( 'finds items when no type is specified (defaults to item)', async () => {
		const response = await mindy.action( 'wbsearchentities', {
			search: ITEM_EN_LABEL,
			language: 'en',
			// no 'type' param – the API defaults to 'item'
		} );

		const itemResult = response.search.find( ( r ) => r.id === testItemId );
		assert.isOk( itemResult, 'item should appear in search results' );
	} );

	it( 'finds item by label in other languages', async () => {
		const response = await mindy.action( 'wbsearchentities', {
			search: ITEM_DE_LABEL,
			language: 'de',
			type: 'item',
		} );

		const result = response.search.find( ( r ) => r.id === testItemId );
		assert.isOk( result, 'item should appear in search results when searching in German' );
		assert.equal( result.match.language, 'de' );
		assert.equal( result.match.text, ITEM_DE_LABEL );
	} );

} );
