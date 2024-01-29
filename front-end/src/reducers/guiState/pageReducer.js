import Immutable from 'immutable';
import {
  SET_PAGE_TITLE,
  SYSTEM_REQUIREMENTS_LOADING_COMPLETE,
  ONBOARDING_SET_STEP,
  ONBOARDING_SET_STATE,
  CONFIRM_SHOW,
  CONFIRM_HIDE,
  EDIT_FORM_SHOW,
  EDIT_FORM_HIDE,
  EDIT_FORM_SET_ITEM,
  EDIT_FORM_SET_ERROR,
  EDIT_FORM_UPDATE_ITEM_FIELD,
  EDIT_FORM_DELETE_ITEM_FIELD,
  SET_PAGE_FLAG,
  onBoardingStates,
} from '@/actions/guiStateActions/pageActions';
import { LOGIN } from '@/actions/userActions';


const defaultState = Immutable.Map({
  title: ' ',
  systemRequirementsLoad: false,
  onBoarding: Immutable.Map({
    step: 0,
    state: onBoardingStates.READY,
  }),
  confirm: Immutable.Map({}),
});

const pageReducer = (state = defaultState, action) => {
  switch (action.type) {
    case SET_PAGE_TITLE: {
      const newTitle = typeof action.title !== 'undefined' ? action.title : defaultState.get('title');
      return state.set('title', newTitle);
    }

    case SYSTEM_REQUIREMENTS_LOADING_COMPLETE: {
      return state.set('systemRequirementsLoad', true);
    }

    case ONBOARDING_SET_STEP: {
      return state.setIn(['onBoarding', 'step'], action.step);
    }

    case ONBOARDING_SET_STATE: {
      return state.setIn(['onBoarding', 'state'], action.state);
    }

    case LOGIN: {
      if (action.data && action.data.last_login === null) {
        return state.setIn(['onBoarding', 'state'], onBoardingStates.STARTING);
      }
      return state;
    }

    case CONFIRM_SHOW: {
      return state.setIn(['confirm'], Immutable.Map({ ...action.confirm, show: true }));
    }

    case CONFIRM_HIDE: {
      return state.setIn(['confirm'], Immutable.Map());
    }

    case EDIT_FORM_SHOW: {
      const { item, component, config } = action;
      return state.setIn(['formModalData'], Immutable.Map({
        item,
        component,
        config: Immutable.fromJS(config),
        show: true,
      }));
    }

    case EDIT_FORM_HIDE: {
      return state.setIn(['formModalData'], Immutable.Map());
    }

    case EDIT_FORM_SET_ITEM: {
      return state.setIn(['formModalData', 'item'], action.item);
    }

    case EDIT_FORM_SET_ERROR: {
      const { fieldId = null, message = null } = action;
      if (fieldId === null) {
        return state.deleteIn(['formModalData', 'errors']);
      }
      if (message === null) {
        return state.deleteIn(['formModalData', 'errors', fieldId]);
      }
      return state.setIn(['formModalData', 'errors', fieldId], message);
    }

    case EDIT_FORM_UPDATE_ITEM_FIELD: {
      const { path, value } = action;
      const arrayPath = Array.isArray(path) ? path : [path];
      return state.setIn(['formModalData', 'item', ...arrayPath], value);
    }

    case EDIT_FORM_DELETE_ITEM_FIELD: {
      const { path } = action;
      const arrayPath = Array.isArray(path) ? path : [path];
      return state.deleteIn(['formModalData', 'item', ...arrayPath]);
    }

    case SET_PAGE_FLAG: {
      const { page, flag, value } = action;
      if (flag === null) {
        return state.deleteIn(['flag', page]);
      }
      const arrayPath = Array.isArray(flag) ? flag : [flag];
      if (value === null) {
        return state.deleteIn(['flag', page, ...arrayPath]);
      }
      return state.setIn(['flag', page, ...arrayPath], value);
    }

    default:
      return state;
  }
};

export default pageReducer;
