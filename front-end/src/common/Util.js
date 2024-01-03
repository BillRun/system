import Immutable from 'immutable';
import moment from 'moment';
import isNumber from 'is-number';
import { titleCase, sentenceCase } from 'change-case';
import systemConfig from '../config/system';
import fieldNamesConfig from '../language/fieldNames.json';
import reportConfig from '../config/report';
import systemItemsConfig from '../config/entities.json';
import mainMenu from '../config/mainMenu.json';
import eventsConfig from '../config/events.json';
import ratesConfig from '../config/rates.json';
import importConfig from '../config/import.json';
import exportConfig from '../config/export.json';
import collectionsConfig from '../config/collections.json';
import customFieldsConfig from '../config/customFields.json';
import discountConfig from '../config/discount.json';

/**
 * Get data from config files
 * @param  {[String/Array of strings]} key/path in config
 * @param  {[Any]} [defaultValue=null] If key/Path not exist
 * @return {[Any]} Value from config or default value if key/path not exist
 */

let configCache = Immutable.Map();

export const getConfig = (key, defaultValue = null) => {
  const path = Array.isArray(key) ? key : [key];
  if (configCache.isEmpty()) {
    configCache = Immutable.fromJS(systemConfig);
  }

  if (!configCache.has(path[0])) {
    switch (path[0]) {
      case 'env': {
        const env = Immutable.Map().withMutations((envWithMutations) => {
          Object.entries(process.env).forEach((value) => {
            if (value[0].startsWith('REACT_APP_')) {
              const envKey = value[0].replace("REACT_APP_", "");
              let envValue = value[1];
              if (value[1] === 'true') {
                envValue = true;
              } else if(value[1] === 'false') {
                envValue = false;
              } else if (isNumber(envValue)) {
                envValue = parseFloat(envValue);
              }
              envWithMutations.set(envKey, envValue);
            }
          });
        });
        configCache = configCache.set('env', env);
      }
        break;
      case 'reports': configCache = configCache.set('reports', Immutable.fromJS(reportConfig));
        break;
      case 'fieldNames': configCache = configCache.set('fieldNames', Immutable.fromJS(fieldNamesConfig));
        break;
      case 'systemItems': configCache = configCache.set('systemItems', Immutable.fromJS(systemItemsConfig));
        break;
      case 'mainMenu': configCache = configCache.set('mainMenu', Immutable.fromJS(mainMenu));
        break;
      case 'events': configCache = configCache.set('events', Immutable.fromJS(eventsConfig));
        break;
      case 'rates': configCache = configCache.set('rates', Immutable.fromJS(ratesConfig));
        break;
      case 'import': configCache = configCache.set('import', Immutable.fromJS(importConfig));
        break;
      case 'export': configCache = configCache.set('export', Immutable.fromJS(exportConfig));
        break;
      case 'collections': configCache = configCache.set('collections', Immutable.fromJS(collectionsConfig));
        break;
      case 'customFields': configCache = configCache.set('customFields', Immutable.fromJS(customFieldsConfig));
        break;
      case 'discount': configCache = configCache.set('discount', Immutable.fromJS(discountConfig));
        break;
      default: console.log(`Config category not exists ${path}`);
    }
  }
  return configCache.getIn(path, defaultValue);
};

export const getFieldName = (field, category, defaultValue = null) => {
  const categoryName = getConfig(['fieldNames', category, field], false);
  if (typeof categoryName === 'string' && categoryName.length > 0) {
    return categoryName;
  }
  const rootName = getConfig(['fieldNames', field], false);
  if (typeof rootName === 'string' && rootName.length > 0) {
    return rootName;
  }
  if (defaultValue !== null) {
    return defaultValue;
  }
  return field;
};

/*  Map entity different names to fieldNames.json names */
export const getFieldNameType = (type) => {
  switch (type.toLocaleLowerCase()) {
    case 'account':
    case 'accounts':
    case 'customer':
      return 'account';
    case 'subscription':
    case 'subscriptions':
    case 'subscribers':
    case 'subscriber':
      return 'subscription';
    case 'lines':
    case 'line':
    case 'usage':
      return 'lines';
    case 'service':
    case 'services':
      return 'service';
    case 'plan':
    case 'plans':
      return 'plan';
    case 'rate':
    case 'rates':
    case 'product':
    case 'products':
      return 'product';
    default:
      return type;
  }
};

