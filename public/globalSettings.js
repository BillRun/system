var globalSetting = {
  storageVersion: 'v0.1',
  serverUrl: "",
  serverApiTimeOut: 300000, // 5 minutes
  serverApiDebug: false,
  serverApiDebugQueryString: 'XDEBUG_SESSION_START=netbeans-xdebug',
  datetimeFormat: "DD/MM/YYYY HH:mm",
  dateFormat: "DD/MM/YYYY",
  timeFormat: "HH:mm",
  apiDateTimeFormat: "YYYY-MM-DD",
  currency: '$',
  list: {
    maxItems: 100,
    defaultItems: 10,
  },
  statusMessageDisplayTimeout: 5000,
  planCycleUnlimitedValue: 'UNLIMITED',
  serviceCycleUnlimitedValue: 'UNLIMITED',
  productUnlimitedValue: 'UNLIMITED',
  keyUppercaseRegex: /^[A-Z0-9_]*$/,
  keyRegex: /^[A-Za-z0-9_]*$/,
  defaultLogo: 'billRun-cloud-logo.png',
  billrunCloudLogo: 'billRun-cloud-logo.png',
  billrunLogo: 'billRun-logo.png',
  queue_calculators: ['customer', 'rate', 'pricing'],
  mail_support: 'cloud_support@billrun.com',
  logoMaxSize: 2,
  importMaxSize: 8,
  chargingBufferDays: 5,
  reports: {
    entities: ['lines'/*, 'subscription', 'customer'*/],
    fields: {
      lines: [
        // Default settings \ Example
        // { id: [REQUIRED], type: 'string', filter: true, display: true, groupBy: true, inputConfig: {
        //    inputType: 'select',
        //    options: ['option1', 'option2'] | [{value: 'val', label: 'Label'}, ...] /* array of values or objects */
        //    callback: 'getExampleOptions', /* callback function + should be implementation */
        // } },
        { id: 'urt', type: 'date', filter: true, display: true, groupBy: true },
        { id: 'arate_key', type: 'string', filter: true, display: true, groupBy: true, inputConfig: {
          inputType: 'select',
          callback: 'getProductsOptions'
        } },
        { id: 'arategroup', type: 'string', filter: true, display: true, groupBy: true, inputConfig: {
          inputType: 'select',
          callback: 'getGroupsOptions',
        } },
        { id: 'lastname', type: 'string', filter: true, display: true, groupBy: false },
        { id: 'firstname', type: 'string', filter: true, display: true, groupBy: false },
        { id: 'stamp', type: 'string', filter: true, display: true, groupBy: false },
        { id: 'billrun', type: 'string', filter: true, display: true, groupBy: true, inputConfig: {
          inputType: 'select',
          callback: 'getCyclesOptions',
        } },
        { id: 'in_group', type: 'number', filter: true, display: true, groupBy: false },
        { id: 'aprice', type: 'number', filter: true, display: true, groupBy: false },
        { id: 'file', type: 'string', filter: true, display: true, groupBy: true },
        { id: 'plan', type: 'string', filter: true, display: true, groupBy: true, inputConfig: {
          inputType: 'select',
          callback: 'getPlansOptions',
        } },
        { id: 'billsec', type: 'number', filter: true, display: true, groupBy: false },
        { id: 'sid', type: 'number', filter: true, display: true, groupBy: true },
        { id: 'over_group', type: 'number', filter: true, display: true, groupBy: false },
        { id: 'usagev', type: 'number', filter: true, display: true, groupBy: true },
        { id: 'usaget', type: 'string', filter: true, display: true, groupBy: true, inputConfig: {
          inputType: 'select',
          callback: 'getUsageTypesOptions',
        } },
        { id: 'aid', type: 'number', filter: true, display: true, groupBy: true },
        { id: 'process_time', type: 'string', filter: true, display: true, groupBy: false },
        { id: 'usagesb', type: 'number', filter: true, display: true, groupBy: false },
        { id: 'session_id', type: 'string', filter: true, display: true, groupBy: true },
        { id: 'billrun_pretend', type: 'boolean', filter: true, display: false, groupBy: false },
      ],
    },
    operators: [
      { id: 'eq', title: '==', types: ['string', 'number', 'boolean', 'date'] }, // 'Equals'
      { id: 'ne', title: '!=', types: ['string', 'number', 'boolean'] }, // 'Not equals'
      { id: 'lt', title: '<', types: ['number', 'date'] }, // 'Less than'
      { id: 'lte', title: '<=', types: ['number', 'date'] }, // 'Less than or equals'
      { id: 'gt', title: '>', types: ['number', 'date'] }, // 'Greater than'
      { id: 'gte', title: '>=', types: ['number', 'date'] }, // 'Greater than or equals'
      { id: 'like', title: 'Contains', types: ['string', 'number'] },
      { id: 'starts_with', title: 'Starts with', types: ['string'] },
      { id: 'ends_with', title: 'Ends with', types: ['string'] },
      { id: 'exists', title: 'Exists', types: ['string', 'number', 'boolean', 'date'] },
      { id: 'in', title: 'In', types: ['string', 'number'] },
    ],
    groupByOperators: [
      { id: 'sum', title: 'Sum', types: ['number'] },
      { id: 'avg', title: 'Average', types: ['number'] },
      { id: 'first', title: 'First', types: ['string', 'number', 'boolean', 'date'] },
      { id: 'last', title: 'Last', types: ['string', 'number', 'boolean', 'date'] },
      { id: 'max', title: 'Max', types: ['number', 'date'] },
      { id: 'min', title: 'Min', types: ['number', 'date'] },
      { id: 'push', title: 'List', types: ['string', 'number', 'boolean', 'date'] },
      { id: 'addToSet', title: 'Unique List', types: ['string', 'number', 'boolean', 'date'] },
      { id: 'count', title: 'Count', types: ['string', 'number', 'boolean', 'date'] },
    ],
  },
  systemItems: {
    service: {
      collection: 'services',
      uniqueField: 'name',
      itemName: 'service',
      itemType: 'service',
      itemsType: 'services',
    },
    plan: {
      collection: 'plans',
      uniqueField: 'name',
      itemName: 'plan',
      itemType: 'plan',
      itemsType: 'plans',
    },
    charging_plan: {
      collection: 'prepaidgroups',
      uniqueField: 'name',
      itemName: 'buckets group',
      itemType: 'charging_plan',
      itemsType: 'charging_plans',
    },
    prepaid_plan: {
      collection: 'plans',
      uniqueField: 'name',
      itemName: 'prepaid plan',
      itemType: 'prepaid_plan',
      itemsType: 'prepaid_plans',
    },
    prepaid_include: {
      collection: 'prepaidincludes',
      uniqueField: 'name',
      itemName: 'prepaid bucket',
      itemType: 'prepaid_include',
      itemsType: 'prepaid_includes',
    },
    product: {
      collection: 'rates',
      uniqueField: 'key',
      itemName: 'product',
      itemType: 'product',
      itemsType: 'products',
    },
    discount: {
      collection: 'discounts',
      uniqueField: 'key',
      itemName: 'discount',
      itemType: 'discount',
      itemsType: 'discounts',
    },
    subscription: {
      collection: 'subscribers',
      uniqueField: 'sid',
      itemName: 'subscription',
      itemType: 'subscription',
      itemsType: 'subscriptions',
    },
    customer: {
      collection: 'accounts',
      uniqueField: 'aid',
      itemName: 'customer',
      itemType: 'customer',
      itemsType: 'customers',
    },
    report: {
      collection: 'reports',
      uniqueField: 'key',
      itemName: 'report',
      itemType: 'report',
      itemsType: 'reports',
    },
  },
};
