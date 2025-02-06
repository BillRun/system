import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import { getDeleteLineQuery } from '../common/ApiQueries';
import { startProgressIndicator } from './progressIndicatorActions';

export const deleteLine = id => (dispatch) => { // eslint-disable-line import/prefer-default-export
  dispatch(startProgressIndicator());
  const query = getDeleteLineQuery(id);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Line deleted successfully!')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error deleting Line')));
};
