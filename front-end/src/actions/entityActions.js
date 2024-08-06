import moment from 'moment';
import Immutable from 'immutable';
import isNumber from 'is-number';
import { upperCaseFirst } from 'change-case';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler, buildRequestUrl } from '../common/Api';
import { getEntityByIdQuery, apiEntityQuery, getEntityCSVQuery, getEntitesQuery } from '../common/ApiQueries';
import { getItemDateValue, getConfig, getItemId } from '@/common/Util';
import { startProgressIndicator } from './progressIndicatorActions';

const apiTimeOutMessage = 'Oops! Something went wrong. Please try again in a few moments.';

export const actions = {
  GOT_ENTITY: 'GOT_ENTITY',
  UPDATE_ENTITY_FIELD: 'UPDATE_ENTITY_FIELD',
  DELETE_ENTITY_FIELD: 'DELETE_ENTITY_FIELD',
  CLEAR_ENTITY: 'CLEAR_ENTITY',
  CLONE_RESET_ENTITY: 'CLONE_RESET_ENTITY',
};

export const updateEntityField = (collection, path, value) => ({
  type: actions.UPDATE_ENTITY_FIELD,
  collection,
  path,
  value,
});

export const deleteEntityField = (collection, path) => ({
  type: actions.DELETE_ENTITY_FIELD,
  collection,
  path,
});

export const gotEntity = (collection, entity) => ({
  type: actions.GOT_ENTITY,
  collection,
  entity,
});

export const gotEntitySource = (collection, entity) => ({
  type: actions.GOT_ENTITY,
  collection: `source${upperCaseFirst(collection)}`,
  entity,
});

export const setCloneEntity = (collection, entityName) => ({
  type: actions.CLONE_RESET_ENTITY,
  collection,
  uniquefields: getConfig(['systemItems', entityName, 'uniqueField'], []),
});

export const clearEntity = collection => ({
  type: actions.CLEAR_ENTITY,
  collection,
});

const buildRequestData = (item, action) => {
  const apiDateFormat = getConfig('apiDateFormat', 'YYYY-MM-DD');
  switch (action) {

    case 'move': {
      const formData = new FormData();
      const query = { _id: item.getIn(['_id', '$id'], 'undefined') };
      formData.append('query', JSON.stringify(query));
      const update = (item.has('from'))
        ? { from: getItemDateValue(item, 'from').format(apiDateFormat) }
        : { to: getItemDateValue(item, 'to').format(apiDateFormat) };
      formData.append('update', JSON.stringify(update));
      return formData;
    }

    case 'close': {
      const formData = new FormData();
      const query = { _id: item.getIn(['_id', '$id'], 'undefined') };
      formData.append('query', JSON.stringify(query));
      const update = { to: getItemDateValue(item, 'to').format(apiDateFormat) };
      formData.append('update', JSON.stringify(update));
      return formData;
    }

    case 'delete': {
      const formData = new FormData();
      const query = { _id: item.getIn(['_id', '$id'], 'undefined') };
      formData.append('query', JSON.stringify(query));
      return formData;
    }

    case 'create': {
      const formData = new FormData();
      const newFrom = getItemDateValue(item, 'from').format(apiDateFormat);
      const update = item.withMutations((itemWithMutations) => {
        itemWithMutations
          .set('from', newFrom)
          .delete('originalValue');
      });
      formData.append('update', JSON.stringify(update));
      return formData;
    }

    case 'import': {
      const formData = new FormData();
      if (item.has('files')) {
        item.get('files', []).forEach((file, i) => {
          formData.append(`files[${i}]`, file, file.name)
        });
      }
      formData.append('update', JSON.stringify(item.delete('files')));
      return formData;
    }

    case 'export': {
      const exportVersion = getConfig(['env', 'exportVersion'], '');
      return item
        .map((value, key) => { // add export version to filename
          if (exportVersion !== '' && key === 'file_name') {
            return `${value}_${exportVersion}`;
          }
          return value;
        })
        .reduce((acc, data, key) => acc.push({[key]: JSON.stringify(data)}), Immutable.List())
        .toArray()
    }

    case 'update': {
      const formData = new FormData();
      const query = { _id: item.getIn(['_id', '$id'], 'undefined') };
      const update = item.withMutations((itemWithMutations) => {
        itemWithMutations
          .delete('_id')
          .delete('originalValue');
      });
      formData.append('query', JSON.stringify(query));
      formData.append('update', JSON.stringify(update));
      return formData;
    }

    case 'closeandnew': {
      const formData = new FormData();
      const update = item.withMutations((itemWithMutations) => {
        const originalFrom = getItemDateValue(item, 'originalValue', null);
        if (originalFrom !== null) {
          if (originalFrom.isSame(getItemDateValue(item, 'from', moment(0)), 'days')) {
            itemWithMutations.delete('from');
          }
        }
        itemWithMutations
          .delete('_id')
          .delete('originalValue');
      });
      formData.append('update', JSON.stringify(update));
      const query = { _id: item.getIn(['_id', '$id'], 'undefined') };
      formData.append('query', JSON.stringify(query));
      return formData;
    }

    case 'reopen': {
      const formData = new FormData();
      const query = { _id: getItemId(item, 'undefined') };
      const update = { from: item.get('from', 'undefined') };
      formData.append('query', JSON.stringify(query));
      formData.append('update', JSON.stringify(update));
      return formData;
    }

    default:
      return new FormData();
  }
};

