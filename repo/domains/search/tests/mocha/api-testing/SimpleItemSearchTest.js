'use strict';

const { assert, utils, wiki } = require( 'api-testing' );
const { RequestBuilder } = require( '../../../../../rest-api/tests/mocha/helpers/RequestBuilder' );
const { expect } = require( '../../../../../rest-api/tests/mocha/helpers/chaiHelper' );

async function createItem( item ) {
	return ( await new RequestBuilder()
		.withRoute( 'POST', '/v1/entities/items' )
		.withJsonBodyParam( 'item', item )
		.makeRequest() ).body;
}

function newSearchRequest( language, searchTerm ) {
	return new RequestBuilder()
		.withRoute( 'GET', '/v0/search/items' )
		.withQueryParam( 'language', language )
		.withQueryParam( 'q', searchTerm );
}

describe( 'Simple item search', () => {
	let item1;
	let item2;

	const englishTermMatchingTwoItems = 'label-' + utils.uniq();
	const item1Label = englishTermMatchingTwoItems;
	const item1Description = 'item 1 en';
	const item1GermanLabel = 'de-label-' + utils.uniq();
	const item1GermanDescription = 'item 1 de';

	const item2Label = englishTermMatchingTwoItems;
	const item2Description = 'item 2 en';

	before( async () => {
		item1 = await createItem( {
			labels: {
				en: item1Label,
				de: item1GermanLabel
			},
			descriptions: {
				en: item1Description,
				de: item1GermanDescription
			}
		} );
		item2 = await createItem( { labels: { en: item2Label }, descriptions: { en: item2Description } } );

		await wiki.runAllJobs();
		await new Promise( ( resolve ) => {
			setTimeout( resolve, 1000 ); // apparently the index update still needs a bit after runAllJobs()
		} );
	} );

	it( 'finds items matching the search term', async () => {
		const response = await newSearchRequest( 'en', englishTermMatchingTwoItems )
			.assertValidRequest()
			.makeRequest();

		expect( response ).to.have.status( 200 );

		const results = response.body.results;
		assert.lengthOf( results, 2 );

		const item1Result = results.find( ( { id } ) => id === item1.id );
		assert.deepEqual( item1Result, { id: item1.id, label: item1Label, description: item1Description } );

		const item2Result = results.find( ( { id } ) => id === item2.id );
		assert.deepEqual( item2Result, { id: item2.id, label: item2Label, description: item2Description } );
	} );

	it( 'finds items matching the search term in another language', async () => {
		const response = await newSearchRequest( 'de', item1GermanLabel )
			.assertValidRequest()
			.makeRequest();

		expect( response ).to.have.status( 200 );
		assert.deepEqual(
			response.body.results,
			[ { id: item1.id, label: item1GermanLabel, description: item1GermanDescription } ]
		);
	} );

	it( 'finds nothing if no items match', async () => {
		const response = await newSearchRequest( 'en', utils.uniq( 40 ) )
			.assertValidRequest()
			.makeRequest();

		expect( response ).to.have.status( 200 );

		const results = response.body.results;
		assert.lengthOf( results, 0 );
	} );
} );