/*  Map entity different names to entities.json names */
export const getFieldEntityKey = (type) => {
  switch (type.toLocaleLowerCase()) {
    case 'account':
    case 'accounts':
    case 'customer':
      return 'customer';
    case 'lines':
    case 'line':
    case 'usage':
      return 'usage';
    default:
      return getFieldNameType(type);
  }
};

export const getZiroTimeDate = (date = moment()) => {
  const dateWithoutTime = moment(date).utcOffset(0);
  dateWithoutTime.set({ hour: 0, minute: 0, second: 0, millisecond: 0 });
  return dateWithoutTime;
};

export const getFirstName = item => item.get('first_name', item.get('firstname', ''));

export const getLastName = item => item.get('last_name', item.get('lastname', ''));

export const getCustomerId = item => item.get('aid', '');

export const getSubscriberId = item => item.get('sid', '');

export const buildPageTitle = (mode, entityName, item = Immutable.Map()) => {
  switch (mode) {
    case 'clone':
    case 'create': {
      const entitySettings = getConfig(['systemItems', entityName]);
      if (entitySettings) {
        return `Create New ${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))}`;
      }
      return 'Create';
    }

    case 'loading':
    case 'closeandnew': {
      const entitySettings = getConfig(['systemItems', entityName]);
      if (entitySettings) {
        if (entityName === 'customer') {
          return `Edit ${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))} - ${getFirstName(item)} ${getLastName(item)} [${getCustomerId(item)}]`;
        }
        if (entityName === 'subscription') {
          return `Edit ${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))} - ${getFirstName(item)} ${getLastName(item)} [${getSubscriberId(item)}]`;
        }
        if (entityName === 'auto_renew') {
          return `Edit ${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))}`;
        }
        return `Edit ${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))} - ${item.get(entitySettings.get('uniqueField', ''), '')}`;
      }
      return 'Edit';
    }

    case 'view': {
      const entitySettings = getConfig(['systemItems', entityName]);
      if (entitySettings) {
        if (entityName === 'customer') {
          return `${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))} - ${getFirstName(item)} ${getLastName(item)} [${getCustomerId(item)}]`;
        }
        if (entityName === 'subscription') {
          return `${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))} - ${getFirstName(item)} ${getLastName(item)} [${getSubscriberId(item)}]`;
        }
        return `${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))} - ${item.get(entitySettings.get('uniqueField', ''), '')}`;
      }
      return 'View';
    }

    case 'update': {
      const entitySettings = getConfig(['systemItems', entityName]);
      if (entitySettings) {
        if (entityName === 'customer') {
          return `Update ${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))} - ${getFirstName(item)} ${getLastName(item)} [${getCustomerId(item)}]`;
        }
        if (entityName === 'subscription') {
          return `Update ${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))} - ${getFirstName(item)} ${getLastName(item)} [${getSubscriberId(item)}]`;
        }
        return `Update ${titleCase(entitySettings.get('itemName', entitySettings.get('itemType', '')))} - ${item.get(entitySettings.get('uniqueField', ''), '')}`;
      }
      return 'Update';
    }
    default:
      return '';
  }
};

export const getItemDateValue = (item, fieldName, defaultValue = moment()) => {
  if (Immutable.Map.isMap(item)) {
    const dateString = item.get(fieldName, false);
    if (typeof dateString === 'string') {
      const dateFromString = moment(dateString);
      if (dateFromString.isValid()) {
        return dateFromString;
      }
    }
    const dateUnix = item.getIn([fieldName, 'sec'], false);
    if (typeof dateUnix === 'number') {
      const dateFromTimestemp = moment.unix(dateUnix);
      if (dateFromTimestemp.isValid()) {
        return dateFromTimestemp;
      }
    }
  }
  return defaultValue;
};

export const getItemId = (item, defaultValue = null) => {
  if (Immutable.Map.isMap(item)) {
    return item.getIn(['_id', '$id'], defaultValue);
  }
  return defaultValue;
};