const requestActionBuilder = (collection, item, action) => {
  if (action === 'closeandnew' && getItemDateValue(item, 'from').isSame(getItemDateValue(item, 'originalValue', moment(0)), 'day')) {
    return 'update';
  }
  if (action === 'clone') {
    return 'create';
  }
  return action;
};

const requestDataBuilder = (collection, item, action) => {
  switch (collection) {
    default:
      return buildRequestData(item, action);
  }
};

export const saveEntity = (collection, item, action) => (dispatch) => {
  dispatch(startProgressIndicator());
  const apiAction = requestActionBuilder(collection, item, action);
  const body = requestDataBuilder(collection, item, apiAction);
  const query = apiEntityQuery(collection, apiAction, body);
  return apiBillRun(query, { timeOutMessage: apiTimeOutMessage })
    .then(success => dispatch(apiBillRunSuccessHandler(success)))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error saving Entity')));
};

export const importEntities = (collection, items, operation) => (dispatch) => {
  dispatch(startProgressIndicator());
  const body = requestDataBuilder(collection, items, 'import');
  body.append('operation', operation);
  const query = apiEntityQuery(collection, 'import', body);
  return apiBillRun(query, { timeOutMessage: apiTimeOutMessage })
    .then(success => dispatch(apiBillRunSuccessHandler(success)))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error importing Entities')));
};

export const exportEntities = (entityType, params) => (dispatch) => {
  const collection = getConfig(['systemItems', entityType, 'collection'], entityType);
  const data = requestDataBuilder(collection, params, 'export');
  const apiQuery = getEntityCSVQuery(collection, data);
  const url = buildRequestUrl(apiQuery);
  window.open(url)
  return true;
};

export const fetchEntity = (collection, query) => (dispatch) => {
  dispatch(startProgressIndicator());
  return apiBillRun(query, { timeOutMessage: apiTimeOutMessage })
    .then((success) => {
      dispatch(gotEntity(collection, success.data[0].data.details[0]));
      return dispatch(apiBillRunSuccessHandler(success));
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error retrieving Entity')));
};

export const getEntity = (collection, query) => dispatch =>
  dispatch(fetchEntity(collection, query));

export const getEntityById = (name, collection, id) => (dispatch) => {
  const query = getEntityByIdQuery(collection, id);
  return dispatch(fetchEntity(name, query));
};

export const deleteEntity = (collection, item) => dispatch =>
  dispatch(saveEntity(collection, item, 'delete'));

export const closeEntity = (collection, item) => dispatch =>
  dispatch(saveEntity(collection, item, 'close'));

export const moveEntity = (collection, item, type) => (dispatch) => {
  const hackedItem = Immutable.Map().withMutations((itemWithMutations) => {
    itemWithMutations.set('_id', item.get('_id'));
    if (type === 'to') {
      itemWithMutations.set('to', item.get('to'));
    } else {
      itemWithMutations.set('from', item.get('from'));
    }
  });
  return dispatch(saveEntity(collection, hackedItem, 'move'));
};

export const reopenEntity = (collection, item, from) => (dispatch) => {
  const itemToReopen = Immutable.Map({
    _id: item.get('_id'),
    from,
  });
  return dispatch(saveEntity(collection, itemToReopen, 'reopen'));
};

export const validateMandatoryField = (value, fieldConfig) => {
  if (fieldConfig.get('mandatory', false)) {
    switch (fieldConfig.get('type', false)) {
      default: {
        if (['', null, undefined].includes(value)) {
          return `${fieldConfig.get('title', fieldConfig.get('field_name', ''))} field is required.`;
        }
      }
    }
  }
  return true;
}

export const entitySearchByQuery = (collection, query, project, sort, options, size) => dispatch => {
  const searchQuery = getEntitesQuery(collection, project, query, sort, options, size);
  return apiBillRun(searchQuery, { timeOutMessage: apiTimeOutMessage })
    .then((success) => {
      if (success && success.data && success.data[0] && success.data[0].data && success.data[0].data.details) {
        return success.data[0].data.details;
      }
      throw new Error();
    })
    .catch(error => false);
}

/**
 * Validate value by field configuration and return if value has error.
 * Supported for multi values
 * 
 * @param {any} value value to validate
 * @param {Immutable.Map()} config with field configuration
 * @return (boolean|string)
 *    TRUE or string with error message if error
 *    FALSE if no error or value is empty
 */
export const validateFieldByType = (value, config) => {
  const isMulti = config.get('multiple', false);
  if (value === '' || (isMulti && ( (Array.isArray(value) && value.length === 0) || Immutable.is(value, Immutable.List())))) {
    return false;
  }

  if (isMulti && (Array.isArray(value) || Immutable.Iterable.isIterable(value))) {
    const notMultiConfig = config.set('multiple', false);
    return value.reduce((acc, val) => {
      if (acc !== false) {
        return acc;
      }
      return validateFieldByType(val, notMultiConfig);
    }, false);
  }
 
  switch (config.get('type', '')) {
    case 'number':
    case 'decimal':
      return isNumber(value) ? false : 'Value must be numeric';
    case 'integer':
      return isNumber(value) && `${parseInt(value)}` === `${value}` ? false : 'Value must be integer';
    case 'json':
      return value === false; // no need for the message, current json field display message in the editbox
    default:
      return false;
  }
}
