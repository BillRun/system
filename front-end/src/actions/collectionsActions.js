import uuid from 'uuid';
import { collectionStepsSelector } from '@/selectors/settingsSelector';
import {
  saveSettings,
  getSettings,
  actions as settingsActions,
} from './settingsActions';

import { toImmutableList } from '@/common/Util';

/* Collection Step */
const updateCollectionStepByIndex = (index, path, value) => ({
  type: settingsActions.UPDATE_SETTING,
  category: 'collection',
  name: ['steps', index, ...toImmutableList(path)],
  value,
});

const removeCollectionStepByIndex = index => ({
  type: settingsActions.REMOVE_SETTING_FIELD,
  category: 'collection',
  name: ['steps', index],
});

const addCollectionStep = value => ({
  type: settingsActions.PUSH_TO_SETTING,
  category: 'collection',
  path: 'steps',
  value,
});

export const removeCollectionStep = editedItem => (dispatch, getState) => {
  const steps = collectionStepsSelector(getState());
  const index = steps.findIndex(step => step.get('id', '') === editedItem.get('id', ''));
  if (index !== -1) {
    return dispatch(removeCollectionStepByIndex(index));
  }
  return false;
};

export const updateCollectionStep = (item, path, value) => (dispatch, getState) => {
  const steps = collectionStepsSelector(getState());
  const index = steps.findIndex(step => step.get('id', '') === item.get('id', ''));
  if (index !== -1) {
    return dispatch(updateCollectionStepByIndex(index, path, value));
  }
  return false;
};

export const saveCollectionStep = step => (dispatch, getState) => {
  // If step is new, add it
  if (!step.has('id')) {
    return dispatch(addCollectionStep(step.set('id', uuid.v4())));
  }
  // If step is existing, replace step with new step data
  const existingSteps = collectionStepsSelector(getState());
  const index = existingSteps.findIndex(existingStep => existingStep.get('id', '') === step.get('id', ''));
  if (index !== -1) {
    return dispatch(updateCollectionStep(step, [], step));
  }
  return false;
};

/* Collection Steps array */
export const saveCollectionSteps = () => saveSettings(['collection.steps']);

export const getCollectionSteps = () => getSettings(['collection.steps']);

/* Collection Settings */
export const saveCollectionSettings = () => saveSettings(['collection.settings']);

export const getCollectionSettings = () => getSettings(['collection.settings']);

export const updateCollectionSettings = (path, value) => ({
  type: settingsActions.UPDATE_SETTING,
  category: 'collection',
  name: ['settings', ...toImmutableList(path)],
  value,
});
