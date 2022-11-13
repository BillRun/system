import { getConfig, getUnitLabel } from '@/common/Util';
import getSymbolFromCurrency from 'currency-symbol-map';
import Immutable from 'immutable';


export const getBalanceConditionData = conditionName =>
  getConfig(['events', 'operators', 'balance', 'conditions'], Immutable.Map())
    .find(condType => condType.get('id', '') === conditionName, null, Immutable.Map());

/**
 * retrieve the service that the group is defined in
 * @param  {String} group        [The group name]
 * @param  {Object} servicesData [List of services data]
 * @return {Object}              [Relevant service]
 */
export const getGroupRelatedService = (group, servicesData) => {
  let service = '';
  servicesData.forEach((groups, key) => {
    if (groups.includes(group)) {
      service = key;
    }
  });
  return service;
};

/**
 * Creates the object to save in the DB for event 'related_entities' field
 * @param  {[String]} groupName    [The group's name]
 * @param  {[Object]} servicesData [List of services data]
 * @return {[Object]}              [Related entities]
 */
export const createRelatedEntities = (groupName, servicesData) => Immutable.List()
  .push(Immutable.Map({ type: 'group', key: groupName }))
  .push(Immutable.Map({ type: 'service', key: getGroupRelatedService(groupName, servicesData) }));

export const buildBalanceConditionPath = (trigger, limitation, params = {}) => {
  switch (limitation) {
    case 'group':
      return Immutable.List().withMutations((listWithMutations) => {
        params.groupNames.split(',').forEach(groupName => listWithMutations.push(
          Immutable.Map({
            path: `balance.groups.${groupName}.${trigger}`,
            total_path: `balance.groups.${groupName}.total`,
            related_entities: createRelatedEntities(groupName, params.servicesData),
          })));
      });
    case 'activity_type': {
      const path = (params.hasOwnProperty('overGroup') && params.overGroup === 'over_group')
        ? `balance.totals.${params.activityType}.over_group.${trigger}`
        : `balance.totals.${params.activityType}.${trigger}`;
      return Immutable.List([Immutable.Map({
        path,
        total_path: '',
        related_entities: Immutable.List()
      })]);
    }
    case 'none':
    default:
      return Immutable.List([Immutable.Map({
        path: 'balance.cost',
        total_path: '',
        related_entities: Immutable.List(),
      })]);
  }
};

export const getTriggerFromBalanceConditionPath = path => path.substr(path.lastIndexOf('.') + 1);

export const getLimitationFromBalanceConditionPath = (path) => {
  if (path.indexOf('.groups.') !== -1) {
    return 'group';
  }
  if (path.indexOf('.totals.') !== -1) {
    return 'activity_type';
  }
  return 'none';
};

export const getOverGroupFromBalanceConditionPath = path =>
  (path.indexOf('.over_group.') !== -1 ? 'over_group' : 'none');

export const getActivityTypeFromBalanceConditionPath = (path, limitation) => {
  if (limitation !== 'activity_type') {
    return '';
  }
  const limit = getOverGroupFromBalanceConditionPath(path) === 'over_group'
    ? path.lastIndexOf('.over_group')
    : path.lastIndexOf('.');
  return path.substring(path.lastIndexOf('.totals.') + 8, limit);
};

/**
 * Creates an option for an option under 'groups included' list
 * @param  {[String]} group        [The group's name]
 * @param  {[Object]} servicesData [List of services data]
 * @return {[Object]}              [group option]
 */
export const createGroupOption = (group, servicesData) => {
  const label = `${group} (${getGroupRelatedService(group, servicesData)})`;
  return { value: group, label };
};

export const getGroupFromBalanceConditionPath = (paths, limitation) => {
  if (limitation !== 'group') {
    return '';
  }
  return paths.reduce((acc, currPath) => {
    const path = currPath.get('path', '');
    const groupName = path.substring(path.lastIndexOf('.groups.') + 8, path.lastIndexOf('.'));
    return acc.push(groupName);
  }, Immutable.List())
  .join();
};

