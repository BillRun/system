import { createSelector } from 'reselect';
import Immutable from 'immutable';
import moment from 'moment';
import { titleCase } from 'change-case';
import isNumber from 'is-number';
import {
  getConfig,
  getFieldName,
  getFieldNameType,
  setFieldTitle,
  addPlayToFieldTitle,
  parseFieldSelectOptions,
  formatSelectOptions,
  onlyLineForeignFields,
} from '@/common/Util';

const getTaxation = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['taxation']);

const getPluginActions = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['plugin_actions']);

const getImport = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.get('import');

const getSystemSettings = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['system']);

const getPlaysSettings = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['plays']);

const getPricing = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['pricing']);

const getUsageType = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.get('usage_types');

const getEventCode = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['events', 'balance']);

const getPropertyTypes = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.get('property_types');

const getBillrun = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.get('billrun');

const getEntityFields = (state, props) => {
  const entityName = Array.isArray(props.entityName) ? props.entityName : [props.entityName];
  return state.settings.getIn([...entityName, 'fields']);
};

const getEventType = (state, props) => props.eventType;

const getMinEntityDate = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.get('minimum_entity_start_date');

const getAccountFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['subscribers', 'account', 'fields']);

const getSubscriberFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['subscribers', 'subscriber', 'fields']);

const getLinesFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['lines', 'fields']);

const getBillsFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['bills', 'fields']);

const getBillsUserFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['payments', 'offline', 'uf']);

const getServiceFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['services', 'fields']);

const getTaxFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['taxes', 'fields']);

const getDiscountFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['discounts', 'fields']);

const getChargeFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['charges', 'fields']);

const getProductFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['rates', 'fields']);

const getPlanFields = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['plans', 'fields']);

const getInvoiceExport = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.get('invoice_export');

const getEmailTemplates = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.get('email_templates');

const getEvents = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['events']);

const getCollections = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['collection']);

const getTemplateTokens = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['template_token']);

const getPaymentGateways = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.getIn(['payment_gateways']);

const getInputProcessorFields = (state, props) => // eslint-disable-line no-unused-vars
  props.settings.get('fields', Immutable.List());

const selectFieldNames = (fields) => {
  if (fields) {
    return fields.map(field => field.get('field_name', ''));
  }
  return fields;
};

const selectRateCategories = (fields) => {
  if (fields) {
    const categoriesField = fields.find(field => field.get('field_name', '') === 'tariff_category');
    if (categoriesField) {
      return Immutable.List(categoriesField.get('select_options', '').split(','));
    }
  }
  return undefined;
};

const getUniqueUsageTypesFormInputProssesors = (inputProssesor) => {
  let usageTypes = Immutable.Set();
  const defaultUsaget = inputProssesor.getIn(['processor', 'default_usaget'], '');
  if (defaultUsaget !== '') {
    usageTypes = usageTypes.add(defaultUsaget);
  }
  inputProssesor
    .getIn(['processor', 'usaget_mapping'], Immutable.List())
    .forEach((usagetMapping) => {
      const usaget = usagetMapping.get('usaget', '');
      if (usaget !== '') {
        usageTypes = usageTypes.add(usaget);
      }
    });
  return usageTypes.toList();
};

const getInputProssesors = (state, props) => // eslint-disable-line no-unused-vars
  state.settings.get('file_types', Immutable.List());

const selectCsiOptions = (inputProssesors) => {
  let options = Immutable.List();
  inputProssesors.forEach((inputProssesor) => {
    const usageTypes = getUniqueUsageTypesFormInputProssesors(inputProssesor);
    const customKeys = inputProssesor.getIn(['parser', 'custom_keys'], Immutable.List());
    usageTypes.forEach((usageType) => {
      options = options.push(Immutable.Map({
        fileType: inputProssesor.get('file_type', ''),
        usageType,
        customKeys,
      }));
    });
  });
  return options;
};

const selectFielteredFields = (inputProssesors) => {
  let options = Immutable.Set();
  inputProssesors.forEach((inputProssesor) => {
    const filteredFields = inputProssesor
      .getIn(['parser', 'structure'], Immutable.List())
      .filter(field => field.get('checked', true) === true)
      .map(field => field.get('name', ''));
    options = options.concat(filteredFields);
  });
  return options.toList();
};

