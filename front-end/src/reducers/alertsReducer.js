import Immutable from 'immutable';
import { SHOW_ALERT, DISMISS_ALERT, DISMISS_ALL_ALERTS } from '@/actions/alertsActions';


const defaultState = Immutable.List();

export default function (state = defaultState, action) {
  switch (action.type) {

    case SHOW_ALERT:
      return state.insert(0, action.alert);

    case DISMISS_ALERT:
      return state.filter(alert => alert.get('id') !== action.id);

    case DISMISS_ALL_ALERTS:
      return defaultState;

    default:
      return state;
  }
}
