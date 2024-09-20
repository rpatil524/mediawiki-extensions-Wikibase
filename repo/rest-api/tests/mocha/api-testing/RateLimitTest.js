'use strict';

const { describeWithTestData } = require( '../helpers/describeWithTestData' );
const {
	getItemEditRequests,
	getPropertyEditRequests
} = require( '../helpers/happyPathRequestBuilders' );
const { assertValidError } = require( '../helpers/responseValidator' );
describeWithTestData( 'Rate Limiting', ( itemRequestInputs, propertyRequestInputs ) => {

	[
		...getItemEditRequests( itemRequestInputs ),
		...getPropertyEditRequests( propertyRequestInputs )
		// TODO createItem is special and will be handled in a follow-up
	].forEach( ( { newRequestBuilder } ) => {
		it( `${newRequestBuilder().getRouteDescription()} responds 429 when the edit rate limit is reached`, async () => {
			const response = await newRequestBuilder()
				.withHeader( 'X-Wikibase-CI-Anon-Rate-Limit-Zero', true )
				.makeRequest();

			assertValidError(
				response,
				429,
				'request-limit-reached',
				{ reason: 'rate-limit-reached' }
			);
		} );
	} );

} );
