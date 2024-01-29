import moment from 'moment';
import { startProgressIndicator } from './progressIndicatorActions';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import {
  saveSettingsQuery,
  getSettingsQuery,
  saveFileQuery,
  getFileQuery,
  getCurrenciesQuery,
  saveSharedSecretQuery,
  disableSharedSecretQuery,
} from '../common/ApiQueries';


export const actions = {
  UPDATE_SETTING: 'UPDATE_SETTING',
  GOT_SETTINGS: 'GOT_SETTINGS',
  ADD_PAYMENT_GATEWAY: 'ADD_PAYMENT_GATEWAY',
  REMOVE_PAYMENT_GATEWAY: 'REMOVE_PAYMENT_GATEWAY',
  UPDATE_PAYMENT_GATEWAY: 'UPDATE_PAYMENT_GATEWAY',
  REMOVE_SETTING_FIELD: 'REMOVE_SETTING_FIELD',
  PUSH_TO_SETTING: 'PUSH_TO_SETTING',
  SET_FIELD_POSITION: 'SET_FIELD_POSITION',
};


export const addPaymentGateway = gateway => ({
  type: actions.ADD_PAYMENT_GATEWAY,
  gateway,
});

export const removePaymentGateway = gateway => ({
  type: actions.REMOVE_PAYMENT_GATEWAY,
  gateway,
});

export const updatePaymentGateway = gateway => ({
  type: actions.UPDATE_PAYMENT_GATEWAY,
  gateway,
});

export const updateSetting = (category, name, value) => ({
  type: actions.UPDATE_SETTING,
  category,
  name,
  value,
});

export const pushToSetting = (category, value, path = null) => ({
  type: actions.PUSH_TO_SETTING,
  category,
  path,
  value,
});

export const removeSettingField = (category, name) => ({
  type: actions.REMOVE_SETTING_FIELD,
  category,
  name,
});

const gotSettings = settings => ({
  type: actions.GOT_SETTINGS,
  settings,
});

const gotFile = (fileData, path) => {
  const value = `data:image/png;base64,${fileData}`;
  return updateSetting('files', path, value);
};

export const setFieldPosition = (oldIndex, newIndex, path) => ({
  type: actions.SET_FIELD_POSITION,
  oldIndex,
  newIndex,
  path,
});

export const saveFile = (file, metadata = {}) => {
  const query = saveFileQuery(file, metadata);
  return apiBillRun(query);
};

export const fetchFile = (query, path = 'file') => (dispatch) => {
  const dataImage = localStorage.getItem(path);
  if (dataImage) {
    return dispatch(gotFile(dataImage, path));
  }
  const apiQuery = getFileQuery(query);
  return apiBillRun(apiQuery)
    .then((success) => {
      if (success.data && success.data[0] && success.data[0].data && success.data[0].data.desc) {
        localStorage.setItem(path, success.data[0].data.desc);
        dispatch(gotFile(success.data[0].data.desc, path));
        return true;
      }
      return success;
    })
    .catch((error) => {
      dispatch(apiBillRunErrorHandler(error));
      return error;
    });
};

export const saveSettings = (categories = [], messages = {}) => (dispatch, getState) => {
  const {
    success: successMessage = 'Settings saved successfully!',
    error: errorMessage = 'Error saving settings',
  } = messages;
  const ignoreCategories = ['import.mapping']; // disallow to save categories in this function.
  dispatch(startProgressIndicator());
  const { settings } = getState();
  let categoriesToSave = Array.isArray(categories) ? categories : [categories];
  categoriesToSave = categoriesToSave.filter(category => !ignoreCategories.includes(category));
  if (categoriesToSave.length === 0) {
    const warning = {data: [{data: {status: 2, warnings: 'No settings to save'}}]};
    return dispatch(apiBillRunSuccessHandler(warning));
  }
  const multipleCategories = categoriesToSave.length > 1;
  const categoryData = categoriesToSave.map((category) => {
    let data = settings.getIn(category.split('.'));
    if (category === 'taxation') {
      data = data.set('vat', data.get('vat') / 100);
    }
    return (multipleCategories) ? { [category]: data } : data;
  });
  const category = multipleCategories ? 'ROOT' : categoriesToSave[0];
  const data = multipleCategories ? categoryData : categoryData[0];
  const queries = saveSettingsQuery(data, category);

  return apiBillRun(queries)
    .then(success => dispatch(apiBillRunSuccessHandler(success, successMessage)))
    .catch(error => dispatch(apiBillRunErrorHandler(error, errorMessage)));
};

export const getSettings = (categories = [], data = {}) => (dispatch) => {
  const categoriesToSave = Array.isArray(categories) ? categories : [categories];
  const queries = categoriesToSave.map(category => getSettingsQuery(category, data));
  return apiBillRun(queries)
    .then((success) => {
      dispatch(gotSettings(success.data));
      return true;
    })
    .catch((error) => {
      dispatch(apiBillRunErrorHandler(error));
      return false;
    });
};

export const clearAppStorage = (keys = null) => {
  if (keys === null) {
    localStorage.clear();
  } else {
    keys.forEach((key) => {
      localStorage.removeItem(key);
    });
  }
}

export const getCurrencies = () => (dispatch) => {
  const now = moment();
  const cacheForMinutes = 60;
  const cacheKey = 'currencies-options';
  const cache = JSON.parse(localStorage.getItem(cacheKey));
  if (cache && moment(cache.time).add(cacheForMinutes, 'minutes').isAfter(now)) {
    return Promise.resolve(cache.data);
  }
  dispatch(startProgressIndicator());
  const query = getCurrenciesQuery();
  return apiBillRun(query)
    .then((success) => {
      const data = dispatch(apiBillRunSuccessHandler(success));
      localStorage.setItem(cacheKey, JSON.stringify({ time: now, data }));
      return data;
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error retrieving currencies')));
};

export const saveSharedSecret = (secret, mode) => (dispatch) => {
  dispatch(startProgressIndicator());
  const query = (mode === 'remove') ? disableSharedSecretQuery(secret) : saveSharedSecretQuery(secret);
  return apiBillRun(query)
    .then((success) => {
      let action = (['create'].includes(mode)) ? 'created' : '';
      if (action === '') {
        action = (['remove'].includes(mode)) ? 'removed' : 'updated';
      }
      return dispatch(apiBillRunSuccessHandler(success, `The secret key was ${action}`));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error saving secret')));
};
