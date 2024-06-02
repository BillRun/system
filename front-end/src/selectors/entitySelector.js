import { createSelector } from 'reselect';
import Immutable from 'immutable';
import { upperCaseFirst } from 'change-case';
import { getConfig, getItemId, getItemMode, getItemMinFromDate } from '@/common/Util';
import { minEntityDateSelector, closedCycleChangesSelector } from './settingsSelector';


export const getPropsItem = (state, props) => props.item;

const getUniqueFiled = (state, props, entityName) =>
  getConfig(['systemItems', entityName, 'uniqueField'], 'name');

const getRevisions = (state, props, entityName) => {
  const entityCollectin = getConfig(['systemItems', entityName, 'collection'], '');
  return state.entityList.revisions.get(entityCollectin);
};

const getTab = (state, props) => {
  if (props.location && props.location.query && typeof props.location.query.tab !== 'undefined') {
    return parseInt(props.location.query.tab) || undefined;
  }
  return undefined;
};

const getMessage = (state, props) => {
  if (props.location && props.location.query && typeof props.location.query.message !== 'undefined') {
    return props.location.query.message;
  }
  return undefined;
};

const getSignature = (state, props) => {
  if (props.location && props.location.query && typeof props.location.query.sig !== 'undefined') {
    return props.location.query.sig;
  }
  return undefined;
};

const getTimestamp = (state, props) => {
  if (props.location && props.location.query && typeof props.location.query.t !== 'undefined') {
    return props.location.query.t;
  }
  return undefined;
};

const getUsername = (state, props) => {
  if (props.location && props.location.query && typeof props.location.query.u !== 'undefined') {
    return props.location.query.u;
  }
  return undefined;
};

const getAction = (state, props) => {
  if (props.location && props.location.query && props.location.query.action) {
    return props.location.query.action.length > 0 ? props.location.query.action : null;
  }
  return null;
};

const getId = (state, props) => {
  if (props.params && props.params.itemId) {
    return props.params.itemId.length > 0 ? props.params.itemId : null;
  }
  return null;
};

const getEntityType = (state, props) => {
  if (props.params && props.params.itemType) {
    return props.params.itemType.length > 0 ? props.params.itemType : null;
  }
  return null;
};

const getItem = (state, props, entityName) => {
  if (entityName && entityName.startsWith('source')) {
    return state.entity.get(entityName);
  }
  switch (entityName) {
    case 'prepaid_include':
    case 'autorenew':
    case 'customer':
    case 'subscription':
    case 'discount':
    case 'charge':
    case 'reports':
    case 'importer':
    case 'tax':
    case 'immediate-invoice':
      return state.entity.get(entityName);
    case 'charging_plan':
      return state.plan;
    default: {
      return state[entityName];
    }
  }
};

const getItemSource = (state, props, entityName) => getItem(state, props, `source${upperCaseFirst(entityName)}`);

export const selectorFieldsByEntity = (
  item = Immutable.Map(),
  accountFields,
  subscriberFields,
  productFields,
) => {
  switch (item.get('entity')) {
    case 'customer':
      return accountFields;
    case 'subscription':
      return subscriberFields;
    case 'product':
      return productFields;
    default:
      return undefined;
  }
};

const selectMaxFrom = (item = null, minDate = null) => getItemMinFromDate(item, minDate);

const selectRevisions = (item, allRevisions, uniqueFiled) => {
  if (allRevisions && getItemId(item, false)) {
    return allRevisions.get(item.get(uniqueFiled, ''));
  }
  return undefined;
};

const selectFormMode = (action, id, item) => {
  if (action) {
    return action;
  }
  if (!id) {
    return 'create';
  }

  if (getItemId(item, false)) {
    return getItemMode(item);
  }
  return 'loading';
};

const selectSimpleMode = (action, id, item) => {
  if (action) {
    return action;
  }
  if (!id) {
    return 'create';
  }

  if (getItemId(item, false)) {
    return 'update';
  }
  return 'loading';
};

const selectEntityRates = (entity = Immutable.Map()) => entity.get('rates');

export const revisionsSelector = createSelector(
  getItem,
  getRevisions,
  getUniqueFiled,
  selectRevisions,
);

export const tabSelector = createSelector(
  getTab,
  tab => tab,
);

export const timestampSelector = createSelector(
  getTimestamp,
  timestamp => timestamp
);

export const usernameSelector = createSelector(
  getUsername,
  username => username
);

export const sigSelector = createSelector(
  getSignature,
  signature => signature
);

export const messageSelector = createSelector(
  getMessage,
  (message) => {
    if (message) {
      try {
        return JSON.parse(message);
      } catch (e) {
        return undefined;
      }
    }
    return undefined;
  },
);

export const itemSourceSelector = createSelector(
  getItemSource,
  item => item,
);

export const itemSelector = createSelector(
  getItem,
  item => item,
);

export const idSelector = createSelector(
  getId,
  id => id,
);

export const modeSelector = createSelector(
  getAction,
  idSelector,
  itemSelector,
  selectFormMode,
);

export const importItemTypeSelector = createSelector(
  getEntityType,
  itemType => (getConfig(['import', 'allowed_entities'], Immutable.List()).includes(itemType) ? itemType : undefined),
);

export const exportItemTypeSelector = createSelector(
  getEntityType,
  itemType => (getConfig('systemItems', Immutable.Map()).keySeq().includes(itemType) ? itemType : undefined),
);

export const modeSimpleSelector = createSelector(
  getAction,
  idSelector,
  itemSelector,
  selectSimpleMode,
);

const selectDangerousDate = (closedCycleChanges, minEntityDate) => {
  if (closedCycleChanges) {
    return null;
  }
  return minEntityDate;
};

export const dangerousDateSelector = createSelector(
  closedCycleChangesSelector,
  minEntityDateSelector,
  selectDangerousDate,
);

export const entityMinFrom = createSelector(
  getPropsItem,
  dangerousDateSelector,
  selectMaxFrom,
);

export const actionSelector = createSelector(
  getAction,
  action => action || undefined
);

export const sourceEntityRatesSelector = createSelector(
  itemSourceSelector,
  selectEntityRates,
);