const selectRatingParams = (inputProssesors) => {
  let options = Immutable.Set();
  inputProssesors.forEach((inputProssesor) => {
    const ratingCalculators = inputProssesor.get('rate_calculators', Immutable.Map());
    ratingCalculators.forEach((ratingCalculatorsInCategory) => {
      ratingCalculatorsInCategory.forEach((ratingCalculatorsPriority) => {
        ratingCalculatorsPriority.forEach((fields) => {
          const currentFields = fields
            .filter(field => field.get('rate_key', '').startsWith('params.'))
            .map(field => field.get('rate_key', ''));
          options = options.concat(currentFields);
        });
      });
    });
  });
  return options.toList();
};

const selectUsageTypes = (usageTypes) => {
  if (!usageTypes) {
    return undefined;
  }
  return usageTypes.map(usageType => usageType.get('usage_type', ''));
};

const selectEventCode = (events) => {
  if (!events) {
    return undefined;
  }
  return events.map(event => event.get('event_code', ''));
};

const selectFileType = (fileTypes) => {
  if (!fileTypes) {
    return undefined;
  }
  return fileTypes.map(fileType => fileType.get('file_type', ''));
};

export const inputProssesorCsiOptionsSelector = createSelector(
  getInputProssesors,
  selectCsiOptions,
);

export const inputProssesorUsageTypesOptionsSelector = createSelector(
  getInputProssesors,
  (inputProssesors = Immutable.List()) => Immutable.Map()
    .withMutations((nputProssesorUsageTypesWithMutations) => {
      inputProssesors.forEach((inputProssesor) => {
        const types = getUniqueUsageTypesFormInputProssesors(inputProssesor);
        nputProssesorUsageTypesWithMutations.set(inputProssesor.get('file_type'), types);
      });
    }),
);

export const inputProssesorfilteredFieldsSelector = createSelector(
  getInputProssesors,
  selectFielteredFields,
);

export const inputProssesorRatingParamsSelector = createSelector(
  getInputProssesors,
  selectRatingParams,
);

export const taxMappingSelector = createSelector(
  getTaxation,
  (tax = Immutable.Map()) => tax.get('mapping'),
);

export const importersSelector = createSelector(
  getPluginActions,
  (actions = Immutable.Map()) => actions
      // get only import plugins
      .filter((methods, action) => action.startsWith('import'))
      // create {plugin => [entity1, entity2]} array from {imporEntity => [plugin1, plugin2]}
      .reduce((acc, actions, methode) => {
        // the methode is like 'imporEntity'
        const entity = getFieldNameType(methode.split('import')[1]);
        actions.forEach(action => {
            acc = acc.update(action, Immutable.List(), list =>
              (list.includes(entity) ? list : list.push(entity))
            );
        });
        return acc;
      }, Immutable.Map()
    ),
);

export const importSelector = createSelector(
  getImport,
  importConfig => importConfig,
);

export const taxationSelector = createSelector(
  getTaxation,
  taxation => taxation,
);

export const taxationTypeSelector = createSelector(
  taxationSelector,
  (taxation = Immutable.Map()) => taxation.get('tax_type'),
);

export const pricingSelector = createSelector(
  getPricing,
  pricing => pricing,
);

export const systemSettingsSelector = createSelector(
  getSystemSettings,
  system => system,
);

export const playsSettingsSelector = createSelector(
  getPlaysSettings,
  plays => plays,
);

export const playsEnabledSelector = createSelector(
  playsSettingsSelector,
  (plays = Immutable.List()) => (plays
    ? plays.filter(play => play.get('enabled', true))
    : Immutable.List()
  ),
);

export const isPlaysEnabledSelector = createSelector(
  playsEnabledSelector,
  (playsEnabled = Immutable.List()) => playsEnabled.size > 1,
);

export const availablePlaysSettingsSelector = createSelector(
  getPlaysSettings,
  plays => (plays ? plays.filter(play => play.get('enabled', true)) : undefined),
);

