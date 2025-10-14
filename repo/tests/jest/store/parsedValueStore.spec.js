jest.mock(
	'../../../resources/wikibase.wbui2025/api/editEntity.js',
	() => ( { parseValue: jest.fn() } )
);

const { setActivePinia, createPinia } = require( 'pinia' );
const { useParsedValueStore } = require( '../../../resources/wikibase.wbui2025/store/parsedValueStore.js' );
const { parseValue: mockedParseValue } = require( '../../../resources/wikibase.wbui2025/api/editEntity.js' );

describe( 'parsed value store', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
	} );

	describe( 'getParsedValue', () => {

		it( 'sends one parseValue request with the right parameters', async () => {
			const parsedValueStore = useParsedValueStore();
			mockedParseValue.mockResolvedValueOnce( { type: 'string', value: 'abc' } );

			const parsedValue1 = await parsedValueStore.getParsedValue( 'P123', ' abc ' );
			expect( parsedValue1 ).toEqual( { type: 'string', value: 'abc' } );
			expect( mockedParseValue ).toHaveBeenCalledWith( 'P123', ' abc ' );

			const parsedValue2 = await parsedValueStore.getParsedValue( 'P123', ' abc ' );
			expect( parsedValue2 ).toEqual( { type: 'string', value: 'abc' } );
			expect( mockedParseValue ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'sends separate requests for different properties and values', async () => {
			const parsedValueStore = useParsedValueStore();
			mockedParseValue.mockImplementation( ( propertyId, value ) => Promise.resolve( {
				type: 'string',
				value: `parsed ${ propertyId }:${ value }`
			} ) );

			const parsedValue1 = await parsedValueStore.getParsedValue( 'P123', 'abc' );
			const parsedValue2 = await parsedValueStore.getParsedValue( 'P456', 'def' );
			const parsedValue3 = await parsedValueStore.getParsedValue( 'P123', 'def' );

			expect( mockedParseValue ).toHaveBeenCalledTimes( 3 );
			expect( parsedValue1 ).toEqual( { type: 'string', value: 'parsed P123:abc' } );
			expect( parsedValue2 ).toEqual( { type: 'string', value: 'parsed P456:def' } );
			expect( parsedValue3 ).toEqual( { type: 'string', value: 'parsed P123:def' } );
		} );

	} );

	describe( 'populateWithStatements', () => {

		it( 'imports string main snak, qualifiers and references', () => {
			const parsedValueStore = useParsedValueStore();
			const stringSnak = ( propertyId, string ) => ( {
				snaktype: 'value',
				property: propertyId,
				datatype: 'string',
				datavalue: {
					type: 'string',
					value: string
				}
			} );
			parsedValueStore.populateWithStatements( {
				P1: [ {
					mainsnak: stringSnak( 'P1', 'P1 main snak' ),
					qualifiers: {
						P2: [ stringSnak( 'P2', 'P1 qualifier P2' ) ]
					},
					references: [ {
						snaks: {
							P3: [ stringSnak( 'P3', 'P1 reference P3' ) ]
						}
					} ]
				} ],
				P2: [
					{ mainsnak: stringSnak( 'P2', 'P2 main snak 1' ) },
					{ mainsnak: stringSnak( 'P2', 'P2 main snak 2' ) }
				]
			} );

			expect( parsedValueStore.peekParsedValue( 'P1', 'P1 main snak' ) )
				.toEqual( { type: 'string', value: 'P1 main snak' } );
			expect( parsedValueStore.peekParsedValue( 'P2', 'P1 qualifier P2' ) )
				.toEqual( { type: 'string', value: 'P1 qualifier P2' } );
			expect( parsedValueStore.peekParsedValue( 'P3', 'P1 reference P3' ) )
				.toEqual( { type: 'string', value: 'P1 reference P3' } );
			expect( parsedValueStore.peekParsedValue( 'P2', 'P2 main snak 1' ) )
				.toEqual( { type: 'string', value: 'P2 main snak 1' } );
			expect( parsedValueStore.peekParsedValue( 'P2', 'P2 main snak 2' ) )
				.toEqual( { type: 'string', value: 'P2 main snak 2' } );
		} );

		it( 'ignores unsupported snaks without error', () => {
			const parsedValueStore = useParsedValueStore();

			parsedValueStore.populateWithStatements( {
				P1: [ {
					mainsnak: {
						snaktype: 'value',
						property: 'P1',
						datatype: 'wikibase-item',
						datavalue: {
							type: 'wikibase-entityid',
							value: {
								'entity-type': 'item',
								id: 'Q1'
							}
						}
					},
					qualifiers: {
						P2: [
							{ snaktype: 'somevalue', property: 'P2', datatype: 'string' },
							{ snaktype: 'novalue', property: 'P2', datatype: 'string' }
						],
						P3: [ {
							snaktype: 'value',
							property: 'P3',
							datatype: 'external-id',
							datavalue: {
								type: 'string',
								value: 'abc'
							}
						} ]
					}
				} ]
			} );

			expect( parsedValueStore.parsedValuesPerProperty ).toEqual( new Map() );
		} );

	} );

} );
