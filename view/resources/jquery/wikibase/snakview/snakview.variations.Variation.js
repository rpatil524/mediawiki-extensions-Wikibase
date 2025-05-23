/**
 * @license GPL-2.0-or-later
 * @author Daniel Werner < daniel.a.r.werner@gmail.com >
 */
( function () {
	'use strict';
	var ViewState = require( './snakview.ViewState.js' );

	/**
	 * Abstract base for all kinds of `Variation`s to be used by `jQuery.wikibase.snakview` to
	 * represent the different types of `datamodel.Snak` objects.
	 *
	 * @see datamodel.Snak
	 * @abstract
	 */
	var SELF = class WbSnakviewVariationsVariation {
		/**
		 * @param {ViewState} viewState Interface that allows retrieving
		 *        information from the related `snakview` instance as well as updating the `snakview`
		 *        instance.
		 * @param {jQuery} $viewPort A DOM node which serves as drawing surface for the `Variation`'s
		 *        output. This is where the `Variation` instance expresses its current state and/or
		 *        displays input elements for user interaction.
		 * @param {PropertyDataTypeStore} propertyDataTypeStore
		 * @param {wikibase.ValueViewBuilder} valueViewBuilder Enables the `Variation` to have
		 *        `jQuery.valueview` instances created according to particular `wikibase.dataTypes.DataType` /
		 *        `dataValues.DataValue` objects.
		 * @param {wikibase.dataTypes.DataTypeStore} dataTypeStore Enables the `Variation` to retrieve a
		 *        `wikibase.dataTypes.DataType` instance for a particular `DataType` ID.
		 *
		 * @throws {Error} if a required parameter is not specified properly.
		 */
		constructor(
			viewState,
			$viewPort,
			propertyDataTypeStore,
			valueViewBuilder,
			dataTypeStore
		) {
			if ( !( viewState instanceof ViewState ) ) {
				throw new Error( 'No ViewState object was provided to the snakview variation' );
			}
			if ( !( $viewPort instanceof $ ) || $viewPort.length !== 1 ) {
				throw new Error( 'No sufficient DOM node provided for the snakview variation' );
			}

			/**
			 * @property {wikibase.ValueViewBuilder}
			 */
			this._valueViewBuilder = valueViewBuilder;
			/**
			 * @property {ViewState}
			 */
			this._viewState = viewState;
			/**
			 * @property {wikibase.dataTypes.DataTypeStore}
			 */
			this._dataTypeStore = dataTypeStore;
			/**
			 * @property {PropertyDataTypeStore}
			 */
			this._propertyDataTypeStore = propertyDataTypeStore;

			/**
			 * The DOM node displaying the `Variation`'s current state and/or input elements for user
			 * interaction during the `snakview`'s edit mode. The node's content has to be updated by
			 * the `draw()` function.
			 *
			 * @property {jQuery}
			 * @protected
			 */
			this.$viewPort = $viewPort;

			this.setupVariation();
			this.$viewPort.addClass( this.variationBaseClass );

			this._init();
		}

		/**
		 * This method should be overriden by the subclasses. We keep the definition here
		 * so that we can add doctypes to the fields without requiring Javascript field definitions.
		 *
		 * @abstract
		 */
		setupVariation() {
			/**
			 * A unique class for this `Variation`, applied to the `Variation` DOM's `class` attribute.
			 * Will be set by the `Variation` factory when creating a new `Variation` definition.
			 *
			 * @property {string|null}
			 * @readonly
			 */
			this.variationBaseClass = null;

			/**
			 * The constructor of the `Snak` the `Variation` is for. Will be set by the `Variation`
			 * factory when creating a new `Variation` definition.
			 *
			 * @property {datamodel.Snak|null}
			 * @readonly
			 */
			this.variationSnakConstructor = null;
			util.abstractMember();
		}

		/**
		 * @protected
		 */
		_init() {
			this._viewState.notify( 'valid' );
		}

		/**
		 * Destroys the `Variation`.
		 */
		destroy() {
			this.$viewPort.removeClass( this.variationBaseClass );
			this.$viewPort = null;
			this._viewState = null;
		}

		/**
		 * @protected
		 *
		 * @return {boolean}
		 */
		isDestroyed() {
			return !this._viewState;
		}

		/**
		 * Returns an object that offers information about the related `snakview`'s current state as
		 * well as allows updating the `snakview` instance.
		 *
		 * @see jQuery.wikibase.snakview
		 *
		 * @return {ViewState|null} Null when called after the object got
		 *  destroyed.
		 */
		viewState() {
			return this._viewState;
		}

		/**
		 * Sets/Gets the value of the `Variation`'s part of the `Snak` by accepting/returning an
		 * incomplete `Snak` serialization containing the parts of the `Snak` specific to the `Snak`
		 * bound to the `Variation`. Equivalent to what
		 * `wikibase.serialization.SnakSerializer.serialize()` returns, just without the fields
		 * `snaktype` and `property`.
		 *
		 * @see wikibase.serialization.SnakSerializer
		 *
		 * @param {Object} [value]
		 * @return {Object|undefined} Incomplete `Snak` serialization containing the parts of the
		 *         `Snak` specific to the `Snak` bound to the `Variation`. Equivalent to what
		 *         `wikibase.serialization.SnakSerializer.serialize()` returns, just without the
		 *         fields `snaktype` and `property`.
		 */
		value( value ) {
			if ( value === undefined ) {
				return this._getValue();
			}
			this._setValue( value );
		}

		/**
		 * Sets the `Variation`s value by being passed an incomplete `Snak` serialization containing
		 * the parts of the `Snak` specific to the `Snak` type bound to the `Variation`. Equivalent
		 * to what `wikibase.serialization.SnakSerializer.serialize()` returns, just without the
		 * fields `snaktype` and `property`. These fields may be received per
		 * `viewState().property()` and `viewState().snakType()`, if necessary. A missing field
		 * implies that the aspect of the `Snak` was not defined yet. Then, the view should display
		 * a useful message or, in edit-mode, show empty input forms for user interaction.
		 *
		 * @protected
		 *
		 * @param {Object} value Incomplete `Snak` serialization.
		 */
		_setValue( value ) {}

		/**
		 * Gets the `Variation`s value returning an incomplete `Snak` serialization containing the
		 * parts of the `Snak` specific to the `Snak` type bound to the `Variation`. Equivalent to
		 * what `wikibase.serialization.SnakSerializer.serialize()` returns, just without the fields
		 * `snaktype` and `property`. Attributes of the `Snak` not defined yet, should be omitted
		 * from the returned incomplete serialization.
		 *
		 * @return {Object} Incomplete `Snak` serialization.
		 */
		_getValue() {
			return {};
		}

		/**
		 * Start the `Variation`'s edit mode.
		 */
		startEditing() {
			$( this ).triggerHandler( 'afterstartediting' );
		}

		/**
		 * Stops the `Variation`'s edit mode.
		 *
		 * @param {boolean} dropValue
		 */
		stopEditing( dropValue ) {}

		disable() {}

		enable() {}

		/**
		 * @return {boolean}
		 */
		isFocusable() {
			return false;
		}

		/**
		 * Sets the focus on the `Variation`.
		 */
		focus() {}

		/**
		 * Removes focus from the `Variation`.
		 */
		blur() {}
	};

	/**
	 * Updates the `Variation` view port's content.
	 *
	 * @abstract
	 */
	SELF.prototype.draw = util.abstractMember;

	module.exports = SELF;

}() );