export const closedCycleChangesSelector = createSelector(
  systemSettingsSelector,
  (system = Immutable.Map()) => system.get('closed_cycle_changes'),
);

export const billrunSelector = createSelector(
  getBillrun,
  billrun => billrun,
);

export const minEntityDateSelector = createSelector(
  getMinEntityDate,
  minEntityDate =>
    (minEntityDate && isNumber(minEntityDate) ? moment.unix(minEntityDate) : moment(0)),
);

export const currencySelector = createSelector(
  pricingSelector,
  (pricing = Immutable.Map()) => pricing.get('currency'),
);

export const chargingDaySelector = createSelector(
  billrunSelector,
  (billrun = Immutable.Map()) => {
    const chargingDay = billrun.get('charging_day');
    return (isNumber(chargingDay)) ? chargingDay : Number(chargingDay);
  },
);

export const fileTypeSelector = createSelector(
  getInputProssesors,
  selectFileType,
);

export const usageTypeSelector = createSelector(
  getUsageType,
  selectUsageTypes,
);

export const eventCodeSelector = createSelector(
  getEventCode,
  selectEventCode,
);

export const usageTypesDataSelector = createSelector(
  getUsageType,
  usageTypes => usageTypes,
);

export const propertyTypeSelector = createSelector(
  getPropertyTypes,
  propertyTypes => propertyTypes,
);

export const entityFieldSelector = createSelector(
  getEntityFields,
  fields => fields,
);

export const accountFieldsSelector = createSelector(
  getAccountFields,
  (fields) => {
    if (fields) {
      return fields.map(field => (
        field.get('title', '') !== ''
          ? field
          : field.set('title', getFieldName(field.get('field_name', ''), 'account'))
      ));
    }
    return undefined;
  },
);

export const accountFieldNamesSelector = createSelector(
  accountFieldsSelector,
  selectFieldNames,
);

export const availablePlaysLabelsSelector = createSelector(
  availablePlaysSettingsSelector,
  (plays = Immutable.List()) => plays.reduce(
    (labels, item) => labels.set(item.get('name'), item.get('label')),
    Immutable.Map(),
  ),
);

export const subscriberFieldsSelector = createSelector(
  getSubscriberFields,
  (fields) => {
    if (fields) {
      return fields.map(field => (
        field.get('title', '') !== ''
          ? field
          : field.set('title', getFieldName(field.get('field_name', ''), 'subscription'))
      ));
    }
    return undefined;
  },
);

export const subscriberFieldsWithPlaySelector = createSelector(
  subscriberFieldsSelector,
  availablePlaysLabelsSelector,
  isPlaysEnabledSelector,
  (fields = Immutable.List(), plays = Immutable.Map(), isPlaysEnabled = false) =>
    fields.map(field => (isPlaysEnabled ? addPlayToFieldTitle(field, plays) : field)),
);

export const linesFieldsSelector = createSelector(
  getLinesFields,
  (fields) => {
    if (fields) {
      return fields.map((field) => {
        if (field.get('title', '') !== '') {
          return field;
        }
        if (field.has('foreign')) {
          return field.set('title', getFieldName(field.getIn(['foreign', 'field'], ''), getFieldNameType(field.getIn(['foreign', 'entity'], ''))));
        }
        return field.set('title', getFieldName(field.get('field_name', ''), 'lines'));
      });
    }
    return undefined;
  },
);

export const billsFieldsSelector = createSelector(
  getBillsFields,
  (fields = Immutable.List()) => {
    return fields.map((field) => {
      if (field.get('title', '') !== '') {
        return field;
      }
      if (field.has('foreign')) {
        return field.set('title', getFieldName(field.getIn(['foreign', 'field'], ''), getFieldNameType(field.getIn(['foreign', 'entity'], ''))));
      }
      return field.set('title', getFieldName(field.get('field_name', ''), 'bills'));
    });
  },
);

export const billsUserFieldsSelector = createSelector(
  getBillsUserFields,
  (fields = Immutable.List()) => {
    return fields;
  }
);

