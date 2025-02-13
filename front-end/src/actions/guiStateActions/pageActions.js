export const SET_PAGE_TITLE = 'SET_PAGE_TITLE';
export const SYSTEM_REQUIREMENTS_LOADING_COMPLETE = 'SYSTEM_REQUIREMENTS_LOADING_COMPLETE';
export const ONBOARDING_SHOW = 'SHOW_ON_BOARDING';
export const ONBOARDING_TOGGLE = 'TOGGLE_BOARDING';
export const ONBOARDING_SET_STEP = 'SET_ON_BOARDING_STEP';
export const ONBOARDING_SET_STATE = 'SET_ON_BOARDING_STATE';

export const SET_PAGE_FLAG = 'SET_PAGE_FLAG';

export const CONFIRM_SHOW = 'CONFIRM_SHOW';
export const CONFIRM_HIDE = 'CONFIRM_HIDE';

export const EDIT_FORM_SHOW = 'EDIT_FORM_SHOW';
export const EDIT_FORM_HIDE = 'EDIT_FORM_HIDE';
export const EDIT_FORM_SET_ITEM = 'EDIT_FORM_SET_ITEM';
export const EDIT_FORM_SET_ERROR = 'EDIT_FORM_SET_ERROR';
export const EDIT_FORM_UPDATE_ITEM_FIELD = 'EDIT_FORM_UPDATE_ITEM_FIELD';
export const EDIT_FORM_DELETE_ITEM_FIELD = 'EDIT_FORM_DELETE_ITEM_FIELD';

export const onBoardingStates = {
  READY: 'READY',
  RUNNING: 'RUNNING',
  FINISHED: 'FINISHED',
  STARTING: 'STARTING',
  PAUSED: 'PAUSED',
  CANCELED: 'CANCELED',
};

export function setPageTitle(title) {
  return {
    type: SET_PAGE_TITLE,
    title,
  };
}

export function emptyPageTitle() {
  return {
    type: SET_PAGE_TITLE,
  };
}

export function systemRequirementsLoadingComplete() {
  return {
    type: SYSTEM_REQUIREMENTS_LOADING_COMPLETE,
  };
}

export const setOnBoardingStep = (step = 0) => ({
  type: ONBOARDING_SET_STEP,
  step,
});

export const pendingOnBoarding = () => ({
  type: ONBOARDING_SET_STATE,
  state: onBoardingStates.READY,
});

export const pauseOnBoarding = () => ({
  type: ONBOARDING_SET_STATE,
  state: onBoardingStates.PAUSED,
});

export const startOnBoarding = () => ({
  type: ONBOARDING_SET_STATE,
  state: onBoardingStates.STARTING,
});

export const runOnBoarding = () => ({
  type: ONBOARDING_SET_STATE,
  state: onBoardingStates.RUNNING,
});

export const finishOnBoarding = () => ({
  type: ONBOARDING_SET_STATE,
  state: onBoardingStates.FINISHED,
});

export const cancelOnBoarding = () => ({
  type: ONBOARDING_SET_STATE,
  state: onBoardingStates.CANCELED,
});

export const showConfirmModal = confirm => ({
  type: CONFIRM_SHOW,
  confirm,
});

export const hideConfirmModal = () => ({
  type: CONFIRM_HIDE,
});

export const showFormModal = (item, component, config) => ({
  type: EDIT_FORM_SHOW,
  item,
  component,
  config,
});

export const hideFormModal = () => ({
  type: EDIT_FORM_HIDE,
});

export const setFormModalItem = item => ({
  type: EDIT_FORM_SET_ITEM,
  item,
});

export const setFormModalError = (fieldId, message = null) => ({
  type: EDIT_FORM_SET_ERROR,
  fieldId,
  message,
});

export const updateFormModalItemField = (path, value) => ({
  type: EDIT_FORM_UPDATE_ITEM_FIELD,
  path,
  value,
});

export const removeFormModalItemField = path => ({
  type: EDIT_FORM_DELETE_ITEM_FIELD,
  path,
});

export const setPageFlag = (page, flag = null, value = null) => ({
  type: SET_PAGE_FLAG,
  page,
  flag,
  value,
});
