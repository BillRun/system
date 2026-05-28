import Immutable from 'immutable';

import { actions } from '@/actions/listActions';

const defaultState = Immutable.Map({nextPage: true});

const pager = (state = defaultState, action) => {
  switch (action.type) {
    case actions.SET_NEXT_PAGE:
      return state.set('nextPage', action.nextPage)
    default:
      return state;
  }
};

export default pager;