export const saveToBillPaymentGatewaySelector = createSelector(
  getPaymentGateways,
  (paymentGateways = Immutable.List()) => {
    return Immutable.List().withMutations((fieldsWithMutations) => {
      paymentGateways.forEach((paymentGateway) => {
        paymentGateway.getIn(['transactions_request'], Immutable.List()).forEach((transactionRequest) => {
          transactionRequest.getIn(['generator', 'data_structure'], Immutable.List()).forEach((field) => {
            if(field.getIn(['save_to_bill'], false) === true){
              if (field.get('title', '') !== '') {
                fieldsWithMutations.push(field.set('payment_gateway', paymentGateway.get('name', '')));
              } else {
                fieldsWithMutations.push(field
                  .set('field_name', getFieldName(field.get('name', ''), 'bills'))
                  .set('payment_gateway', paymentGateway.get('name', '')));
              }
            }
          });
        });
      });
    })
  },
);

export const productFieldsSelector = createSelector(
  getProductFields,
  (fields = Immutable.List()) => fields.map(field => setFieldTitle(field, 'product')),
);

export const rateCategoriesSelector = createSelector(
  getProductFields,
  selectRateCategories,
);

export const seriveceFieldsSelector = createSelector(
  getServiceFields,
  (fields = Immutable.List()) => fields.map(field => setFieldTitle(field, 'service')),
);

export const taxFieldsSelector = createSelector(
  getTaxFields,
  (fields = Immutable.List()) => fields.map(field => setFieldTitle(field, 'tax')),
);

export const discountFieldsSelector = createSelector(
  getDiscountFields,
  (fields = Immutable.List()) => fields.map(field => setFieldTitle(field, 'discount')),
);

export const chargeFieldsSelector = createSelector(
  getChargeFields,
  (fields = Immutable.List()) => fields.map(field => setFieldTitle(field, 'charge')),
);

export const planFieldsSelector = createSelector(
  getPlanFields,
  (fields = Immutable.List()) => fields.map(field => setFieldTitle(field, 'plan')),
);

export const templateTokenSettingsSelector = createSelector(
  getTemplateTokens,
  templateTokens => templateTokens,
);

export const templateTokenSettingsSelectorForEditor = createSelector(
  templateTokenSettingsSelector,
  (state, props, types) => types,
  (templateTokens, types) => {
    if(!templateTokens) {
      return Immutable.List();
    }
    return templateTokens
      .filter((tokens, type) => types.includes(type))
      .reduce((acc, tokens, type) =>
        Immutable.List([...acc, ...tokens.map(token => `${type}::${token}`)]),
        Immutable.List(),
      )
  }
);

export const collectionSettingsSelector = createSelector(
  getCollections,
  collection => (collection ? collection.get('settings', Immutable.Map()) : undefined),
);

export const collectionStepsSelector = createSelector(
  getCollections,
  collection => (collection ? collection.get('steps', Immutable.List()) : undefined),
);

export const collectionStepsSelectorForList = createSelector(
  collectionStepsSelector,
  steps => (steps
    ? steps.sortBy(item => parseFloat(item.get('do_after_days', 0)))
    : undefined),
);

export const eventsSettingsSelector = createSelector(
  getEvents,
  events => (events ? events.get('settings', Immutable.Map()) : undefined),
);

export const formatFieldOptions = (fields, item = Immutable.Map()) => {
  const type = getFieldNameType(item.get('entity', ''));
  if (fields) {
    return fields.map(field => ({
      value: field.get('field_name', ''),
      label: field.get('title', getFieldName(field.get('field_name', ''), type)),
      editable: field.get('editable', true),
      generated: field.get('generated', false),
      unique: field.get('unique', false),
      mandatory: field.get('mandatory', false),
      linker: field.get('linker', false),
      updater: field.get('updater', false),
      select_options: field.get('select_options', false),
      multiple: field.get('multiple', false),
      help: field.get('help', false),
      show: field.get('show', true),
      plays: field.get('plays', Immutable.List()),
    }));
  }
  return undefined;
};