export const getPathParams = (paths) => {
  const first = paths.get(0, Immutable.Map()).get('path', '');
  const limitation = getLimitationFromBalanceConditionPath(first);
  const overGroup = getOverGroupFromBalanceConditionPath(first);
  return {
    trigger: getTriggerFromBalanceConditionPath(first),
    limitation,
    overGroup,
    activityType: getActivityTypeFromBalanceConditionPath(first, limitation),
    groupNames: getGroupFromBalanceConditionPath(paths, limitation),
  };
};

export const getBalanceConditionName = condition =>
  getBalanceConditionData(condition.get('type', '')).get('title', '');

export const getUnitTitle = (unit, trigger, usaget, propertyTypes, usageTypesData, currency, operatorType) => { // eslint-disable-line max-len
  if (operatorType === 'reached_percentage') {
    return '%';
  }
  if (trigger === 'usagev' || unit !== '') {
    return getUnitLabel(propertyTypes, usageTypesData, usaget, unit);
  }
  return getSymbolFromCurrency(currency);
};
  
export const getConditionValue = (condition, params) => {
  const { propertyTypes, usageTypesData, currency } = params;
  const { trigger, limitation, activityType } = getPathParams(condition.get('paths', Immutable.List()));
  const usaget = (limitation === 'group' ? condition.get('usaget', '') : activityType);
  const unitTitle = getUnitTitle(condition.get('unit', ''), trigger, usaget, propertyTypes, usageTypesData, currency, condition.get('type', ''));
  if (unitTitle === '%' && getBalanceConditionData(condition.get('type', '')).get('type', 'text') === "tags") {
    return condition
      .get('value', '')
      .split(',')
      .filter(val => val !== '')
      .map(val => `${val}${unitTitle}`)
      .join(', ');
  }
  return `${condition.get('value', '')} ${unitTitle}`;
};

export const getConditionDescription = (condition, params) => {
  const { trigger, limitation, activityType } = getPathParams(condition.get('paths', Immutable.List()));
  let pref = trigger === 'usagev' ? 'Usage' : 'Cost';
  if (condition && condition.getIn(['paths', 0, 'path'], '').indexOf('over_group') !== -1) {
    pref = `Exceeding ${pref.toLowerCase()}`;
  }
  switch (limitation) {
    case 'group':
      return `${pref} of group(s) ${getBalanceConditionName(condition)} ${getConditionValue(condition, params)}`;
    case 'activity_type':
      return `${pref} of ${activityType} activity ${getBalanceConditionName(condition)} ${getConditionValue(condition, params)}`;
    case 'none':
    default:
      return `Total cost ${getBalanceConditionName(condition)} ${getConditionValue(condition, params)}`;
  }
};

export const gitPeriodLabel = (value) => {
  switch (value) {
    case 'minutely':
      return 'Minutes';
    case 'hourly':
      return 'Hours';
    default:
      return 'Select unit...';
  }
};

export const gitTimeOptions = (value) => {
  if (value === 'minutely') {
    return [{ value: 15, label: '15' }, { value: 30, label: '30' }];
  }
  if (value === 'hourly') {
    return Array.from(new Array(24), (v, k) => k + 1).map(v => ({ value: v, label: `${v}` }));
  }
  return [];
};

export const getBalancePrepaidConditionType = (condition) => {
  const path = condition.get('paths', Immutable.List()).first(Immutable.Map()).get('path', '');
  if (path === 'pp_includes_external_id') {
    return 'bucket';
  }
  if (path === 'connection_type') {
    return 'is_prepaid';
  }
  return 'value';
}

export const getBalancePrepaidConditionIndexByType = (type, conditions) => {
  if (type === 'bucket') {
    return conditions.findIndex(condition => condition.get('paths', Immutable.List()).first(Immutable.Map()).get('path', '') === 'pp_includes_external_id');
  }
  if (type === 'is_prepaid') {
    return conditions.findIndex(condition => condition.get('paths', Immutable.List()).first(Immutable.Map()).get('path', '') === 'connection_type');
  }
  return conditions.findIndex(condition => !['pp_includes_external_id', 'connection_type'].includes(condition.get('paths', Immutable.List()).first(Immutable.Map()).get('path', '')));
}