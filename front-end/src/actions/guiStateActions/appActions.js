import { apiBillRun } from '@/common/Api';
import { isWorkersSelector } from '@/selectors/appSelectors';
import {
  getWorkersQuery,
} from '@/common/ApiQueries';
export const SET_APP_DATA = 'SET_APP_DATA';

export const setAppData = (key, data) => ({
  type: SET_APP_DATA,
  key,
  data,
});

export const getWorkersStatus = () => (dispatch, getState) => {
  const isWorkers = isWorkersSelector(getState(), {}) || null;
  if (isWorkers === null) {
    return apiBillRun(getWorkersQuery())
      .then(() => dispatch(setAppData('isWorkers', true)))
      .catch(() => dispatch(setAppData('isWorkers',false)));
  }
  return Promise.resolve(isWorkers);
}



