import { Map, List } from 'immutable';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '@/common/Api';
import { saveSettingsQuery } from '@/common/ApiQueries';
import { startProgressIndicator } from '@/actions/progressIndicatorActions';
import { getSettings } from '@/actions/settingsActions';
import {
  gotEntity,
  clearEntity,
  updateEntityField,
  deleteEntityField,
  importEntities,
} from '@/actions/entityActions';

const defaultImporter = Map({
  map: Map(),
  fileDelimiter: ',',
});

export const initImporter = () => gotEntity('importer', defaultImporter);

export const deleteImporter = () => clearEntity('importer');

export const updateImporterValue = (path, value) => updateEntityField('importer', path, value);

export const deleteImporterValue = path => deleteEntityField('importer', path);

export const sendImport = (collection, items, operation) => importEntities(collection, items, operation);

const convertToArrayOfFieldsObject = (fieldMap) => {
  if (List.isList(fieldMap)) {
    return fieldMap;
  }
  return fieldMap.reduce((acc, value, field) => acc.push(Map({field, value})), List());
}

const convertFromArrayOfFieldsObject = (fieldMap) => {
  if (Map.isMap(fieldMap)) {
    return fieldMap;
  }
  return fieldMap.reduce((acc, mapper) => acc.set(mapper.get('field', ''), mapper.get('value', '')), Map());
}

export const convertImporterMapperFromDb = mapper => mapper
  .update('map', List(), convertFromArrayOfFieldsObject)
  .update('multiFieldAction', List(), convertFromArrayOfFieldsObject);

const convertImporterMapperToDb = mapper => mapper
  .update('map', Map(), convertToArrayOfFieldsObject)
  .update('multiFieldAction', Map(), convertToArrayOfFieldsObject);

export const addNewImporterMap = (importerMap) => (dispatch, getState) => {
  const { settings } = getState();
  const importMappers = settings.getIn(['import', 'mapping'], List());
  const newMappersArray = importMappers.push(importerMap);
  return dispatch(saveImporters(newMappersArray, `Mapper '${importerMap.get('label', '')}' was saved`));
}

export const updateImporterMap = (importerMap, index) => (dispatch, getState) => {
  const { settings } = getState();
  const importMappers = settings.getIn(['import', 'mapping'], List());
  const newMappersArray = importMappers.set(index, importerMap);
  return dispatch(saveImporters(newMappersArray, `Mapper '${importerMap.get('label', '')}' was updated`));
}

export const deleteImporterMap = (importerMapLabel) => (dispatch, getState) => {
  const { settings } = getState();
  const importMappers = settings.getIn(['import', 'mapping'], List());
  const newMappersArray = importMappers.filter(mapper => mapper.get('label', '') !== importerMapLabel);
  return dispatch(saveImporters(newMappersArray, `Mapper '${importerMapLabel}' was removed`));
}

const saveImporters = (importMappers, successMessage = 'Mapper was saved', errorMessage = 'Error saving mapper') => (dispatch) => {
  dispatch(startProgressIndicator());
  const convertedImportMapping = importMappers.map(convertImporterMapperToDb);  
  const queries = saveSettingsQuery(convertedImportMapping, 'import.mapping');
  return apiBillRun(queries)
    .then(success => dispatch(apiBillRunSuccessHandler(success, successMessage)))
    .catch(error => dispatch(apiBillRunErrorHandler(error, errorMessage)))
    .finally(() => dispatch(getSettings('import.mapping')));
}
