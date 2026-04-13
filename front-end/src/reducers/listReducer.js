import Immutable from 'immutable';
import { actions } from '@/actions/listActions';

const defaultState = Immutable.Map();

export default function (state = defaultState, action) {
  const { collection, type } = action;
  switch (type) {
    case actions.GOT_LIST:
      return state.set(collection, Immutable.fromJS(action.list).toList());

    case actions.ADD_TO_LIST: {
      const items = Immutable.fromJS(action.items).toList();
      return state.update(collection, Immutable.List(), list => list.push(...items));
    }

    case actions.REMOVE_FROM_LIST:
      return state.update(collection, Immutable.List(), list => list.delete(action.index));

    case actions.CLEAR_LIST: {
      if (collection) {
        return state.set(collection, Immutable.List());
      }
      return defaultState;
    }

    default:
      return state;
  }
}
