import Immutable from 'immutable';
import { actions } from '@/actions/entityListActions';
import { LOGOUT } from '@/actions/userActions';


const defaultState = Immutable.Map({});

const stateReducer = (state = defaultState, action) => {
  switch (action.type) {

    case actions.CLEAR_ENTITY_LIST: {
      if (action.collection && action.collection.length > 0) {
        return state.delete(action.collection);
      }
      return state;
    }

    case actions.SET_STATE: {
      if (action.collection && action.collection.length > 0) {
        if (action.state.isEmpty()) {
          return state.set(action.collection, Immutable.List([0, 1, 2]));
        }
        return state.set(action.collection, action.state);
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

export default stateReducer;