export const isItemClosed = (item) => {
  const earlyExpiration = item.getIn(['revision_info', 'early_expiration'], null);
  if (earlyExpiration !== null) {
    return earlyExpiration;
  }
  const toTime = getItemDateValue(item, 'to');
  return toTime.isAfter(moment()) && toTime.isBefore(moment().add(50, 'years'));
};

export const isItemReopened = (item, prevItem) => {
  const currentFrom = getItemDateValue(item, 'from', false);
  const prevTo = getItemDateValue(prevItem, 'to', false);
  if (!currentFrom || !prevTo) {
    return false;
  }

  return currentFrom.isAfter(prevTo.add(1, 'days'));
};

export const isItemFinite = (item, toField = 'to') => {
  const toTime = getItemDateValue(item, toField);
  return toTime.isBefore(moment().add(50, 'years'));
};

export const getItemMode = (item, undefinedItemStatus = 'create') => {
  if (Immutable.Map.isMap(item)) {
    if (getItemId(item, null) === null) {
      return 'create';
    }
    const status = item.getIn(['revision_info', 'status'], '');
    const isLast = item.getIn(['revision_info', 'is_last'], true);
    if (['expired'].includes(status) || (status === 'active' && !isLast)) {
      return 'view';
    }
    if (['future'].includes(status) || isItemClosed(item)) {
      return 'update';
    }
    return 'closeandnew';
  }
  return undefinedItemStatus;
};

export const getItemMinFromDate = (item, minDate) => {
  // item and minDate
  if (minDate && getItemId(item, false)) {
    return moment.max(minDate, getItemDateValue(item, 'originalValue', getItemDateValue(item, 'from', moment(0))));
  }
  // only item
  if(getItemId(item, false)) {
    return getItemDateValue(item, 'originalValue', getItemDateValue(item, 'from', moment(0)));
  }
  // only minDate
  if (minDate) {
    return minDate;
  }
  // allow component set default value if no item and minDate exist
  return undefined;
};

export const getRevisionStartIndex = (item, revisions) => {
  const index = revisions.findIndex(revision => getItemId(revision) === getItemId(item));
  if (index <= 0) {
    return 0;
  }
  if (index + 1 === revisions.size) {
    return ((index - 2) >= 0) ? index - 2 : 0;
  }
  return index - 1;
};

export const formatSelectOptions = option => (
  Immutable.Map.isMap(option)
    ? ({ value: option.get('value', ''), label: option.get('label', '') })
    : ({ value: option, label: sentenceCase(option) })
);

export const parseConfigSelectOptions = configOption => formatSelectOptions(
  configOption.has('title')
    ? Immutable.Map({ value: configOption.get('id'), label: configOption.get('title') })
    : configOption.get('id')
);

export const parseFieldSelectOptions = (fieldOption) => formatSelectOptions(
  Immutable.Map({
    value: fieldOption.get('field_name'),
    label: fieldOption.get('title', fieldOption.get('field_name'), ''),
  })
);

export const isLinkerField = (field = Immutable.Map()) => (
  field.get('unique', false) &&
  !field.get('generated', false) &&
  field.get('editable', true)
);

export const isUpdaterField = (field = Immutable.Map()) => (
  field.get('unique', false) ||
  (field.get('field_name','') === 'key' && field.get('mandatory', false) && field.get('system', false)) ||
  (field.get('field_name','') === 'name' && field.get('mandatory', false) && field.get('system', false))
);

export const createReportColumnLabel = (label, fieldsOptions, opOptions, oldField, oldOp, newField, newOp) => {
  const oldFieldLabel = oldField === '' ? '' : fieldsOptions.find(
    fieldConfig => fieldConfig.get('id') === oldField, null, Immutable.Map({ title: newField }),
  ).get('title', '');
  const newFieldLabel = oldField === newField ? oldFieldLabel : fieldsOptions.find(
    fieldConfig => fieldConfig.get('id') === newField, null, Immutable.Map({ title: newField }),
  ).get('title', '');

  const oldOpLabel = oldOp === '' ? '' : opOptions.find(
    groupByOperator => groupByOperator.get('id') === oldOp, null, Immutable.Map(),
  ).get('title', '');
  const newOpLabel = oldOp === newOp ? oldOpLabel : opOptions.find(
    groupByOperator => groupByOperator.get('id') === newOp, null, Immutable.Map(),
  ).get('title', '');
  // Check if label is empty or was NOT changed by user
  const oldLabel = oldOpLabel === '' || oldOp === 'group' ? oldFieldLabel : `${oldFieldLabel} (${oldOpLabel})`;
  if (label === '' || label === oldLabel) {
    return newOpLabel === '' || newOp === 'group' ? newFieldLabel : `${newFieldLabel} (${newOpLabel})`;
  }
  return label;
};

