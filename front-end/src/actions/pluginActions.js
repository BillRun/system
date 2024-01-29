import { List, Map } from 'immutable';
import { startProgressIndicator } from './progressIndicatorActions';
import { getSettings } from '@/actions/settingsActions';
import { validateFieldByType } from '@/actions/entityActions';
import { setFormModalError } from './guiStateActions/pageActions';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import {
  saveSettingsQuery,
} from '../common/ApiQueries';
import {
  formatPluginLabel,
} from '@/common/Util';

const defaultPlugin = Map({
  enabled: true,
  system: true,
  hide_from_ui: false,
  configuration: Map(),
});

export const savePluginByName = (pluginName, messages = {}) => (dispatch, getState) => {
  const { settings } = getState();
  const plugins = settings.get('plugins', List());
  const plugin = plugins.find(plugin => plugin.get('name', '') === pluginName, null, Map());
  return dispatch(savePlugin(plugin, messages));
}

export const savePlugin = (plugin, messages = {}) => (dispatch) => {
  const {
    success: successMessage = `Plugin ${plugin.get('label', '')} was successfuly updated!`,
    error: errorMessage = `Error saving plugin ${plugin.get('label', '')}`,
  } = messages;
  dispatch(startProgressIndicator());
  const convertedPlugin = plugin.deleteIn(['configuration', 'fields']);
  const queries = saveSettingsQuery(convertedPlugin, 'plugin');
  return apiBillRun(queries)
    .then(success => dispatch(apiBillRunSuccessHandler(success, successMessage)))
    .catch(error => dispatch(apiBillRunErrorHandler(error, errorMessage)))
    .finally(() => dispatch(getSettings('plugins')));
}

export const parseGotPlugins = plugins => plugins
  .map(convertPluginToNewStructure)
  .filter(getVisiblePlugins)
  .map(setPluginLabel)
  .map(sortPluginFields);

const convertPluginToNewStructure = (plugin) => {
  if (typeof plugin === 'string') {
    return defaultPlugin.set('name', plugin);
  }
  return plugin;
}

const setPluginLabel = (plugin) => {
  if (plugin.get('label', '') === '') {
    return plugin.set('label', formatPluginLabel(plugin));
  }
  return plugin;
};

const sortPluginFields = plugin => plugin.updateIn(
  ['configuration', 'fields'], List(),
  fields => fields.sortBy(field => field.get('field_name', ''))
);

const getVisiblePlugins = plugin => !plugin.get('hide_from_ui', false);

export const validatePlugin = (plugin) => (dispatch) => {
  const values = plugin.getIn(['configuration', 'values'], Map());
  const configs = plugin.getIn(['configuration', 'fields'], List());
  let isPluginValid = true;
  configs.forEach(config => {
    if (config.get('display', false) && config.get('editable', false)) {
      const path = config.get('field_name', '');
      const path_array = path.split('.').filter(part => part !== '');
      if (values.hasIn(path_array)) {
        const value = values.getIn(path_array)
        const hasError = validateFieldByType(value, config)
        if (hasError !== false) {
          isPluginValid = false;
          dispatch(setFormModalError(path, hasError));
        }
      }
    }
  });
  return isPluginValid;
}