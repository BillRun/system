import { createSelector } from 'reselect';
import Immutable from 'immutable';
import { onBoardingStates } from '@/actions/guiStateActions/pageActions';


// Plain input selectors — no createSelector wrapper needed for identity transforms
const getOnBoardingState = state => state.guiState.page.getIn(['onBoarding', 'state']);
export const onBoardingStateSelector = getOnBoardingState;

const getOnBoardingStep = state => state.guiState.page.getIn(['onBoarding', 'step']);
export const onBoardingStepSelector = getOnBoardingStep;

export const onBoardingIsRunnigSelector = createSelector(
  getOnBoardingState,
  state => state === onBoardingStates.RUNNING,
);

export const onBoardingIsFinishedSelector = createSelector(
  getOnBoardingState,
  state => state === onBoardingStates.FINISHED,
);

export const onBoardingIsReadySelector = createSelector(
  getOnBoardingState,
  state => state === onBoardingStates.READY,
);

export const onBoardingIsPausedSelector = createSelector(
  getOnBoardingState,
  state => state === onBoardingStates.PAUSED,
);

export const onBoardingIsStartingSelector = createSelector(
  getOnBoardingState,
  state => state === onBoardingStates.STARTING,
);

const getFormModalItem = state => state.guiState.page.getIn(['formModalData', 'item']);
export const formModalItemSelector = getFormModalItem;

const getFormModalComponent = state => state.guiState.page.getIn(['formModalData', 'component']);
export const formModalComponentSelector = getFormModalComponent;

const getFormModalConfig = state => state.guiState.page.getIn(['formModalData', 'config']);
export const formModalConfigSelector = getFormModalConfig;

const getFormModalErrors = state => state.guiState.page.getIn(['formModalData', 'errors']);
export const formModalErrosSelector = getFormModalErrors;

export const getPageErrors = createSelector(
  getFormModalErrors,
  (state, props, page = '') => page,
  (allErrors, page) => allErrors ? allErrors.get(page) : undefined,
);

const getFormModalShowState = state => state.guiState.page.getIn(['formModalData', 'show']);
export const formModalShowStateSelector = getFormModalShowState;

const getConfirm = state => state.guiState.page.getIn(['confirm']);
export const confirmSelector = getConfirm;

const getMainMenu = state => state.guiState.menu.getIn(['main']);
export const permissionsSelector = createSelector(
  getMainMenu,
  (mainMenu = Immutable.Map()) => mainMenu
    .reduce((acc, menuItem) => {
      if (!menuItem.get('subMenus', Immutable.List()).isEmpty()) {
        return acc.push(...menuItem.get('subMenus', Immutable.List()))
      }
      return acc;
    }, mainMenu)
    .reduce((acc, menuItem) => {
      if (menuItem.get('route', '') !== '') {
        const routePermission = Immutable.Map({
          'view':  menuItem.get('roles', Immutable.List())
        });
        return acc.set(menuItem.get('route', '-'), routePermission)
      }
      return acc;
    }, Immutable.Map())
);

const getPagesFlags = state => state.guiState.page.get('flag');
export const pageFlagSelector = createSelector(
  getPagesFlags,
  (state, props, page) => page,
  (state, props, page, flag) => flag,
  (flags = Immutable.Map(), page = '', flag = null) => {
    const path = (flag !== null) ? [page, flag] : [page];
    return flags.getIn(path, undefined);
  },
);
