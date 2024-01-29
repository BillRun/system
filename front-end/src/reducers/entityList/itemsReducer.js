import Immutable from 'immutable';
import { actions } from '@/actions/entityListActions';
import { LOGOUT } from '@/actions/userActions';


const defaultState = Immutable.Map();

const itemsReducer = (state = defaultState, action) => {
  switch (action.type) {

    case actions.SET_ITEMS: {
      if (action.collection && action.collection.length > 0) {
        return state.set(action.collection, Immutable.fromJS(action.list).toList());
      }
      return state;
    }

    case actions.CLEAR_ENTITY_LIST:
    case actions.CLEAR_ITEMS: {
      if (action.collection && action.collection.length > 0) {
        return state.set(action.collection, Immutable.List());
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

export default itemsReducer;