export const getSettingsKey = (entityName, asArray = false) => {
  const key = getConfig(['systemItems', getFieldEntityKey(entityName), 'settingsKey'], entityName);
  if (asArray) {
    return key.split('.');
  }
  return key;

};

export const getSettingsPath = (entityName, asArray = false, extraPath = []) => {
  const key = getSettingsKey(entityName, true);
  const path = (extraPath.length > 0) ? [...key, ...extraPath] : key;
  if (asArray) {
    return path;
  }
  return path.join('.');
};

export const getRateByKey = (rates, rateKey) => rates.find(rate => rate.get('key', '') === rateKey) || Immutable.Map();

export const getRateUsaget = rate => rate.get('rates', Immutable.Map()).keySeq().first() || '';

export const getRateUsagetByKey = (rates, rateKey) => getRateUsaget(getRateByKey(rates, rateKey));

export const getRateUnit = (rate, usaget) => rate.getIn(['rates', usaget, 'BASE', 'rate', 0, 'uom_display', 'range'], '');

export const getUom = (propertyTypes, usageTypes, usaget) => {
  const selectedUsaget = usageTypes.find(usageType => usageType.get('usage_type', '') === usaget) || Immutable.Map();
  return (propertyTypes.find(prop => prop.get('type', '') === selectedUsaget.get('property_type', '')) || Immutable.Map()).get('uom', Immutable.List());
};

export const getUsagePropertyType = (usageTypesData, usage) =>
  (usageTypesData.find(usaget => usaget.get('usage_type', '') === usage) || Immutable.Map()).get('property_type', '');

export const getUnitLabel = (propertyTypes, usageTypes, usaget, unit) => {
  const uom = getUom(propertyTypes, usageTypes, usaget);
  return (uom.find(propertyType => propertyType.get('name', '') === unit) || Immutable.Map()).get('label', '');
};

export const getValueByUnit = (propertyTypes, usageTypes, usaget, unit, value, toBaseUnit = true) => { // eslint-disable-line max-len
  if (value === 'UNLIMITED') {
    return 'UNLIMITED';
  }
  const uom = getUom(propertyTypes, usageTypes, usaget);
  const u = (uom.find(propertyType => propertyType.get('name', '') === unit) || Immutable.Map()).get('unit', 1);
  return (value.toString().split(',').map(val => (toBaseUnit ? (val * u) : (val / u))).join());
};

const getItemConvertedRates = (propertyTypes, usageTypes, item, toBaseUnit, type) => {
  const convertedRates = item.get('rates', Immutable.Map()).withMutations((ratesWithMutations) => {
    ratesWithMutations.forEach((rates, usagetOrPlan) => {
      rates.forEach((rate, planOrUsaget) => {
        const usaget = (type === 'product' ? usagetOrPlan : planOrUsaget);
        const plan = (type === 'product' ? planOrUsaget : usagetOrPlan);
        rate.get('rate', Immutable.List()).forEach((rateStep, index) => {
          const rangeUnit = rateStep.getIn(['uom_display', 'range'], 'counter');
          const intervalUnit = rateStep.getIn(['uom_display', 'interval'], 'counter');
          const convertedFrom = getValueByUnit(propertyTypes, usageTypes, usaget, rangeUnit, rateStep.get('from'), toBaseUnit);
          const newFrom = isNumber(convertedFrom) ? parseFloat(convertedFrom) : convertedFrom;
          const to = rateStep.get('to');
          const convertedTo = (to === 'UNLIMITED' ? 'UNLIMITED' : getValueByUnit(propertyTypes, usageTypes, usaget, rangeUnit, to, toBaseUnit));
          const newTo = isNumber(convertedTo) ? parseFloat(convertedTo) : convertedTo;
          const price = rateStep.get('price');
          const convertedPrice = isNumber(price) ? parseFloat(price) : price;
          const convertedInterval = getValueByUnit(propertyTypes, usageTypes, usaget, intervalUnit, rateStep.get('interval'), toBaseUnit);
          const newInterval = isNumber(convertedInterval) ? parseFloat(convertedInterval) : convertedInterval;
          const ratePath = (type === 'product' ? [usaget, plan, 'rate', index] : [plan, usaget, 'rate', index]);
          ratesWithMutations.setIn([...ratePath, 'from'], newFrom);
          ratesWithMutations.setIn([...ratePath, 'to'], newTo);
          ratesWithMutations.setIn([...ratePath, 'price'], convertedPrice);
          ratesWithMutations.setIn([...ratePath, 'interval'], newInterval);
        });
        const percentage = rate.get('percentage', null);
        if (percentage !== null) {
          const ratePath = (type === 'product' ? [usaget, plan] : [plan, usaget]);
          const convertedPercentage = toBaseUnit ? percentage / 100 : percentage * 100;
          ratesWithMutations.setIn([...ratePath, 'percentage'], convertedPercentage);
        }
      });
    });
  });
  return !convertedRates.isEmpty()
    ? convertedRates
    : Immutable.Map();
};

