import Immutable from 'immutable';
import { getConfig } from '@/common/Util';
import includeGroupsReducer from './includeGroupsReducer';
import { ADD_GROUP, REMOVE_GROUP } from '@/actions/includeGroupsActions';
import {
  SERVICE_PRODUCTS_REMOVE,
  SERVICE_PRODUCTS_RATE_UPDATE_TO,
  SERVICE_PRODUCTS_RATE_UPDATE,
  SERVICE_PRODUCTS_RATE_REMOVE,
  SERVICE_PRODUCTS_RATE_ADD,
  SERVICE_PRODUCTS_RATE_INIT,
  GOT_SERVICE,
  CLONE_RESET_SERVICE,
  CLEAR_SERVICE,
  UPDATE_SERVICE,
  ADD_GROUP_SERVICE,
  REMOVE_GROUP_SERVICE,
  DELETE_SERVICE_FIELD,
} from '@/actions/serviceActions';
import entityProductsReducer from './entityProductsReducer';

const DefaultState = Immutable.fromJS({
  description: '',
  name: '',
  price: [{
    from: 0,
    to: getConfig('serviceCycleUnlimitedValue', 'UNLIMITED'),
    price: '',
  }],
});

const serviceReducer = (state = DefaultState, action) => {
  switch (action.type) {
    case CLEAR_SERVICE:
      return DefaultState;

    case GOT_SERVICE:
      return Immutable.fromJS(action.item);

    case UPDATE_SERVICE:
      return state.setIn(action.path, action.value);

    case CLONE_RESET_SERVICE: {
      const keysToDeleteOnClone = ['_id', 'from', 'to', 'originalValue', ...action.uniquefields];
      return state.withMutations((itemWithMutations) => {
        keysToDeleteOnClone.forEach((keyToDelete) => {
          itemWithMutations.delete(keyToDelete);
        });
      });
    }

    case ADD_GROUP_SERVICE: {
      const includeGroupsAction = Object.assign({}, action, { type: ADD_GROUP });
      return includeGroupsReducer(state, includeGroupsAction);
    }

    case REMOVE_GROUP_SERVICE: {
      const includeGroupsAction = Object.assign({}, action, { type: REMOVE_GROUP });
      return includeGroupsReducer(state, includeGroupsAction);
    }

    case SERVICE_PRODUCTS_REMOVE:
    case SERVICE_PRODUCTS_RATE_UPDATE_TO:
    case SERVICE_PRODUCTS_RATE_UPDATE:
    case SERVICE_PRODUCTS_RATE_REMOVE:
    case SERVICE_PRODUCTS_RATE_ADD:
    case SERVICE_PRODUCTS_RATE_INIT:
      return entityProductsReducer(state, action);

    case DELETE_SERVICE_FIELD: {
      const { path } = action;
      const arrayPath = Array.isArray(path) ? path : [path];
      return state.deleteIn(arrayPath);
    }

    default:
      return state;
  }
};

export default serviceReducer;