export const addDefaultFieldOptions = (formatedFields, item = Immutable.Map()) => {
  if (formatedFields) {
    const entity = item.get('entity', '');
    const defaultFieldsByEntity = {
      subscription: [{
        value: 'from',
        label: 'From',
        editable: true,
        generated: false,
        unique: false,
        mandatory: true,
      }, {
        value: 'to',
        label: 'To',
        editable: true,
        generated: false,
        unique: false,
        mandatory: true,
      }],
      customer: [{
        value: 'from',
        label: 'From',
        editable: true,
        generated: false,
        unique: false,
        mandatory: true,
      }, {
        value: 'to',
        label: 'To',
        editable: true,
        generated: false,
        unique: false,
        mandatory: true,
      }],
    };
    return formatedFields.withMutations((fieldsWithMutations) => {
      const defaultFields = defaultFieldsByEntity[entity] || [];
      defaultFields
        .filter(defaultField =>
          formatedFields.findIndex(field => field.value === defaultField.value) === -1)
        .forEach((defaultField) => {
          fieldsWithMutations.push(defaultField);
        });
    });
  }
  return undefined;
};

export const invoiceTemplateHeaderSelector = createSelector(
  getInvoiceExport,
  (invoiceExport = Immutable.Map()) => invoiceExport.get('header'),
);
export const invoiceTemplateFooterSelector = createSelector(
  getInvoiceExport,
  (invoiceExport = Immutable.Map()) => invoiceExport.get('footer'),
);

export const invoiceTemplateSuggestionsSelector = createSelector(
  getInvoiceExport,
  (invoiceExport = Immutable.Map()) => invoiceExport.get('html_translation'),
);

export const invoiceTemplateTemplatesSelector = createSelector(
  getInvoiceExport,
  (invoiceExport = Immutable.Map()) => invoiceExport.get('templates'),
  // (invoiceExport = Immutable.Map()) => {
  //   const defaultTamplates = Immutable.Map({
  //     header: Immutable.List([
  //       Immutable.Map({ label: 'Empty', content: '<p>Empty</p>' }),
  //       Immutable.Map({ label: 'Default', content: '<p>default</p>' }),
  //     ]),
  //   });
  //   return invoiceExport.get('templates', defaultTamplates);
  // },
);

export const invoiceTemplateStatusSelector = createSelector(
  getInvoiceExport,
  (invoiceExport = Immutable.Map()) => invoiceExport.get('status'),
);

export const paymentGatewaysSelector = createSelector(
  getPaymentGateways,
  availablePaymentGateways => availablePaymentGateways,
);

export const emailTemplatesSelector = createSelector(
  getEmailTemplates,
  emailTemplates => emailTemplates,
);

export const eventsSelector = createSelector(
  getEvents,
  getEventType,
  (events = Immutable.Map(), type) => {
    // all balances types, Prepaid and Normal
    if (type === 'balances') {
      return events.get('balance');
    }
    if (type === 'balance') {
      return events
        .get('balance', Immutable.List())
        .filter(event => !event.get('prepaid', false));
    }
    if (type === 'balancePrepaid') {
      return events
        .get('balance', Immutable.List())
        .filter(event => event.get('prepaid', false));
    }
    return events.get(type);
  },
);

export const taxParamsKeyOptionsSelector = createSelector(
  taxFieldsSelector,
  (fields = Immutable.List()) => fields
    .filter(field => (field.get('field_name', '').startsWith('params.')))
    .map(parseFieldSelectOptions)
    .insert(0, {value: 'key', label: 'Key'})
    .toArray()
);

export const computedConditionFieldsOptionsSelector = createSelector(
    linesFieldsSelector,
    (lineFields = Immutable.List()) => lineFields
      .filter(onlyLineForeignFields)
      .map(parseFieldSelectOptions)
      .push({ value: 'type', label: 'Type' })
      .push({ value: 'usaget', label: 'Usage Type' })
      .push({ value: 'file', label: 'File name' })
      .push({ value: 'installments', label: 'Installments' })
      .toArray());