export const getProductConvertedRates = (propertyTypes, usageTypes, item, toBaseUnit = true) => getItemConvertedRates(propertyTypes, usageTypes, item, toBaseUnit, 'product');

export const getPlanConvertedRates = (propertyTypes, usageTypes, item, toBaseUnit = true) => getItemConvertedRates(propertyTypes, usageTypes, item, toBaseUnit, 'plan');

export const getPlanConvertedPpThresholds = (propertyTypes, usageTypes, ppIncludes, item, toBaseUnit = true) => { // eslint-disable-line max-len
  const convertedPpThresholds = item.get('pp_threshold', Immutable.Map()).withMutations((ppThresholdsWithMutations) => {
    ppThresholdsWithMutations.forEach((value, ppId) => {
      const ppInclude = ppIncludes.find(pp => pp.get('external_id', '') === parseInt(ppId)) || Immutable.Map();
      const unit = ppInclude.get('charging_by_usaget_unit', false);
      if (unit) {
        const usaget = ppInclude.get('charging_by_usaget', '');
        const newValue = getValueByUnit(propertyTypes, usageTypes, usaget, unit, value, toBaseUnit);
        ppThresholdsWithMutations.set(ppId, parseFloat(newValue));
      }
    });
  });
  return !convertedPpThresholds.isEmpty()
    ? convertedPpThresholds
    : Immutable.Map();
};

export const getPlanConvertedNotificationThresholds = (propertyTypes, usageTypes, ppIncludes, item, toBaseUnit = true) => { // eslint-disable-line max-len
  const convertedPpThresholds = item.get('notifications_threshold', Immutable.Map()).withMutations((notificationThresholdsWithMutations) => {
    notificationThresholdsWithMutations.forEach((notifications, ppId) => {
      const ppInclude = ppIncludes.find(pp => pp.get('external_id', '') === parseInt(ppId)) || Immutable.Map();
      const unit = ppInclude.get('charging_by_usaget_unit', false);
      if (unit) {
        const usaget = ppInclude.get('charging_by_usaget', '');
        notifications.forEach((notification, index) => {
          const value = notification.get('value', '');
          const newValue = getValueByUnit(propertyTypes, usageTypes, usaget, unit, value, toBaseUnit); // eslint-disable-line max-len
          notificationThresholdsWithMutations.setIn([ppId, index, 'value'], parseFloat(newValue));
        });
      }
    });
  });
  return !convertedPpThresholds.isEmpty()
    ? convertedPpThresholds
    : Immutable.Map();
};

