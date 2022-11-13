import Immutable from 'immutable';
import isNumber from 'is-number';
import { actions } from '@/actions/entityListActions';
import { LOGOUT } from '@/actions/userActions';


const defaultState = Immutable.Map();

const sizeReducer = (state = defaultState, action) => {
  switch (action.type) {

    case actions.CLEAR_ENTITY_LIST: {
      if (action.collection && action.collection.length > 0) {
        return state.delete(action.collection);
      }
      return state;
    }

    case actions.SET_SIZE: {
      if (action.collection && action.collection.length > 0 && isNumber(action.size)) {
        return state.set(action.collection, parseInt(action.size));
      }
      return state;
    }

    case LOGOUT: {
      return defaultState;
    }

    default:
      return state;
  }
};

export default sizeReducer;
