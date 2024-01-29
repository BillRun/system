import { createSelector } from 'reselect';
import Immutable from 'immutable';
import { onBoardingStates } from '@/actions/guiStateActions/pageActions';


const getOnBoardingState = state => state.guiState.page.getIn(['onBoarding', 'state']);
export const onBoardingStateSelector = createSelector(
  getOnBoardingState,
  state => state,
);

const getOnBoardingStep = state => state.guiState.page.getIn(['onBoarding', 'step']);
export const onBoardingStepSelector = createSelector(
  getOnBoardingStep,
  step => step,
);

export const onBoardingIsRunnigSelector = createSelector(
  onBoardingStateSelector,
  state => state === onBoardingStates.RUNNING,
);

export const onBoardingIsFinishedSelector = createSelector(
  onBoardingStateSelector,
  state => state === onBoardingStates.FINISHED,
);

export const onBoardingIsReadySelector = createSelector(
  onBoardingStateSelector,
  state => state === onBoardingStates.READY,
);

export const onBoardingIsPausedSelector = createSelector(
  onBoardingStateSelector,
  state => state === onBoardingStates.PAUSED,
);

export const onBoardingIsStartingSelector = createSelector(
  onBoardingStateSelector,
  state => state === onBoardingStates.STARTING,
);

const getFormModalItem = state => state.guiState.page.getIn(['formModalData', 'item']);
export const formModalItemSelector = createSelector(
  getFormModalItem,
  item => item,
);
const getFormModalComponent = state => state.guiState.page.getIn(['formModalData', 'component']);
export const formModalComponentSelector = createSelector(
  getFormModalComponent,
  component => component,
);
const getFormModalConfig = state => state.guiState.page.getIn(['formModalData', 'config']);
export const formModalConfigSelector = createSelector(
  getFormModalConfig,
  config => config,
);

const getFormModalErrors = state => state.guiState.page.getIn(['formModalData', 'errors']);
export const formModalErrosSelector = createSelector(
  getFormModalErrors,
  errors => errors,
);

const getFormModalShowState = state => state.guiState.page.getIn(['formModalData', 'show']);
export const formModalShowStateSelector = createSelector(
  getFormModalShowState,
  show => show,
);

const getConfirm = state => state.guiState.page.getIn(['confirm']);
export const confirmSelector = createSelector(
  getConfirm,
  confirm => confirm,
);

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
