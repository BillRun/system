import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '@/common/Api';
import { getExportGeneratorActionQuery, saveExportGeneratorQuery } from '@/common/ApiQueries';
import { startProgressIndicator } from './progressIndicatorActions';
import { getSettings } from '@/actions/settingsActions';


export const CLEAR_EXPORT_GENERATOR = 'CLEAR_EXPORT_GENERATOR';
export const GOT_EXPORT_GENERATOR = 'GOT_EXPORT_GENERATOR';
export const UPDATE_EXPORT_GENERATOR_VALUE = 'UPDATE_EXPORT_GENERATOR_VALUE';
export const REMOVE_EXPORT_GENERATOR_VALUE = 'REMOVE_EXPORT_GENERATOR_VALUE';


const gotExportGenerator = generator => ({
  type: GOT_EXPORT_GENERATOR,
  generator,
});

export const updateExportGeneratorValue = (path, value) => ({
  type: UPDATE_EXPORT_GENERATOR_VALUE,
  path,
  value
});

export const removeExportGeneratorValue = (path) => ({
  type: REMOVE_EXPORT_GENERATOR_VALUE,
  path,
});

export const clearExportGenerator = () => ({
  type: CLEAR_EXPORT_GENERATOR
});

export const fetchExportGenerators = () => dispatch => 
  dispatch(getSettings("export_generators"));

export const deleteExportGenerator = name => (dispatch) => {
  const query = getExportGeneratorActionQuery(name, 'unset');
  dispatch(startProgressIndicator());
  return apiBillRun(query)
    .then(success => {
      dispatch(fetchExportGenerators());
      return dispatch(apiBillRunSuccessHandler(success));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, `Error occurred while trying to delete export generator ${name}`)));
};

export const updateExportGeneratorStatus = (name, status) => (dispatch) => {
  const action = (status ? 'enable' : 'disable');
  const query = getExportGeneratorActionQuery(name, action);
  dispatch(startProgressIndicator());
  return apiBillRun(query)
    .then(success => {
      dispatch(fetchExportGenerators());
      return dispatch(apiBillRunSuccessHandler(success));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, `Error occurred while trying to ${action} export generator ${name}`)));
};

export const getExportGenerator = name => (dispatch) => {
  const query = getExportGeneratorActionQuery(name, 'get');
  dispatch(startProgressIndicator());
  return apiBillRun(query)
    .then(success => {
      dispatch(gotExportGenerator(success.data[0].data.details));
      return dispatch(apiBillRunSuccessHandler(success));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, `Error loading export generator ${name}`)));
};

export const saveExportGenerator = (generator) => {
  const query = saveExportGeneratorQuery(generator);
  return dispatch => {
    dispatch(startProgressIndicator());
    return apiBillRun(query).then(
      success => dispatch(apiBillRunSuccessHandler(success, 'Export generator saved successfully')),
    ).catch(error => dispatch(apiBillRunErrorHandler(error)));
  };
}