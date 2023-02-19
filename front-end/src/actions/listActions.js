import Immutable from 'immutable';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import { getEntitesQuery, getServicesKeysWithInfoQuery, getPlansKeysQuery } from '../common/ApiQueries';
import { startProgressIndicator } from './progressIndicatorActions';
import {
  getConfig,
  toImmutableList,
} from '@/common/Util';


export const actions = {
  GOT_LIST: 'GOT_LIST',
  ADD_TO_LIST: 'ADD_TO_LIST',
  REMOVE_FROM_LIST: 'REMOVE_FROM_LIST',
  CLEAR_LIST: 'CLEAR_LIST',
  SET_NEXT_PAGE: 'SET_NEXT_PAGE',
};

export const gotList = (collection, list) => ({
  type: actions.GOT_LIST,
  collection,
  list,
});

const setNextPage = nextPage => ({
  type: actions.SET_NEXT_PAGE,
  nextPage,
});

export const addToList = (collection, items) => ({
  type: actions.ADD_TO_LIST,
  collection,
  items,
});

const fetchList = (collection, params) => (dispatch) => {
  dispatch(startProgressIndicator());
  return apiBillRun(params)
  .then((success) => {
    try {
      dispatch(gotList(collection, success.data[0].data.details));
      dispatch(setNextPage(success.data[0].data.next_page));
      return dispatch(apiBillRunSuccessHandler(success));
    } catch (e) {
      console.log('fetchList error: ', e);
      throw new Error('Error retrieving list');
    }
  })
  .catch(error => dispatch(apiBillRunErrorHandler(error, 'Network error - please refresh and try again')));
};

const fetchToList = (collection, params) => (dispatch) => {
  dispatch(startProgressIndicator());
  return apiBillRun(params)
  .then((success) => {
    try {
      dispatch(addToList(collection, success.data[0].data.details));
      return dispatch(apiBillRunSuccessHandler(success));
    } catch (e) {
      console.log('fetchToList error: ', e);
      throw new Error('Error retrieving list');
    }
  })
  .catch(error => dispatch(apiBillRunErrorHandler(error, 'Network error - please refresh and try again')));
};

export const clearList = collection => ({
  type: actions.CLEAR_LIST,
  collection,
});

export const deleteFromList = (collection, index) => ({
  type: actions.REMOVE_FROM_LIST,
  collection,
  index,
});

export const getList = (collection, params) => dispatch =>
  dispatch(fetchList(collection, params));

export const pushToList = (collection, params) => dispatch =>
  dispatch(fetchToList(collection, params));

export const getEntitiesOptions = (entities = []) => dispatch => {
  entities.forEach((entity) => {
    const entitiesName = getConfig(['systemItems', entity, 'itemsType'], '');
    const collection = getConfig(['systemItems', entity, 'collection'], '');
    let requestQuery = {};
    if (entity === 'service') {
      requestQuery = getServicesKeysWithInfoQuery();
    } else if(entity === 'plan') {
      requestQuery = getPlansKeysQuery({ name: 1, play: 1, description: 1, 'include.services': 1 });
    } else {
      const uniqueFields = toImmutableList(getConfig(['systemItems', entity, 'uniqueField'], ''));
      const project = Immutable.Map().withMutations((fieldsWithMutations) => {
        fieldsWithMutations.set('_id', 0);
        fieldsWithMutations.set('description', 1);
        uniqueFields.forEach((uniqueField) => {
          fieldsWithMutations.set(uniqueField, 1);
        });
      });
      requestQuery = getEntitesQuery(collection, project);
    }
    dispatch(getList(`available_${entitiesName}`, requestQuery));
  });
}

export const clearEntitiesOptions = (entities = []) => dispatch => {
  entities.forEach((entity) => {
    const entitiesName = getConfig(['systemItems', entity, 'itemsType'], '');
    dispatch(clearList(`available_${entitiesName}`));
  });
}
