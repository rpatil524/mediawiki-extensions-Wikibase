import EditFlow from '@/definitions/EditFlow';
import { actions } from '@/store/actions';
import {
	NS_ENTITY,
} from '@/store/namespaces';
import {
	ENTITY_INIT,
} from '@/store/entity/actionTypes';
import {
	BRIDGE_INIT,
} from '@/store/actionTypes';
import {
	PROPERTY_TARGET_SET,
	EDITFLOW_SET,
	APPLICATION_STATUS_SET,
} from '@/store/mutationTypes';
import newMockStore from './newMockStore';
import namespacedStoreEvent from '@/store/namespacedStoreEvent';
import ApplicationStatus from '@/definitions/ApplicationStatus';

describe( 'root/actions', () => {
	describe( BRIDGE_INIT, () => {
		it( `commits to ${EDITFLOW_SET}`, () => {
			const editFlow = EditFlow.OVERWRITE;
			const context = newMockStore( {} );

			const information = {
				editFlow,
				propertyId: '',
				entityId: '',
			};

			return actions[ BRIDGE_INIT ]( context, information ).then( () => {
				expect( context.commit ).toBeCalledWith(
					EDITFLOW_SET,
					editFlow,
				);
			} );
		} );

		it( `commits to ${PROPERTY_TARGET_SET}`, () => {
			const propertyId = 'P42';
			const context = newMockStore( {} );

			const information = {
				editFlow: EditFlow.OVERWRITE,
				propertyId,
				entityId: '',
			};

			return actions[ BRIDGE_INIT ]( context, information ).then( () => {
				expect( context.commit ).toBeCalledWith(
					PROPERTY_TARGET_SET,
					propertyId,
				);
			} );
		} );

		it( `dispatches to ${namespacedStoreEvent( NS_ENTITY, ENTITY_INIT )}$`, () => {
			const entityId = 'Q42';
			const context = newMockStore( {} );

			const information = {
				editFlow: EditFlow.OVERWRITE,
				propertyId: '',
				entityId,
			};

			return actions[ BRIDGE_INIT ]( context, information ).then( () => {
				expect( context.dispatch ).toBeCalledWith(
					namespacedStoreEvent( NS_ENTITY, ENTITY_INIT ),
					{ 'entity': entityId },
				);
			} );
		} );

		describe( 'Applicationstatus', () => {
			const information = {
				editFlow: EditFlow.OVERWRITE,
				propertyId: 'P123',
				entityId: 'Q123',
			};

			it( `commits to ${APPLICATION_STATUS_SET} on successful entity lookup`, () => {
				const context = newMockStore( {} );

				return actions[ BRIDGE_INIT ]( context, information ).then( () => {
					expect( context.commit ).toHaveBeenCalledWith( APPLICATION_STATUS_SET, ApplicationStatus.READY );
				} );
			} );

			it( `commits to ${APPLICATION_STATUS_SET} on fail entity lookup`, () => {
				const context = newMockStore( {
					dispatch: () => Promise.reject(),
				} );

				return actions[ BRIDGE_INIT ]( context, information ).catch( () => {
					expect( context.commit ).toHaveBeenCalledWith( APPLICATION_STATUS_SET, ApplicationStatus.ERROR );
				} );
			} );
		} );
	} );
} );
