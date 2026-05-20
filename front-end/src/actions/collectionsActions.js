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

const addUiFlagsToConditions = (processes) =>
  processes.map(process =>
    process.updateIn(['account', 'fields'], Immutable.List(), conditions =>
      conditions.map(condition =>
        condition.hasIn(['ui_flags', 'id'])
          ? condition
          : condition.setIn(['ui_flags', 'id'], uuid.v4())
      )
    )
  );

const removeUiFlagsFromConditions = (processes) =>
  processes.map(process =>
    process.updateIn(['account', 'fields'], Immutable.List(), conditions =>
      conditions.map(condition => condition.delete('ui_flags'))
    )
  );

export const getCollections = () => (dispatch, getState) => {
  dispatch(setPageError('collection')); // reset errors
  dispatch(setPageFlag('collection')); // reset flags
  return dispatch(getSettings(['collection'])).then((result) => {
    if (result) {
      const processes = collectionSelector(getState());
      if (processes) {
        const processesWithUiFlags = addUiFlagsToConditions(processes);
        dispatch(updateCollectionAction([], processesWithUiFlags));
      }
    }
    return result;
  });
}

export const updateCollections = (path, value) => (dispatch, getState) => {
  const [ index, ...rest ] = path; // eslint-disable-line no-unused-vars
  const dirtySets = pageFlagSelector(getState(), {}, 'collection', 'dirtySets') || [];
  const setIndex = (typeof index === 'undefined') ? -1 : index;
  dispatch(setPageFlag('collection', 'dirtySets', Immutable.Set([...dirtySets, setIndex]).toList()));  
  dispatch(setPageFlag('collection', 'isFormDirty', true));
  return dispatch(updateCollectionAction(path, value));
};

export const saveCollections = () => (dispatch, getState) => {
  const processes = collectionSelector(getState());
  if (processes) {
    const cleanedProcesses = removeUiFlagsFromConditions(processes);
    dispatch(updateCollectionAction([], cleanedProcesses));
  }
  return dispatch(saveSettings(['collection']))
    .then((res) => {
      if (res && res.status && res.status === 1) {
        return dispatch(getCollections());
      }
      // Restore ui_flags if save failed
      if (processes) {
        dispatch(updateCollectionAction([], processes));
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
