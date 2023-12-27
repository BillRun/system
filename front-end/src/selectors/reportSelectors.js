import { createSelector } from 'reselect';
import Immutable from 'immutable';
import { sentenceCase } from 'change-case';
import {
  getFieldName,
  getFieldNameType,
  getConfig,
  sortFieldOption,
  createReportSaveToBillsField
} from '@/common/Util';
import {
  subscriberFieldsWithPlaySelector,
  inputProssesorfilteredFieldsSelector,
  inputProssesorCalculatedFieldsSelector,
  accountFieldsSelector,
  linesFieldsSelector,
  billsFieldsSelector,
  billsUserFieldsSelector,
  saveToBillPaymentsPaymentGatewaySelector,
  saveToBillDenialsPaymentGatewaySelector,
  saveToBillTransactionsResponsePaymentGatewaySelector,
  saveToBillTransactionsRequestPaymentGatewaySelector,
  rateCategoriesSelector,
  isPlaysEnabledSelector,
} from './settingsSelector';


const getReportEntityConfigFields = type => getConfig(['reports', 'fields', type], Immutable.List());

const getReportEntities = () => getConfig(['reports', 'entities'], Immutable.List());

const formatReportFields = (fields) => {
  if (!fields) {
    return undefined;
  }
  return fields.map(field => Immutable.Map({
    id: field.get('field_name', ''),
    title: field.get('title', ''),
    type: field.get('type', 'string'),
    aggregatable: true,
    searchable: field.get('searchable', true),
    inputConfig: field.get('inputConfig', null),
  }));
};

const selectReportLinesFields = (
  customKeys = Immutable.List(),
  calculatedFields = Immutable.List(),
  billrunFields = Immutable.List(),
  categoryFields = Immutable.List(),
) =>
  Immutable.List().withMutations((optionsWithMutations) => {
    // set fields from IP
    customKeys.forEach((customKey) => {
      optionsWithMutations.push(Immutable.Map({
        field_name: `uf.${customKey}`,
        title: `${getFieldName(customKey, 'lines', sentenceCase(customKey))} (User field)`,
      }));
    });
    calculatedFields.forEach((calculatedField) => {
      optionsWithMutations.push(Immutable.Map({
        field_name: `cf.${calculatedField}`,
        title: `${getFieldName(calculatedField, 'lines', sentenceCase(calculatedField))} (Calculated field)`,
      }));
    });
    categoryFields.forEach((customKey) => {
      const fieldLabel = getFieldName(customKey, 'lines', sentenceCase(customKey));
      const chargeLabel = getFieldName('charge', 'lines', sentenceCase('charge'));
      const productKeyLabel = getFieldName('product_key', 'lines', sentenceCase('product_key'));
      const fieldsPreffix = `rates.tariff_category.${customKey}`;
      optionsWithMutations.push(Immutable.Map({
        field_name: `${fieldsPreffix}.pricing.charge`,
        title: `${fieldLabel} ${chargeLabel}`,
        type: 'number',
      }));
      optionsWithMutations.push(Immutable.Map({
        field_name: `${fieldsPreffix}.key`,
        title: `${fieldLabel} ${productKeyLabel}`,
        inputConfig: Immutable.Map({
          inputType: 'select',
          callback: 'getProductsOptions',
          callbackArgument: Immutable.Map({ tariff_category: customKey }),
        }),
      }));
    });

    // Set fields from billrun settings
    billrunFields.forEach((billrunField) => {
      optionsWithMutations.push(billrunField);
    });
  });

  const selectReportBillsFields = (
    userFields = Immutable.List(),
    billrunFields = Immutable.List(),
    saveToBillPaymentsFields = Immutable.List(),
    saveToBillDenialsFields = Immutable.List(),
    saveToBillTransactionsResponseFields = Immutable.List(),
    saveToBillTransactionsRequestFields = Immutable.List(),
  ) =>
    Immutable.List().withMutations((optionsWithMutations) => {
      // set fields from IP
      saveToBillTransactionsRequestFields.forEach((saveToBillsField) => {
        optionsWithMutations.push(
          createReportSaveToBillsField(saveToBillsField, 'pg_request')
        );
      });
      saveToBillTransactionsResponseFields.forEach((saveToBillsField) => {
        optionsWithMutations.push(
          createReportSaveToBillsField(saveToBillsField, 'pg_response')
        );
      });
      saveToBillDenialsFields.forEach((saveToBillsField) => {
        optionsWithMutations.push(
          createReportSaveToBillsField(saveToBillsField, 'pg_denials')
        );
      });
      saveToBillPaymentsFields.forEach((saveToBillsField) => {
        optionsWithMutations.push(
          createReportSaveToBillsField(saveToBillsField, 'pg_payments')
        );
      });
      // Set fields from billrun settings
      billrunFields.forEach((billrunField) => {
        optionsWithMutations.push(billrunField);
      });
      userFields.forEach((userField) => {
        optionsWithMutations.push(Immutable.Map({
          field_name: `uf.${userField}`,
          title: `${getFieldName(userField, 'bills', sentenceCase(userField))} (User field)`,
        }));
      });
    });

