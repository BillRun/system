import Immutable from 'immutable';

import { actions } from '@/actions/paymentFilesActions';

const defaultState = Immutable.Map();

const paymentsFilesReducer = (state = defaultState, action) => {
  switch (action.type) {
    case actions.SET_PAYMENT_GATEWAY:
      return state
        .setIn([action.source, 'paymentGateway'], action.value)
        .setIn([action.source, 'fileType'], '');
    case actions.SET_FILE_TYPE:
      return state.setIn([action.source, 'fileType'], action.value);
    case actions.CLEAR:
      return defaultState;
    default:
      return state;
  }
};

export default paymentsFilesReducer;
