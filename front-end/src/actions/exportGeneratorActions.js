import Immutable from 'immutable';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '@/common/Api';
import { getEntitesQuery } from '@/common/ApiQueries';
import { startProgressIndicator } from './progressIndicatorActions';
import { saveEntity, deleteEntity } from '@/actions/entityActions';
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

export const deleteExportGenerator = item => dispatch =>
  dispatch(deleteEntity('export_generators', item));

export const updateExportGeneratorStatus = (item, status) => dispatch =>
  dispatch(saveEntity('export_generators', Immutable.Map({ _id: item.get('_id'), enabled: status }), 'update'));

export const getExportGenerator = (name) => (dispatch) => {
  const query = getEntitesQuery('export_generators', {}, { name: { $in: [name] } });
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
  const action = generator.has('_id') ? 'update' : 'create';
  return dispatch(saveEntity('export_generators', generator, action))
    .then((response) => {
      dispatch(clearItems('export generators'));
      return response;
    });
};
