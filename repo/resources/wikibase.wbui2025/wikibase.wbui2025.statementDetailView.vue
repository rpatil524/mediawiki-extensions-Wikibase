<template>
	<!-- TODO: Remove this debugging element T399286 -->
	<div class="wikibase-wbui2025-statement-detail-view">
		<p class="statement_data_debug">
			{{ statementDump }}
		</p>
		<wbui2025-main-snak
			v-if="statement.mainsnak.snaktype === 'value'"
			:main-snak="statement.mainsnak"
		></wbui2025-main-snak>
		<div v-else>
			Unsupported snak type {{ statement.mainsnak.snaktype }}
		</div>
		<wbui2025-qualifiers
			:qualifiers="qualifiers"
			:qualifiers-order="qualifiersOrder">
		</wbui2025-qualifiers>
		<wbui2025-references
			:references="references"
			:show-references="false"
		></wbui2025-references>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const Wbui2025MainSnak = require( './wikibase.wbui2025.mainSnak.vue' );
const Wbui2025References = require( './wikibase.wbui2025.references.vue' );
const Wbui2025Qualifiers = require( './wikibase.wbui2025.qualifiers.vue' );

// @vue/component
module.exports = exports = defineComponent( {
	name: 'WikibaseWbui2025StatementDetail',
	components: {
		Wbui2025MainSnak,
		Wbui2025References,
		Wbui2025Qualifiers
	},
	props: {
		statement: {
			type: Object,
			required: true
		}
	},
	computed: {
		references() {
			return ( this.statement.references ? this.statement.references : [] );
		},
		qualifiers() {
			return ( this.statement.qualifiers ? this.statement.qualifiers : [] );
		},
		qualifiersOrder() {
			return ( this.statement[ 'qualifiers-order' ] ? this.statement[ 'qualifiers-order' ] : [] );
		},
		statementDump() {
			return JSON.stringify( this.statement );
		}
	}
} );
</script>
