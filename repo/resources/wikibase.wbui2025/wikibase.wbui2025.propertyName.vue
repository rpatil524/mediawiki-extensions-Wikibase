<template>
	<div class="wikibase-wbui2025-property-name">
		<p
			class="wikibase-wbui2025-property-name-link"
			:data-property-id="propertyId"
			v-html="propertyLinkHtml"
		></p>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { propertyLinkHtml } = require( './store/serverRenderedHtml.js' );

// @vue/component
module.exports = exports = defineComponent( {
	name: 'WikibaseWbui2025PropertyName',
	props: {
		propertyId: {
			type: String,
			required: true
		}
	},
	computed: {
		propertyLinkHtml() {
			return propertyLinkHtml( this.propertyId );
		}
	}
} );
</script>
