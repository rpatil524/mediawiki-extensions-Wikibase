const { defineStore } = require( 'pinia' );
const { reactive } = require( 'vue' );
const { parseValue } = require( '../api/editEntity.js' );

const useParsedValueStore = defineStore( 'parsedValue', {
	state: () => ( {
		parsedValuesPerProperty: new Map()
	} ),
	actions: {
		/**
		 * Request that the given input for the given property ID should be parsed,
		 * and return the parsed value asynchronously.
		 * Parsed values are cached, so the returned promise might already be resolved.
		 *
		 * @param {string} propertyId
		 * @param {string} value
		 * @returns {Promise<object|null>} A promise that will resolve to the parsed value
		 * (a data value object with "type" and "value" keys), or null if it could not be parsed.
		 */
		getParsedValue( propertyId, value ) {
			let parsedValues = this.parsedValuesPerProperty.get( propertyId );
			if ( parsedValues === undefined ) {
				parsedValues = new Map();
				this.parsedValuesPerProperty.set( propertyId, parsedValues );
			}
			let parsedValue = parsedValues.get( value );
			if ( parsedValue === undefined ) {
				parsedValue = reactive( {
					promise: parseValue( propertyId, value ).then( ( parsed ) => {
						parsedValue.resolved = parsed;
						return parsed;
					} ),
					resolved: undefined
				} );
				parsedValues.set( value, parsedValue );
			}
			return parsedValue.promise;
		},
		/**
		 * Add parsed values from the given statements (including their qualifiers and references).
		 *
		 * @param {Object} statements
		 */
		populateWithStatements( statements ) {
			const processSnak = ( snak ) => {
				if ( snak.snaktype !== 'value' ) {
					return;
				}
				if ( snak.datatype !== 'string' ) {
					return;
				}
				const dataValue = snak.datavalue;
				// for data type "string", assume that parsing the value would yield the same data value again
				const propertyId = snak.property;
				let parsedValues = this.parsedValuesPerProperty.get( propertyId );
				if ( parsedValues === undefined ) {
					parsedValues = new Map();
					this.parsedValuesPerProperty.set( propertyId, parsedValues );
				}
				const value = dataValue.value;
				if ( parsedValues.has( value ) ) {
					return;
				}
				const parsedValue = {
					promise: Promise.resolve( dataValue ),
					resolved: dataValue
				};
				parsedValues.set( value, parsedValue );
			};
			for ( const [ , statementList ] of Object.entries( statements ) ) {
				for ( const statement of statementList ) {
					processSnak( statement.mainsnak );
					for ( const [ , qualifierSnaks ] of Object.entries( statement.qualifiers || {} ) ) {
						qualifierSnaks.forEach( processSnak );
					}
					for ( const reference of statement.references || [] ) {
						for ( const [ , referenceSnaks ] of Object.entries( reference.snaks ) ) {
							referenceSnaks.forEach( processSnak );
						}
					}
				}
			}
		},
		/**
		 * Get the parsed value for the given property ID and input,
		 * if it has already been parsed.
		 *
		 * @param {string} propertyId
		 * @param {string} value
		 * @return {object|null|undefined} The parsed value (a data value object
		 * with "type" and "value" keys), null if it could not be parsed,
		 * or undefined if the parse was not yet requested or did not finish yet.
		 */
		peekParsedValue( propertyId, value ) {
			const parsedValues = this.parsedValuesPerProperty.get( propertyId );
			if ( parsedValues === undefined ) {
				return undefined;
			}
			const parsedValue = parsedValues.get( value );
			if ( parsedValue === undefined ) {
				return undefined;
			}
			return parsedValue.resolved;
		}
	}
} );

module.exports = {
	useParsedValueStore
};