export const getPlanConvertedPpIncludes = (propertyTypes, usageTypes, ppIncludes, item, toBaseUnit = true) => { // eslint-disable-line max-len
  const convertedIncludes = item.get('include', Immutable.Map()).withMutations((includesWithMutations) => {
    includesWithMutations.forEach((include, index) => {
      const ppId = include.get('pp_includes_external_id', '');
      const ppInclude = ppIncludes.find(pp => pp.get('external_id', '') === parseInt(ppId)) || Immutable.Map();
      const unit = ppInclude.get('charging_by_usaget_unit', false);
      if (unit) {
        const usaget = ppInclude.get('charging_by_usaget', '');
        const value = include.get('usagev');
        const newValue = getValueByUnit(propertyTypes, usageTypes, usaget, unit, value, toBaseUnit);
        includesWithMutations.setIn([index, 'usagev'], parseFloat(newValue));
      }
    });
  });
  return !convertedIncludes.isEmpty()
    ? convertedIncludes
    : Immutable.Map();
};

export const getGroupUsaget = group => (group.get('cost', false) !== false
  ? 'cost'
  : group.get('usage_types', Immutable.Map()).keySeq().get(0, false));

export const getGroupUsageTypes = group => (group.get('cost', false) !== false
  ? 'cost'
  : Immutable.List(group.get('usage_types', Immutable.Map()).keySeq().toArray()));

export const isGroupMonetaryBased = group => getGroupUsaget(group) === 'cost';

export const getGroupValue = group => (isGroupMonetaryBased(group)
  ? group.get('cost', '')
  : group.get('value', ''));

export const getGroupUsages = group => (isGroupMonetaryBased(group)
  ? Immutable.List(['cost'])
  : Immutable.List(group.get('usage_types', Immutable.Map()).keySeq().toArray()));

export const getGroupUnit = group => (isGroupMonetaryBased(group)
  ? 'cost'
  : group.get('usage_types', Immutable.Map()).valueSeq().get(0, Immutable.Map()).get('unit', false));

export const getPlanConvertedIncludes = (propertyTypes, usageTypes, item, toBaseUnit = true) => {
  const convertedIncludes = item.get('include', Immutable.Map()).withMutations((includesWithMutations) => {
    includesWithMutations.get('groups', Immutable.Map()).forEach((include, group) => {
      const unit = getGroupUnit(include);
      const usaget = getGroupUsaget(include);
      if (unit && usaget && !isGroupMonetaryBased(include)) {
        const value = getGroupValue(include);
        const newValue = getValueByUnit(propertyTypes, usageTypes, usaget, unit, value, toBaseUnit);
        const newConvertedValue = (newValue === 'UNLIMITED') ? newValue : parseFloat(newValue);
        includesWithMutations.setIn(['groups', group, 'value'], newConvertedValue);
      }
    });
  });
  return !convertedIncludes.isEmpty()
    ? convertedIncludes
    : Immutable.Map();
};

export const convertServiceBalancePeriodToObject = (item) => {
  if (['', 'default'].includes(item.get('balance_period', 'default'))) {
    return { type: 'default', unit: '', value: '' };
  }
  const balancePeriodArray = item.get('balance_period', '').split(' ');
  const unit = balancePeriodArray[balancePeriodArray.length - 1];
  const value = Number(balancePeriodArray[balancePeriodArray.length - 2]);
  const type = 'custom_period';
  return { type, unit, value: (unit === 'days') ? value + 1 : value };
};

export const convertServiceBalancePeriodToString = (item) => {
  if (['', 'default'].includes(item.getIn(['balance_period', 'type'], 'default'))) {
    return 'default';
  }
  const unit = item.getIn(['balance_period', 'unit'], '');
  const value = item.getIn(['balance_period', 'value'], 1);
  const balancePeriod = (unit === 'days') ? `tomorrow +${value - 1} days` : `+${value} ${unit}`;
  return balancePeriod;
};

export const getAvailableFields = (settings, additionalFields = []) => {
  const fields = settings
    .get('fields', Immutable.List())
    .map(field => (Immutable.Map({ value: field, label: field })))
    .sortBy(field => field.get('value', ''));
  return fields.concat(Immutable.fromJS(additionalFields));
};

export const escapeRegExp = text =>
  text.toString().replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');

export const createRateListNameByArgs = (query = Immutable.Map()) => query.reduce((acc, value, key) => `${acc}.${key}.${value}`, 'rates');

