import Immutable from 'immutable';
import moment from 'moment';
import { LOGIN, LOGOUT, LOGIN_ERROR, CLEAR_LOGIN_ERROR } from '@/actions/userActions';

const User = Immutable.Record({
  auth: null,
  name: '',
  roles: ['guest'],
  error: '',
  lastLogin: null,
});

export default function (state = new User(), action) {
  switch (action.type) {
    case LOGIN:
      return new User({
        auth: true,
        roles: action.data.permissions,
        name: action.data.user,
        lastLogin: (action.data.last_login) ? moment(action.data.last_login) : null,
      });

    case LOGOUT:
      return new User({ auth: false });

    case LOGIN_ERROR:
      return state.set('error', action.error);

    case CLEAR_LOGIN_ERROR:
      return state.set('error', '');

    default:
      return state;
  }
}
