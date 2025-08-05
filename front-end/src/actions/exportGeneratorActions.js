import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '@/common/Api';
import { getDeleteEntityByIdQuery, getUpdateEntityByIdQuery, getEntityByFieldQuery, getCreateEntityQuery } from '@/common/ApiQueries';
import { startProgressIndicator } from './progressIndicatorActions';
import { getSettings } from '@/actions/settingsActions';
import { fromJS } from 'immutable';
import { clearItems } from '@/actions/entityListActions';


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

export const deleteExportGenerator = (id) => (dispatch) => {
  const query = getDeleteEntityByIdQuery('export_generators', id);
  dispatch(startProgressIndicator());
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Export generator deleted successfully')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, `Error occurred while trying to delete export generator`)));
};

export const updateExportGeneratorStatus = (id, status) => (dispatch) => {
  const data = fromJS({ enabled: status });
  const query = getUpdateEntityByIdQuery('export_generators', id, data);
  dispatch(startProgressIndicator());
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, `Export generator ${status ? 'enabled' : 'disabled'}`)))
    .catch(error => dispatch(apiBillRunErrorHandler(error, `Error occurred while trying to update status`)));
};

export const getExportGenerator = (name) => (dispatch) => {
  const query = getEntityByFieldQuery('export_generators', 'name', name);
  dispatch(startProgressIndicator());
  return apiBillRun(query)
    .then(success => {
      const generatorData = success.data[0].data.details[0] || {};
      dispatch(gotExportGenerator(generatorData));
      return dispatch(apiBillRunSuccessHandler(success));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, `Error loading export generator ${name}`)));
};

export const saveExportGenerator = (generator) => (dispatch) => {
  const isUpdate = generator.has('_id');

  let query;
  if (isUpdate) {
    const id = generator.getIn(['_id', '$id']);
    const dataToUpdate = generator.delete('_id');
    query = getUpdateEntityByIdQuery('export_generators', id, dataToUpdate);
  } else {
    query = getCreateEntityQuery('export_generators', generator);
  }

  dispatch(startProgressIndicator());
  return apiBillRun(query).then(
    (success) => {
      dispatch(clearItems('export generators'));
      return dispatch(apiBillRunSuccessHandler(success, 'Export generator saved successfully'));
    },
    success => dispatch(apiBillRunSuccessHandler(success, 'Export generator saved successfully')),
  ).catch(error => dispatch(apiBillRunErrorHandler(error)));
};