export const setFieldTitle = (field, entity, keyProperty = 'field_name') => {
  if (field.has('title')) {
    return field;
  }
  const entityName = getFieldNameType(!entity && field.has('entity') ? field.get('entity') : entity);
  const key = field.get(keyProperty, '');
  const defaultLabel = sentenceCase(field.get(keyProperty, ''));
  return field.set('title', getFieldName(key, entityName, defaultLabel));
};

export const addPlayToFieldTitle = (field, plays = Immutable.Map()) => {
  const fieldPlays = field.get('plays', Immutable.List());
  if (!fieldPlays.isEmpty()) {
    const fieldPlayLabels = fieldPlays.reduce(
      (result, playName) => result.push(plays.get(playName, playName)),
      Immutable.List(),
    ).join(', ');
    return field.set('title', `${field.get('title', '')} (${fieldPlayLabels})`);
  }
  return field;
};

export const toImmutableList = (value) => {
  if ([undefined, null, ''].includes(value)) {
    return Immutable.List();
  }
  if (Array.isArray(value)) {
    return Immutable.List([...value]);
  }
  if (Immutable.Iterable.isIterable(value)) {
    return value.toList();
  }
  return Immutable.List([value]);
};

export const sortFieldOption = (optionsA, optionB) => {
  const a = optionsA.get('title', '').toUpperCase(); // ignore upper and lowercase
  const b = optionB.get('title', '').toUpperCase(); // ignore upper and lowercase
  if (a < b) {
    return -1;
  }
  if (a > b) {
    return 1;
  }
  return 0;
};

export const inConfigOptionBlackList = (config, value, configName = 'exclude') => {
  const blackList = config.get(configName, Immutable.List());
  if (blackList.isEmpty()) {
    return false;
  }
  return blackList.includes(value);
};

export const inConfigOptionWhiteList = (config, value, configName = 'include') => {
  const whiteList = config.get(configName, Immutable.List());
  if (whiteList.isEmpty()) {
    return true;
  }
  return whiteList.includes(value);
};

export const onlyLineForeignFields = lineField => lineField.has('foreign');

export const foreignFieldWithoutDates = foreignField => foreignField.getIn(['foreign', 'translate', 'type'], '') !== 'unixTimeToString';

export const isEditableFiledProperty = (field, editable, propName = '') => {
  if (!editable) {
    return false;
  }
  const changeableProps = field.get('changeable_props', null);
  if (changeableProps === null) {
    return !field.get('system', false);
  }
  if (propName === '') {
    return !changeableProps.isEmpty();
  }
  return changeableProps.includes(propName);
};

export const shouldUsePlays = availablePlays => (availablePlays.size > 1);

export const getPlayOptions = availablePlays => availablePlays.map(play => ({
  value: play.get('name', ''),
  label: play.get('label', play.get('name', '')),
})).toArray();

/**
 * return the property type of that matches a usage type/s
 * @param  {[List]} propertyTypes [List of property types]
 * @param  {[List]} usageTypes    [List of usage types]
 * @return {[Set]}               [Set of matching property types]
 */
export const inferPropTypeFromUsageType = (propertyTypes, usageTypes) => {
  const uom = Immutable.List().withMutations((listWithMutations) => {
    if (usageTypes && !usageTypes.isEmpty()) {
      usageTypes.forEach(usaget => listWithMutations.push(usaget.get('unit', '')));
    }
  });
  const props = Immutable.List().withMutations((listWithMutations) => {
    propertyTypes.forEach((p) => {
      const units = p.get('uom', Immutable.List()).reduce((acc, curr) =>
        acc.push(curr.get('name', '')), Immutable.List());
      const t = Immutable.Map({ type: p.get('type', ''), uom: units });
      listWithMutations.push(t);
    });
  });
  return Immutable.Set().withMutations((listWithMutations) => {
    props.forEach((prop) => {
      uom.forEach((unit) => {
        if (prop.get('uom', Immutable.List()).includes(unit)) {
          listWithMutations.add(prop.get('type', ''));
        }
      });
    });
  });
};



export const formatPluginLabel = (plugin) => {
  const plugin_key = 'Plugin';
  if (!Immutable.Map.isMap(plugin)) {
    return plugin_key;
  }
  const name = plugin.get('name', plugin_key);
  if (name === plugin_key) {
    return plugin_key;
  }
  return titleCase(name.substring(0, name.lastIndexOf(plugin_key)));
}