export const computedValueWhenOptionsSelector = createSelector(
    linesFieldsSelector,
    (lineFields = Immutable.List()) => lineFields
      .filter(onlyLineForeignFields)
      .map(parseFieldSelectOptions)
      .push({ value: 'condition_result', label: 'Condition Result' })
      .push({ value: 'hard_coded', label: 'Hard Coded' })
      .push({ value: 'type', label: 'Type' })
      .push({ value: 'usaget', label: 'Usage Type' })
      .push({ value: 'file', label: 'File name' })
      .toArray()
);

export const taxLineKeyOptionsSelector = createSelector(
  linesFieldsSelector,
  (lineFields = Immutable.List()) => lineFields
    .filter(onlyLineForeignFields)
    .map(parseFieldSelectOptions)
    .push({ value: 'type', label: 'Type' })
    .push({ value: 'usaget', label: 'Usage Type' })
    .push({ value: 'file', label: 'File name' })
    .push({ value: 'computed', label: 'Computed' })
    .toArray()
);

export const getFieldsWithPreFunctions = () => getConfig(['rates', 'preFunctions'], Immutable.List())
  .reduce((acc, preFunction) => {
    const id_options = preFunction.get('values', Immutable.List()).map(preFunctionValue => Immutable.Map({
      value: `${preFunctionValue}___${preFunction.get('id', '')}`,
      label: preFunction.get('title', titleCase(preFunction.get('id', ''))),
      preFunction: preFunction.get('id', ''),
      preFunctionValue: preFunctionValue
    }));
    return Immutable.List([...acc, ...id_options]);
  }, Immutable.List());

const getAdditionInputProcessorlineKeyOptions = () => {
  const options = [
    { value: 'type', label: 'Type' },
    { value: 'usaget', label: 'Usage Type' },
    { value: 'connection_type', label: 'Connection Type' },
    { value: 'usagev', label: 'Activity Volume' },
    { value: 'file', label: 'File name' },
    ...getFieldsWithPreFunctions().map(formatSelectOptions),
    { value: 'computed', label: 'Computed' },
  ];
  return Immutable.List(options);
}

export const inputProcessorlineKeyOptionsSelector = createSelector(
  getInputProcessorFields,
  getAdditionInputProcessorlineKeyOptions,
  (inputProcessorFields = Immutable.List(), additionlineKeyOptions = Immutable.List()) => inputProcessorFields
    .map(field => ({ value: field, label: field }))
    .sortBy(field => field.value)
    .push(...additionlineKeyOptions)
    .map(({value, label}) => (Immutable.Map({ value, label })))
);

const getAdditionInputProcessorComputedlineKeyOptions = (state, props) => {
  const options = [
    { value: 'type', label: 'Type' },
    { value: 'usaget', label: 'Usage Type' },
    { value: 'connection_type', label: 'Connection Type' },
    { value: 'file', label: 'File name' },
  ];
  if (props.computedLineKey && props.computedLineKey.get('type', 'regex') === 'regex') {
    options.push(...getFieldsWithPreFunctions().map(formatSelectOptions));
  }
  return Immutable.List(options);
}


export const inputProcessorComputedForeignFieldslineKeyOptionsSelector = createSelector(
  linesFieldsSelector,
  (lineFields = Immutable.List()) => lineFields
    .filter(onlyLineForeignFields)
    .filter(field => field.get('available_from', '') === 'rate')
    .map((filteredField) => {
      const fieldName = filteredField.get('field_name', '');
      const label = filteredField.get('title', titleCase(fieldName));
      return { value: fieldName, label: `${label} (foreign field)` };
    })
);

export const inputProcessorComputedlineKeyOptionsSelector = createSelector(
  getInputProcessorFields,
  inputProcessorComputedForeignFieldslineKeyOptionsSelector,
  getAdditionInputProcessorComputedlineKeyOptions,
  (
    inputProcessorFields = Immutable.List(),
    foreignFields = Immutable.List(),
    additionLineKeyOptions = Immutable.List(),
  ) => inputProcessorFields
    .map(field => ({ value: field, label: field }))
    .sortBy(field => field.value)
    .push(...foreignFields)
    .push(...additionLineKeyOptions)
    .toArray()
);