const concatJoinFields = (
  fields, joinFields = Immutable.Map(), excludeFields = Immutable.Map(),
) => ((!fields)
  ? Immutable.List()
  : fields
    .filter(field => !excludeFields.get('currentEntity', Immutable.List()).includes(field.get('id', '')))
    .withMutations((fieldsWithMutations) => {
      joinFields.forEach((entityfields, entity) => {
        const entityLabel = sentenceCase(getConfig(['systemItems', entity, 'itemName'], entity));
        if (!entityfields.isEmpty()) {
          entityfields.forEach((entityfield) => {
            if (!excludeFields.get(entity, Immutable.List()).includes(entityfield.get('id', ''))) {
              const joinId = `$${entity}.${entityfield.get('id', '')}`;
              const joinTitle = `${entityLabel}: ${entityfield.get('title', entityfield.get('id', ''))}`;
              const joinField = entityfield.withMutations(field => field
                .set('id', joinId)
                .set('title', joinTitle)
                .set('entity', entity));
              fieldsWithMutations.push(joinField);
            }
          });
        }
      });
    })
);

const mergeEntityAndReportConfigFields = (billrunConfigFields, type, isPlayEnabled = false) => {
  const entityFields = (type === 'queue') ? billrunConfigFields : formatReportFields(billrunConfigFields);
  const defaultField = Immutable.Map({
    searchable: true,
    aggregatable: true,
  });
  return Immutable.List().withMutations((fieldsWithMutations) => {
    // Push all fields from Billrun config
    entityFields.forEach((entityField) => {
      fieldsWithMutations.push(entityField);
    });
    // Push report config fields or override if exist
    getReportEntityConfigFields(type).forEach((predefinedFiled) => {
      const index = fieldsWithMutations.findIndex(field => field.get('id', '') === predefinedFiled.get('id', ''));
      if (index === -1) {
        fieldsWithMutations.push(defaultField.merge(predefinedFiled));
      } else {
        fieldsWithMutations.update(index, Immutable.Map(), field => field.merge(predefinedFiled));
      }
    });
    // Set title if not exist
    fieldsWithMutations.forEach((field, index) => {
      if (!field.has('title')) {
        const configTitle = getFieldName(field.get('id', ''), getFieldNameType(type));
        const title = configTitle === field.get('id', '') ? sentenceCase(configTitle) : configTitle;
        fieldsWithMutations.setIn([index, 'title'], title);
      }
    });
  })
  // filter play fields
  .filter(field => (
    (!['play', 'subscriber.play'].includes(field.get('id', ''))) || (
      (field.get('id', '') === 'play' && isPlayEnabled) ||
      (field.get('id', '') === 'subscriber.play' && isPlayEnabled && type === 'usage')
    )
  ))
  // filter hidden fields
  .filter(field => field.get('show', true))
  .sort(sortFieldOption);
};

const selectReportFields = (
  subscriberFields,
  accountFields,
  linesFileds,
  logFileFields,
  paymentsTransactionsRequestFields,
  paymentsTransactionsResponseFields,
  paymentDenialsFields,
  paymentsFilesFields,
  queueFields,
  rebalanceQueueFields,
  eventFields,
  billsFields,
) => {
  // usage: linesFileds,
  // duplicate fields list by join (same fields from different collections)
  // that will be removed frm UI.
  const usageExcludeIds = Immutable.Map({
    subscription: Immutable.List(['sid', 'aid']),
    customer: Immutable.List(['aid']),
    currentEntity: Immutable.List(['firstname', 'lastname']),
  });
  const usage = concatJoinFields(linesFileds, Immutable.Map({
    subscription: subscriberFields,
    customer: accountFields,
  }), usageExcludeIds);

  // const subscription = subscriberFields;
  const subscriptionExcludeIds = Immutable.Map({
    customer: Immutable.List(['aid', 'type']),
    usage: Immutable.List(['firstname', 'lastname', 'sid', 'aid', 'plan']),
    currentEntity: Immutable.List([]),
  });
  const subscription = concatJoinFields(subscriberFields, Immutable.Map({
    customer: accountFields,
    usage: linesFileds,
  }), subscriptionExcludeIds);

  const customer = accountFields; // without collections join (one -> many still not possible on BE)
  // const customerExcludeIds = Immutable.Map({
  //   subscription: Immutable.List(['sid', 'type']),
  //   usage: Immutable.List(['firstname', 'lastname', 'sid', 'sid', 'plan']),
  //   currentEntity: Immutable.List([]),
  // });
  // const customer = concatJoinFields(accountFields, Immutable.Map({
  //   subscription: subscriberFields,
  //   usage: linesFileds,
  // }), customerExcludeIds);

  return Immutable.Map({
    usage,
    usage_archive: usage, // use same fields selector as lines, in th BE we use different collection
    subscription,
    customer,
    logFile: logFileFields,
    paymentsTransactionsRequest: paymentsTransactionsRequestFields,
    paymentsTransactionsResponse: paymentsTransactionsResponseFields,
    paymentDenials: paymentDenialsFields,
    paymentsFiles: paymentsFilesFields,
    queue: queueFields,
    rebalance_queue: rebalanceQueueFields,
    event: eventFields,
    bills: billsFields,
  });
};

