import Immutable from 'immutable';
import { toImmutableList } from '@/common/Util';
import { actions } from '@/actions/entityListActions';
import { LOGOUT } from '@/actions/userActions';


const defaultState = Immutable.Map();

const revisionsReducer = (state = defaultState, action) => {
  switch (action.type) {

    case actions.CLEAR_REVISIONS: {
      const { collection = null, key = null } = action;
      if (collection && key) {
        const keys = toImmutableList(key).join('_');
        return state.deleteIn([collection, keys]);
      }
      if (collection && !key) {
        return state.delete(action.collection);
      }
      return state;
    }

    case actions.SET_REVISIONS: {
      const { collection = null, key = null, revisions = [] } = action;
      const items = Immutable.fromJS(revisions).toList();
      if (collection && key) {
        const keys = toImmutableList(key).join('_');
        return state.setIn([collection, keys], items);
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

export default revisionsReducer;
