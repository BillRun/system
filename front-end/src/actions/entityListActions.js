import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import { getEntityRevisionsQuery } from '../common/ApiQueries';
import { toImmutableList } from '@/common/Util';
import { startProgressIndicator } from './progressIndicatorActions';

export const actions = {
  CLEAR_ENTITY_LIST: 'CLEAR_ENTITY_LIST',
  CLEAR_ITEMS: 'CLEAR_ITEMS',
  SET_NEXT_PAGE: 'SET_NEXT_PAGE',
  SET_ITEMS: 'SET_ITEMS',
  SET_FILTER: 'SET_FILTER',
  SET_PAGE: 'SET_PAGE',
  SET_SORT: 'SET_SORT',
  SET_SIZE: 'SET_SIZE',
  SET_STATE: 'SET_STATE',
  SET_REVISIONS: 'SET_REVISIONS',
  CLEAR_REVISIONS: 'CLEAR_REVISIONS',
  CLEAR_NEXT_PAGE: 'CLEAR_NEXT_PAGE',
};

const gotList = (collection, list) => ({
  type: actions.SET_ITEMS,
  collection,
  list,
});

const setNextPage = (collection, nextPage) => ({
  type: actions.SET_NEXT_PAGE,
  collection,
  nextPage,
});

export const clearNextPage = (collection) => ({
  type: actions.CLEAR_NEXT_PAGE,
  collection,
});

export const setListSort = (collection, sort) => ({
  type: actions.SET_SORT,
  collection,
  sort,
});

export const setListSize = (collection, size) => ({
  type: actions.SET_SIZE,
  collection,
  size,
});

export const setListFilter = (collection, filter) => ({
  type: actions.SET_FILTER,
  collection,
  filter,
});

export const setListPage = (collection, page) => ({
  type: actions.SET_PAGE,
  collection,
  page,
});

export const setListState = (collection, state) => ({
  type: actions.SET_STATE,
  collection,
  state,
});

export const setRevisions = (collection, key, revisions) => ({
  type: actions.SET_REVISIONS,
  collection,
  key,
  revisions,
});

export const clearRevisions = (collection, key) => ({
  type: actions.CLEAR_REVISIONS,
  collection,
  key,
});

export const clearList = collection => ({
  type: actions.CLEAR_ENTITY_LIST,
  collection,
});

export const clearItems = collection => ({
  type: actions.CLEAR_ITEMS,
  collection,
});

export const getList = (collection, params) => (dispatch) => {
  dispatch(startProgressIndicator());
  return apiBillRun(params)
    .then((success) => {
      try {
        dispatch(gotList(collection, success.data[0].data.details));
        dispatch(setNextPage(collection, success.data[0].data.next_page));
        return dispatch(apiBillRunSuccessHandler(success));
      } catch (e) {
        console.log('fetchList error: ', e);
        throw new Error('Error retrieving list');
      }
    })
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Network error - please refresh and try again')));
};

export const getRevisions = (collection, uniqueField, key) => (dispatch) => {
  const keys = toImmutableList(key);
  const uniqueFields = toImmutableList(uniqueField);
  const query = getEntityRevisionsQuery(collection, uniqueFields, keys);
  return apiBillRun(query)
  .then((success) => {
    try {
      dispatch(setRevisions(collection, key, success.data[0].data.details));
      return dispatch(apiBillRunSuccessHandler(success));
    } catch (e) {
      console.log('fetch revision error: ', e);
      throw new Error('fetch revision error');
    }
  })
  .catch(error => dispatch(apiBillRunErrorHandler(error, 'Network error - please refresh and try again')));
};
