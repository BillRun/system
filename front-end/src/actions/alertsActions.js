import Immutable from 'immutable';
import uuid from 'uuid';

export const SUCCESS = 'success';
export const WARNING = 'warning';
export const DANGER = 'danger';
export const INFO = 'info';

export const DISMISS_ALL_ALERTS = 'DISMISS_ALL_ALERTS';
export const DISMISS_ALERT = 'DISMISS_ALERT';
export const SHOW_ALERT = 'SHOW_ALERT';

const timeouts = {
  default: 4000,
  success: 2000,
  warning: 4000,
  danger: 6000,
  info: 4000,
};

const Alert = Immutable.Record({
  type: 'info',
  message: '',
  id: '',
  timeout: timeouts.default,
});

export function showAlert(message = '', type = INFO, timeout = timeouts.default) {
  const id = uuid.v4();
  const alert = new Alert({ message, type, id, timeout });
  return {
    type: SHOW_ALERT,
    alert,
  };
}

export function hideAlert(id) {
  return {
    type: DISMISS_ALERT,
    id,
  };
}

export function hideAllAlerts() {
  return {
    type: DISMISS_ALL_ALERTS,
  };
}

export function showSuccess(message = 'Success', timeout = timeouts.success) {
  return showAlert(message, SUCCESS, timeout);
}

export function showWarning(message = 'Warning', timeout = timeouts.warning) {
  return showAlert(message, WARNING, timeout);
}

export function showDanger(message = 'Error', timeout = timeouts.danger) {
  return showAlert(message, DANGER, timeout);
}

export function showInfo(message = 'Info', timeout = timeouts.info) {
  return showAlert(message, INFO, timeout);
}
