import Immutable from 'immutable';
import { PREPARE_MAIN_MENU_STRUCTURE, TOGGLE_SIDE_BAR, prossessMenuTree, combineMenuOverrides } from '@/actions/guiStateActions/menuActions';

const defaultState = Immutable.Map({
  main: Immutable.Map(),
  collapseSideBar: false,
});

const menuReducer = (state = defaultState, action) => {
  switch (action.type) {
    case TOGGLE_SIDE_BAR: {
      if (action.state === null) {
        return state.set('collapseSideBar', !state.get('collapseSideBar', true));
      }
      return state.set('collapseSideBar', action.state);
    }

    case PREPARE_MAIN_MENU_STRUCTURE: {
      const overrides = Immutable.fromJS(action.mainMenuOverrides);
      return state.set('main', prossessMenuTree(combineMenuOverrides(overrides), 'root'));
    }
    default:
      return state;
  }
};

export default menuReducer;
