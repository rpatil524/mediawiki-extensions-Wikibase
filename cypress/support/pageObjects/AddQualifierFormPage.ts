import Chainable = Cypress.Chainable;

export class AddQualifierFormPage {
	public static SELECTORS = {
		HEADING: '.wikibase-wbui2025-add-qualifier-heading h2',
		PROPERTY_INPUT: '.wikibase-wbui2025-property-lookup input',
		SNAK_VALUE_INPUT: '.wikibase-wbui2025-add-qualifier-value input',
		SNAK_VALUE_LOOKUP: '.wikibase-wbui2025-add-qualifier-value.cdx-lookup',
		SNAK_VALUE_TEXT_INPUT: '.wikibase-wbui2025-add-qualifier-value.cdx-text-input',
		SNAK_VALUE_LOOKUP_INPUT: '.wikibase-wbui2025-add-qualifier-value .cdx-lookup__input input',
		SNAK_VALUE_MENU_ITEMS: '.wikibase-wbui2025-add-qualifier-value .cdx-menu-item',
		MENU: '.cdx-menu',
		ADD_BUTTON: '.wikibase-wbui2025-add-qualifier-form .cdx-button',
	};

	public heading(): Chainable {
		return cy.get( AddQualifierFormPage.SELECTORS.HEADING );
	}

	public propertyInput(): Chainable {
		return cy.get( AddQualifierFormPage.SELECTORS.PROPERTY_INPUT );
	}

	public snakValueInput(): Chainable {
		return cy.get( AddQualifierFormPage.SELECTORS.SNAK_VALUE_INPUT );
	}

	public snakValueLookup(): Chainable {
		return cy.get( AddQualifierFormPage.SELECTORS.SNAK_VALUE_LOOKUP );
	}

	public snakValueTextInput(): Chainable {
		return cy.get( AddQualifierFormPage.SELECTORS.SNAK_VALUE_TEXT_INPUT );
	}

	public snakValueLookupInput(): Chainable {
		return cy.get( AddQualifierFormPage.SELECTORS.SNAK_VALUE_LOOKUP_INPUT );
	}

	public snakValueMenuItems(): Chainable {
		return cy.get( AddQualifierFormPage.SELECTORS.SNAK_VALUE_MENU_ITEMS );
	}

	public menu(): Chainable {
		return cy.get( AddQualifierFormPage.SELECTORS.MENU );
	}

	public addButton(): Chainable {
		return cy.get( AddQualifierFormPage.SELECTORS.ADD_BUTTON );
	}

	public setProperty( searchTerm: string ): this {
		this.propertyInput().type( searchTerm );
		cy.get( '.wikibase-wbui2025-property-lookup .cdx-menu-item:first' ).click();
		return this;
	}

	public setSnakValue( inputText: string ): this {
		this.snakValueInput().type( inputText );
		return this;
	}
}
