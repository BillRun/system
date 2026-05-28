import Immutable from 'immutable';
import { actions } from '@/actions/entityListActions';
import { LOGOUT } from '@/actions/userActions';


const defaultState = Immutable.Map();

const nextPageReducer = (state = defaultState, action) => {
  switch (action.type) {

    case actions.CLEAR_NEXT_PAGE:
    case actions.CLEAR_ENTITY_LIST: {
      if (action.collection && action.collection.length > 0) {
        return state.delete(action.collection);
      }
      return state;
    }

    case actions.SET_NEXT_PAGE: {
      if (action.collection && action.collection.length > 0) {
        return state.set(action.collection, action.nextPage);
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

export default nextPageReducer;
