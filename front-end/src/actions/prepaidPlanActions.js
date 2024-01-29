export const ADD_NOTIFICATION = 'ADD_NOTIFICATION';
export const REMOVE_NOTIFICATION = 'REMOVE_NOTIFICATION';
export const UPDATE_NOTIFICATION_FIELD = 'UPDATE_NOTIFICATION_FIELD';
export const ADD_BALANCE_NOTIFICATIONS = 'ADD_BALANCE_NOTIFICATIONS';
export const REMOVE_BALANCE_NOTIFICATIONS = 'REMOVE_BALANCE_NOTIFICATIONS';
export const BLOCK_PRODUCT = 'BLOCK_PRODUCT';
export const ADD_BALANCE_THRESHOLD = 'ADD_BALANCE_THRESHOLD';
export const CHANGE_BALANCE_THRESHOLD = 'CHANGE_BALANCE_THRESHOLD';
export const REMOVE_BALANCE_THRESHOLD = 'REMOVE_BALANCE_THRESHOLD';

export function addBalanceNotifications(balance) {
  return {
    type: ADD_BALANCE_NOTIFICATIONS,
    balance,
  };
}

export function addNotification(thresholdId) {
  return {
    type: ADD_NOTIFICATION,
    thresholdId,
  };
}

export function removeNotification(thresholdId, index) {
  return {
    type: REMOVE_NOTIFICATION,
    thresholdId,
    index,
  };
}

export function updateNotificationField(thresholdId, index, field, value) {
  return {
    type: UPDATE_NOTIFICATION_FIELD,
    thresholdId,
    index,
    field,
    value,
  };
}

export function removeBalanceNotifications(balanceId) {
  return {
    type: REMOVE_BALANCE_NOTIFICATIONS,
    balanceId,
  };
}

export function blockProduct(rates) {
  return {
    type: BLOCK_PRODUCT,
    rates,
  };
}

export function addBalanceThreshold(balanceId) {
  return {
    type: ADD_BALANCE_THRESHOLD,
    balanceId,
  };
}

export function changeBalanceThreshold(balanceId, value) {
  return {
    type: CHANGE_BALANCE_THRESHOLD,
    balanceId,
    value,
  };
}

export function removeBalanceThreshold(balanceId) {
  return {
    type: REMOVE_BALANCE_THRESHOLD,
    balanceId,
  };
}
