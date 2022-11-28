import Immutable from 'immutable';

import { actions } from '@/actions/paymentFilesActions';

const defaultState = Immutable.Map({
  paymentGateway: '',
  fileType: '',
});

const paymentsFilesReducer = (state = defaultState, action) => {
  switch (action.type) {
    case actions.SET_PAYMENT_GATEWAY:
      return state
        .set('paymentGateway', action.value)
        .set('fileType', '');
    case actions.SET_FILE_TYPE:
      return state.set('fileType', action.value);
    case actions.CLEAR:
      return defaultState;
    default:
      return state;
  }
};

export default paymentsFilesReducer;
