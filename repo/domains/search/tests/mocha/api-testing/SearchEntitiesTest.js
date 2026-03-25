'use strict';

const { action, assert, utils, wiki } = require( 'api-testing' );

const api = action.getAnon();
const ITEM_EN_LABEL = 'e2e-item-en-' + utils.uniq();
const ITEM_EN_ALIAS = 'e2e-item-alias-' + utils.uniq();
const ITEM_DE_LABEL = 'e2e-item-de-' + utils.uniq();
const PROP_EN_LABEL = 'e2e-prop-en-' + utils.uniq();
const LIMIT_LABEL_PREFIX = 'e2e-limit-' + utils.uniq();

async function createItem( payload ) {
	const response = await api.action( 'wbeditentity', {
		new: 'item',
		token: ( await api.loadTokens( [ 'csrf' ] ) ).csrftoken,
		data: JSON.stringify( payload ),
	}, 'POST' );
	return response.entity.id;
}

async function createProperty( payload ) {
	const response = await api.action( 'wbeditentity', {
		new: 'property',
		token: ( await api.loadTokens( [ 'csrf' ] ) ).csrftoken,
		data: JSON.stringify( payload ),
	}, 'POST' );
	return response.entity.id;
}

async function flushJobs() {
	await wiki.runAllJobs();
	// Wait for OpenSearch to finish applying the index update after the jobs have run
	await new Promise( ( resolve ) => {
		setTimeout( resolve, 1000 );
	} );
}

async function getConceptUri( itemId ) {
	const response = await api.action( 'query', {
		meta: 'siteinfo',
		siprop: 'general',
	} );
	const conceptBaseUri = response.query.general[ 'wikibase-conceptbaseuri' ];
	return conceptBaseUri + itemId;
}

describe( 'wbsearchentities', () => {
	let testItemId;
	let testPropertyId;
	let testItemConceptUri;

	before( async () => {
		testItemId = await createItem( {
			labels: {
				en: { language: 'en', value: ITEM_EN_LABEL },
				de: { language: 'de', value: ITEM_DE_LABEL },
			},
			aliases: {
				en: [ { language: 'en', value: ITEM_EN_ALIAS } ],
			},
		} );
		testPropertyId = await createProperty( {
			datatype: 'string',
			labels: {
				en: { language: 'en', value: PROP_EN_LABEL },
			},
		} );

		await flushJobs();

		testItemConceptUri = await getConceptUri( testItemId );
	} );

	it( 'returns empty results when no matches are found', async () => {
		const response = await api.action( 'wbsearchentities', {
			search: 'nonexistent',
			language: 'en',
			type: 'item',
		} );

		assert.isEmpty( response.search );
	} );

	it( 'response contains the expected fields and result shape', async () => {
		const response = await api.action( 'wbsearchentities', {
			search: testItemId,
			language: 'en',
			type: 'item',
		} );

		assert.equal( response.searchinfo.search, testItemId );
		assert.equal( response.success, 1 );

		const result = response.search[ 0 ];
		assert.containsAllKeys( result, [
			'id',
			'title',
			'pageid',
			'concepturi',
			'url',
			'display',
			'match',
			'label',
			'aliases',
		] );
	} );

	it( 'finds item by English label', async () => {
		const response = await api.action( 'wbsearchentities', {
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

	it( 'finds items by English alias', async () => {
		const response = await api.action( 'wbsearchentities', {
			search: ITEM_EN_ALIAS,
			language: 'en',
			type: 'item',
		} );

		const result = response.search.find( ( r ) => r.id === testItemId );
		assert.isOk( result, 'item should appear in search results' );
		assert.equal( result.match.type, 'alias' );
		assert.equal( result.match.text, ITEM_EN_ALIAS );
		assert.equal( result.display.label.value, ITEM_EN_LABEL );
		assert.include( result.aliases, ITEM_EN_ALIAS );
	} );

	it( 'finds property by English label', async () => {
		const response = await api.action( 'wbsearchentities', {
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
		const response = await api.action( 'wbsearchentities', {
			search: testItemId,
			language: 'en',
			type: 'item',
		} );

		assert.equal( response.search[ 0 ].id, testItemId );
		assert.equal( response.search[ 0 ].match.type, 'entityId' );
		assert.notProperty( response.search[ 0 ].match, 'language' );
	} );

	it( 'finds item by concept URI', async () => {
		const response = await api.action( 'wbsearchentities', {
			search: testItemConceptUri,
			language: 'en',
			type: 'item',
		} );

		assert.equal( response.search[ 0 ].id, testItemId );
		assert.equal( response.search[ 0 ].match.type, 'entityId' );
	} );

	it( 'finds items when no type is specified (defaults to item)', async () => {
		const response = await api.action( 'wbsearchentities', {
			search: ITEM_EN_LABEL,
			language: 'en',
			// no 'type' param – the API defaults to 'item'
		} );

		const itemResult = response.search.find( ( r ) => r.id === testItemId );
		assert.isOk( itemResult, 'item should appear in search results' );
	} );

	it( 'finds item by label in other languages', async () => {
		const response = await api.action( 'wbsearchentities', {
			search: ITEM_DE_LABEL,
			language: 'de',
			type: 'item',
		} );

		const result = response.search.find( ( r ) => r.id === testItemId );
		assert.isOk( result, 'item should appear in search results when searching in German' );
		assert.equal( result.match.language, 'de' );
		assert.equal( result.match.text, ITEM_DE_LABEL );
	} );

	describe( 'pagination', () => {
		before( async () => {
			await Promise.all( [ 1, 2, 3 ].map( async ( i ) => {
				await createItem( {
					labels: {
						en: { language: 'en', value: LIMIT_LABEL_PREFIX + i },
					},
				} );
			} ) );
			await flushJobs();
		} );

		it( 'limit controls the number of results', async () => {
			const response = await api.action( 'wbsearchentities', {
				search: LIMIT_LABEL_PREFIX,
				language: 'en',
				type: 'item',
				limit: 2,
			} );

			assert.lengthOf( response.search, 2 );
			assert.equal( response[ 'search-continue' ], 2 );
		} );

		it( 'continue offsets the results', async () => {
			const firstPageResponse = await api.action( 'wbsearchentities', {
				search: LIMIT_LABEL_PREFIX,
				language: 'en',
				type: 'item',
				limit: 2,
			} );
			const secondPageResponse = await api.action( 'wbsearchentities', {
				search: LIMIT_LABEL_PREFIX,
				language: 'en',
				type: 'item',
				limit: 2,
				continue: 2,
			} );

			assert.lengthOf( secondPageResponse.search, 1 );
			assert.notProperty( secondPageResponse, 'search-continue' );

			const firstPageIds = firstPageResponse.search.map( ( r ) => r.id );
			const secondPageId = secondPageResponse.search[ 0 ].id;
			assert.notInclude( firstPageIds, secondPageId );
		} );
	} );
} );
