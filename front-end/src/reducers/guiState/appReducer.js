import Immutable from 'immutable';
import {
  SET_APP_DATA,
} from '@/actions/guiStateActions/appActions';


const defaultState = Immutable.Map();

const appReducer = (state = defaultState, action) => {
  switch (action.type) {

    case SET_APP_DATA: {
      const { key = null, data = null } = action;
      if (data === null) {
        return state.delete(key);
      }
      return state.set(key, data);
    }

    default:
      return state;
  }
};

export default appReducer;