const reportLinesFieldsSelector = createSelector(
  inputProssesorfilteredFieldsSelector,
  inputProssesorCalculatedFieldsSelector,
  linesFieldsSelector,
  rateCategoriesSelector,
  selectReportLinesFields,
);

export const reportSubscriberFieldsSelector = createSelector(
  subscriberFieldsWithPlaySelector,
  () => 'subscribers',
  isPlaysEnabledSelector,
  mergeEntityAndReportConfigFields,
);

export const reportAccountFieldsSelector = createSelector(
  accountFieldsSelector,
  () => 'account',
  isPlaysEnabledSelector,
  mergeEntityAndReportConfigFields,
);

const reportLogFileFieldsSelector = createSelector(
  () => Immutable.List(),
  () => 'logFile',
  isPlaysEnabledSelector,
  mergeEntityAndReportConfigFields,
);

const reportRebalanceQueueFieldsSelector = createSelector(
  () => Immutable.List(),
  () => 'rebalance_queue',
  () => true,
  mergeEntityAndReportConfigFields,
);

const reportPaymentsTransactionsRequestFieldsSelector = createSelector(
  () => Immutable.List(),
  () => 'paymentsTransactionsRequest',
  () => true,
  mergeEntityAndReportConfigFields,
);

const reportPaymentsTransactionsResponseFieldsSelector = createSelector(
  () => Immutable.List(),
  () => 'paymentsTransactionsResponse',
  () => true,
  mergeEntityAndReportConfigFields,
);

const reportPaymentDenialsFieldsSelector = createSelector(
  () => Immutable.List(),
  () => 'paymentDenials',
  () => true,
  mergeEntityAndReportConfigFields,
);

const reportPaymentsFilesFieldsSelector = createSelector(
  () => Immutable.List(),
  () => 'paymentsFiles',
  () => true,
  mergeEntityAndReportConfigFields,
);

const reportEventFileFieldsSelector = createSelector(
  () => Immutable.List(),
  () => 'event',
  isPlaysEnabledSelector,
  mergeEntityAndReportConfigFields,
);

export const reportUsageFieldsSelector = createSelector(
  reportLinesFieldsSelector,
  () => 'usage',
  isPlaysEnabledSelector,
  mergeEntityAndReportConfigFields,
);

const reportQueueFieldsSelector = createSelector(
  reportUsageFieldsSelector,
  () => 'queue',
  isPlaysEnabledSelector,
  mergeEntityAndReportConfigFields,
);

export const reportBillsFieldsSelector = createSelector(
  billsUserFieldsSelector,
  billsFieldsSelector,
  saveToBillPaymentsPaymentGatewaySelector,
  saveToBillDenialsPaymentGatewaySelector,
  saveToBillTransactionsResponsePaymentGatewaySelector,
  saveToBillTransactionsRequestPaymentGatewaySelector,
  selectReportBillsFields,
);

const reportBillsSelector = createSelector(
  reportBillsFieldsSelector,
  () => 'bills',
  isPlaysEnabledSelector,
  mergeEntityAndReportConfigFields,
);

export const reportEntitiesSelector = createSelector(
  getReportEntities,
  entities => entities,
);

export const reportEntitiesFieldsSelector = createSelector(
  reportSubscriberFieldsSelector,
  reportAccountFieldsSelector,
  reportUsageFieldsSelector,
  reportLogFileFieldsSelector,
  reportPaymentsTransactionsRequestFieldsSelector,
  reportPaymentsTransactionsResponseFieldsSelector,
  reportPaymentDenialsFieldsSelector,
  reportPaymentsFilesFieldsSelector,
  reportQueueFieldsSelector,
  reportRebalanceQueueFieldsSelector,
  reportEventFileFieldsSelector,
  reportBillsSelector,
  selectReportFields,
);
