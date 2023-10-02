import { apiBillRun, apiBillRunSuccessHandler, apiBillRunErrorHandler } from '../common/Api';
import {
  fetchUserByIdQuery,
  getUserLoginQuery,
  getUserCheckLoginQuery,
  getUserLogoutQuery,
  sendResetMailQuery,
  changePasswordQuery,
} from '../common/ApiQueries';
import { clearAppStorage } from './settingsActions';
import { startProgressIndicator, finishProgressIndicator } from './progressIndicatorActions';
import { saveEntity, getEntity, actions, deleteEntity } from './entityActions';

export const LOGIN = 'LOGIN';
export const LOGOUT = 'LOGOUT';
export const LOGIN_ERROR = 'LOGIN_ERROR';
export const CLEAR_LOGIN_ERROR = 'CLEAR_LOGIN_ERROR';


export const getUser = id => getEntity('users', fetchUserByIdQuery(id));

export const saveUser = (user, action) => saveEntity('users', user, action);

export const deleteUser = item => dispatch => dispatch(deleteEntity('users', item));

export const updateUserField = (path, value) => ({
  type: actions.UPDATE_ENTITY_FIELD,
  collection: 'users',
  path,
  value,
});

export const deleteUserField = path => ({
  type: actions.DELETE_ENTITY_FIELD,
  collection: 'users',
  path,
});

export const clearUser = () => ({
  type: actions.CLEAR_ENTITY,
  collection: 'users',
});

const loginSuccess = data => ({
  type: LOGIN,
  data,
});

const logoutSuccess = () => ({
  type: LOGOUT,
});

const loginError = error => ({
  type: LOGIN_ERROR,
  error,
});

const clearLoginError = () => ({
  type: CLEAR_LOGIN_ERROR,
});

export const userCheckLogin = () => (dispatch) => {
  const query = getUserCheckLoginQuery();
  dispatch(startProgressIndicator());
  return apiBillRun(query)
    .then((success) => {
      dispatch(loginSuccess(success.data[0].data.details));
      dispatch(finishProgressIndicator());
      return success;
    })
    .catch((error) => { // eslint-disable-line no-unused-vars
      dispatch(logoutSuccess());
      dispatch(finishProgressIndicator());
      return error;
    });
};


export const userDoLogin = (username, password) => (dispatch) => {
  const query = getUserLoginQuery(username, password);
  dispatch(startProgressIndicator());
  dispatch(clearLoginError());
  return apiBillRun(query)
    .then((success) => {
      dispatch(loginSuccess(success.data[0].data.details));
      dispatch(finishProgressIndicator());
      return success;
    })
    .catch((error) => { // eslint-disable-line no-unused-vars
      const message = 'Incorrect username or password, please try again.';
      dispatch(loginError(message));
      dispatch(finishProgressIndicator());
      return error;
    });
};

export const userDoLogout = () => (dispatch) => {
  const query = getUserLogoutQuery();
  dispatch(startProgressIndicator());
  return apiBillRun(query)
    .then((success) => {
      dispatch(logoutSuccess());
      dispatch(finishProgressIndicator());
      clearAppStorage();
      return success;
    })
    .catch((error) => {
      dispatch(finishProgressIndicator());
      clearAppStorage();
      return error;
    });
};

export const sendResetMail = email => (dispatch) => {
  dispatch(startProgressIndicator());
  const query = sendResetMailQuery(email);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'Success. If the user exists, a password reset email should be received shortly')))
    .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error sending email')));
};

export const savePassword = (itemId, signature, timestamp, password) => (dispatch) => {
  dispatch(startProgressIndicator());
  const query = changePasswordQuery(itemId, signature, timestamp, password);
  return apiBillRun(query)
    .then(success => dispatch(apiBillRunSuccessHandler(success, 'The password was changed successfuly')))
    .catch(error => dispatch(apiBillRunErrorHandler(error)));
};
