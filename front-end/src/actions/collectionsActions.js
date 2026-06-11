import uuid from 'uuid';
import { collectionSelector } from '@/selectors/settingsSelector';
import {
  saveSettings,
  getSettings,
  actions as settingsActions,
} from './settingsActions';
import { pageFlagSelector } from '@/selectors/guiSelectors';
import {
  setPageFlag,
  setPageError,
} from './guiStateActions/pageActions.js';
import Immutable from 'immutable';


const updateCollectionAction = (path, value) => ({
  type: settingsActions.UPDATE_SETTING,
  category: 'collection',
  name: ['processes', ...path],
  value,
});

export const getCollections = () => (dispatch) => {
  dispatch(setPageError('collection')); // reset errors
  dispatch(setPageFlag('collection')); // reset flags
  return dispatch(getSettings(['collection']));
}

export const updateCollections = (path, value) => (dispatch, getState) => {
  const [ index, ...rest ] = path; // eslint-disable-line no-unused-vars
  const dirtySets = pageFlagSelector(getState(), {}, 'collection', 'dirtySets') || [];
  const setIndex = (typeof index === 'undefined') ? -1 : index;
  dispatch(setPageFlag('collection', 'dirtySets', Immutable.Set([...dirtySets, setIndex]).toList()));  
  dispatch(setPageFlag('collection', 'isFormDirty', true));
  return dispatch(updateCollectionAction(path, value));
};

export const saveCollections = () => (dispatch) => {
  return dispatch(saveSettings(['collection']))
    .then((res) => {
      if (res && res.status && res.status === 1) {
        return dispatch(getCollections());
      }
      return res;
    });
}

export const updateCollectionStep = (index, step) => (dispatch, getState) => {
  const dirtySets = pageFlagSelector(getState(), {}, 'collection', 'dirtySets') || [];
  dispatch(setPageFlag('collection', 'dirtySets', Immutable.Set([...dirtySets, index]).toList()));
  dispatch(setPageFlag('collection', 'isFormDirty', true));
  const processes = collectionSelector(getState());
  const steps = processes.getIn([index, 'steps'], Immutable.List());

  // If step is new, add it
  if (!step.has('id')) {
      const newSteps = steps.push(step.set('id', uuid.v4()));
      return dispatch(updateCollectionAction([index, 'steps'], newSteps));
  }
  // If step is existing, replace step with new step data
  const existingStepIndex = steps.findIndex(existingStep => existingStep.get('id', '') === step.get('id', ''));
  if (existingStepIndex !== -1) {
    return dispatch(updateCollectionAction([index, 'steps', existingStepIndex], step));
  }
  return false;
};

export const removeCollectionStep = (index, step) => (dispatch, getState) => {
  const dirtySets = pageFlagSelector(getState(), {}, 'collection', 'dirtySets') || [];
  dispatch(setPageFlag('collection', 'dirtySets', Immutable.Set([...dirtySets, index]).toList()));
  dispatch(setPageFlag('collection', 'isFormDirty', true));
  const processes = collectionSelector(getState());
  const steps = processes.getIn([index, 'steps'], Immutable.List());
  if (step.has('id')) {
    const existingStepIndex = steps.findIndex(existingStep => existingStep.get('id', '') === step.get('id', ''));
    if (existingStepIndex !== -1) {
      return dispatch(updateCollectionAction([index, 'steps'], steps.delete(existingStepIndex)));
    }
  }
  return false;
};
