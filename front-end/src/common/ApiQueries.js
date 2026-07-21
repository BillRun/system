import Immutable from 'immutable';
import moment from 'moment';
import isNumber from 'is-number';
import {
  escapeRegExp,
  isValueOn,
} from './Util';


export const getAccountsInvoicesQuery = (aid) => {
  return getEntitesQuery('bills', {
    aid: 1,
    amount: 1,
    invoice_id: 1,
    invoice_date: 1,
    due_before_vat: 1,
  }, { 
    type: 'inv',
    aid: parseFloat(aid),
  }, {
    invoice_id: 1
  });
}

// TODO: fix to uniqueget (for now billAoi can't search by 'rates')
export const searchProductsByKeyAndUsagetQuery = (usages, notKeys = [], plays = '') => {
  const usagesToQuery = Array.isArray(usages) ? usages : [usages];
  const query = {
    key: {
      $nin: [...notKeys, ''], // don't get broken products with empty key
    },
    to: { $gt: moment().toISOString() }, // only active and future
    tariff_category: 'retail', // only retail products
  };

  const additionConditions = []; // for addition conditions in $AND
  if (usagesToQuery[0] !== 'cost') {
    additionConditions.push(
      { $or: usagesToQuery.map(usage => ({ [`rates.${usage}`]: { $exists: true } })) },
    );
  }
  if (plays !== '') {
    additionConditions.push(
      { $or: [
        { play: { $exists: true, $in: [...plays.split(','), '', null] } },
        { play: { $exists: false } },
      ] },
    );
  }
  if (additionConditions.length !== 0) {
    query.$and = additionConditions;
  }

  const formData = new FormData();
  formData.append('collection', 'rates');
  formData.append('size', 99999);
  formData.append('page', 0);
  formData.append('project', JSON.stringify({ key: 1, name: 1, description: 1 }));
  formData.append('query', JSON.stringify(query));

  return {
    api: 'find',
    options: {
      method: 'POST',
      body: formData,
    },
  };
};

export const saveQuery = body => ({
  api: 'save',
  options: {
    method: 'POST',
    body,
  },
});

export const getCurrenciesQuery = () => ({
  api: 'currencies',
  params: [
    { simpleArray: true },
  ],
});

export const getPaymentGatewaysQuery = () => ({
  api: 'paymentgateways',
  action: 'list',
});

export const getUserLoginQuery = (username, password, protocol = 'Internal', provider = null) => {
  const formData = new FormData();
  if (username) {
    formData.append('username', username);
  }
  if (password) {
    formData.append('password', password);
  }
  
  const params = [{ protocol }];
  if (provider) {
    params.push({ provider });
  }

  return ({
    api: 'Auth',     
    action: 'login', 
    params,
    options: {
      method: 'POST',
      body: formData, 
    },
  });
};

export const getUserLogoutQuery = (protocol = 'Internal') => ({
  api: 'Auth',
  action: 'logout',
  params: [
    { protocol },
  ],
});

export const getUserCheckLoginQuery = () => ({
  api: 'Auth',
  action: 'login',
  params: [
    { protocol: 'Internal' },
  ],
});

export const getAuthOptionsQuery = () => ({
  api: 'Auth',
  action: 'options',
});

export const saveFileQuery = (file, metadata) => {
  const formData = new FormData();
  formData.append('action', 'save');
  formData.append('metadata', JSON.stringify(metadata));
  formData.append('query', JSON.stringify({ filename: 'file' }));
  formData.append('file', file);
  return ({
    api: 'files',
    name: 'saveFile',
    options: {
      method: 'POST',
      body: formData,
    },
  });
};

export const getFileQuery = query => ({
  api: 'files',
  params: [
    { action: 'read' },
    { query: JSON.stringify(query) },
  ],
});

export const saveSettingsQuery = (data, category) => {
  const formData = new FormData();
  formData.append('category', category);
  formData.append('action', 'set');
  formData.append('data', JSON.stringify(data));
  return ({
    api: 'settings',
    name: category,
    options: {
      method: 'POST',
      body: formData,
    },
  });
};

export const getSettingsQuery = (category, data = {}) => ({
  api: 'settings',
  name: category,
  params: [
    { category },
    { data: JSON.stringify(data) },
  ],
});

export const sendTestSmsQuery = (recipient) => {
  const formData = new FormData();
  formData.append('recipient', recipient);

  return ({
    api: 'testsms',
    options: {
      method: 'POST',
      body: formData,
    },
  });
};

export const setInputProcessorQuery = (data, action) => {
  const formData = new FormData();
  formData.append('category', 'file_types');
  formData.append('action', action);
  formData.append('data', JSON.stringify(data));
  return ({
    api: 'settings',
    options: {
      method: 'POST',
      body: formData,
    },
  });
};

export const getInputProcessorActionQuery = (fileType, action) => ({
  api: 'settings',
  params: [
    { category: 'file_types' },
    { action },
    { data: JSON.stringify({ file_type: fileType }) },
  ],
});

export const getExportGeneratorActionQuery = (name, action) => ({
  api: 'settings',
  params: [
    { category: 'export_generators' },
    { action },
    { data: JSON.stringify({ name }) },
  ],
});

export const saveExportGeneratorQuery = generator => saveSettingsQuery(generator, 'export_generators');

export const getCreditChargeQuery = params => ({
  api: 'credit',
  params,
});

/* List Components queries */
export const usageListQuery = (query, page, sort, size) => ({
  entity: 'lines',
  action: 'get',
  params: [
    { size },
    { page },
    { sort: JSON.stringify(sort) },
    { query: JSON.stringify(query) },
  ],
});

export const queueListQuery = (query, page, sort, size) => ({
  entity: 'queue',
  action: 'get',
  params: [
    { size },
    { page },
    { sort: JSON.stringify(sort) },
    { query: JSON.stringify(query) },
  ],
});

export const prepaidBalancesListQuery = (query, page, sort, size) => ({
  entity: 'balances',
  action: 'get',
  params: [
    { size },
    { page },
    { sort: JSON.stringify(sort) },
    { query: JSON.stringify(query) },
  ],
});

export const postpaidBalancesListQuery = (query, page, sort, size) => ({
  entity: 'balances',
  action: 'get',
  params: [
    { size },
    { page },
    { sort: JSON.stringify(sort) },
    { query: JSON.stringify(query) },
  ],
});

/* Settings API */
export const savePaymentGatewayQuery = gateway => {
  const formData = new FormData();
  formData.append('category', 'payment_gateways');
  formData.append('action', 'set');
  formData.append('data', JSON.stringify(gateway));

  return ({
    api: 'settings',
    options: {
      method: 'POST',
      body: formData,
    },
  });
};

/* Settings API */
export const saveSharedSecretQuery = secret => ({
  api: 'settings',
  params: [
    { category: 'shared_secret' },
    { action: 'set' },
    { data: JSON.stringify(secret) },
  ],
});

export const disableSharedSecretQuery = key => ({
  api: 'settings',
  params: [
    { category: 'shared_secret' },
    { action: 'unset' },
    { data: JSON.stringify({ key }) },
  ],
});

export const disablePaymentGatewayQuery = name => ({
  api: 'settings',
  params: [
    { category: 'payment_gateways' },
    { action: 'unset' },
    { data: JSON.stringify({ name }) },
  ],
});


/* BillAPI */
export const apiEntityQuery = (collection, action, body) => ({
  entity: collection,
  action,
  timeout: 3600000, // 60 minutes
  options: {
    method: 'POST',
    body,
  },
});

export const getEntityCSVQuery = (entity, params) => ({
  action: 'export',
  entity,
  params,
  options: {
    method: 'GET',
  },
});


export const getGroupsQuery = collection => ({
  action: 'uniqueget',
  entity: collection,
  params: [
    { query: JSON.stringify({
      'include.groups': { $exists: true },
    }) },
    { project: JSON.stringify({
      name: 1,
      include: 1,
    }) },
    { page: 0 },
    { size: 9999 },
  ],
});

export const getPlaysQuery = () => ({
  action: 'uniqueget',
  entity: 'subscribers',
  params: [
    { query: JSON.stringify({}) },
    { project: JSON.stringify({
      play: 1,
    }) },
    { page: 0 },
    { size: 9999 },
  ],
});

export const getEntityByIdQuery = (collection, id) => ({
  action: 'get',
  entity: collection,
  params: [
    { query: JSON.stringify({ _id: id }) },
    { page: 0 },
    { size: 1 },
  ],
});

export const getEntitesQuery = (collection, project = {}, query = {}, sort = null, options = {}, size = 9999) => {
  let action;
  switch (collection) {
    case 'users':
    case 'suggestions':
    case 'bills':
      action = 'get';
      break;
    default:
      action = 'uniqueget';
  }
  const sortBy = sort !== null ? sort : Immutable.fromJS(project).filter(prop => prop === 1);
  return ({
    action,
    entity: collection,
    params: [
      { page: 0 },
      { size },
      { query: JSON.stringify(query) },
      { project: JSON.stringify(project) },
      { sort: JSON.stringify(sortBy) },
      { options: JSON.stringify(options) },
    ],
  });
};

export const getPlansByTypeQuery = (type, project = { name: 1, description: 1 }) => {
  const query = {
    connection_type: {
      $exists: true,
    },
  };
  if (type !== '') {
    query.connection_type.$eq = type;
  }
  return getEntitesQuery('plans', project, query);
};

export const getDeleteLineQuery = id => ({
  action: 'delete',
  entity: 'lines',
  params: [
    { query: JSON.stringify({ _id: id }) },
  ],
});


// List
export const getAccountsQuery = (project = { aid: 1, firstname: 1, lastname: 1 }) =>
  getEntitesQuery('accounts', project, {type: 'account'});
export const getSubscriptionsWithAidQuery = (project = { aid: 1, sid: 1, firstname: 1, lastname: 1 }) =>
  getEntitesQuery('subscribers', project, {type: 'subscriber'});
export const getSubscribersByAidQuery = (aid) =>
  getEntitesQuery('subscribers', { aid: 1, sid: 1, firstname: 1, lastname: 1 }, {type: 'subscriber', aid}, { sid: 1 });
export const getPlansQuery = (project = { name: 1 }) => getEntitesQuery('plans', project);
export const getServicesQuery = (project = { name: 1 }) => getEntitesQuery('services', project);
export const getServicesKeysWithInfoQuery = () => getEntitesQuery('services', { name: 1, description: 1, play: 1, quantitative: 1, balance_period: 1 }, {}, { name: 1 	});
export const getPrepaidIncludesQuery = () => getEntitesQuery('prepaidincludes');
export const getProductsKeysQuery = (project = { key: 1, description: 1, play: 1 }, query = {}, sort = { key: 1 }) =>
  getEntitesQuery('rates', project, query, sort);
export const getRetailProductsKeysQuery = (project = { key: 1, description: 1 }) => {
  const query = { tariff_category: 'retail' };
  return getEntitesQuery('rates', project, query);
};
export const getRetailProductsWithRatesQuery = () =>
  getRetailProductsKeysQuery({ key: 1, description: 1, rates: 1 });
export const getProductsWithRatesQuery = () =>
  getProductsKeysQuery({ key: 1, description: 1, rates: 1 });
export const getServicesKeysQuery = () => getEntitesQuery('services', { name: 1 });
export const getIncludedServicesKeysQuery = () => getEntitesQuery('services', { name: 1, play: 1 }, {
  quantitative: { $ne: true },
  balance_period: { $exists: false },
});
export const getPlansKeysQuery = (project = { name: 1, description: 1 }, query = {}, sort = { name: 1 }) => getEntitesQuery('plans', project, query, sort);
export const getUserKeysQuery = () => getEntitesQuery('users', { username: 1 });
export const getAllGroupsQuery = () => ([
  getGroupsQuery('plans'),
  getGroupsQuery('services'),
]);
export const getBucketGroupsQuery = () => getEntitesQuery('prepaidgroups');
export const getTaxRatesQuery = getEntitesQuery('taxes', { key: 1, description: 1 });
// By ID
export const fetchServiceByIdQuery = id => getEntityByIdQuery('services', id);
export const fetchProductByIdQuery = id => getEntityByIdQuery('rates', id);
export const fetchPrepaidIncludeByIdQuery = id => getEntityByIdQuery('prepaidincludes', id);
export const fetchDiscountByIdQuery = id => getEntityByIdQuery('discounts', id);
export const fetchChargeByIdQuery = id => getEntityByIdQuery('charges', id);
export const fetchReportByIdQuery = id => getEntityByIdQuery('reports', id);
export const fetchtaxeByIdQuery = id => getEntityByIdQuery('taxes', id);
export const fetchPlanByIdQuery = id => getEntityByIdQuery('plans', id);
export const fetchPrepaidGroupByIdQuery = id => getEntityByIdQuery('prepaidgroups', id);
export const fetchUserByIdQuery = id => getEntityByIdQuery('users', id);
export const fetchAutoRenewByIdQuery = id => getEntityByIdQuery('autorenew', id);

export const getProductByKeyQuery = key => ({
  action: 'uniqueget',
  entity: 'rates',
  params: [
    { query: JSON.stringify({ key: { $regex: `^${key}$` } }) },
    { page: 0 },
    { size: 1 },
  ],
});

export const searchProductsByKeyQuery = (key, project = {}) => ({
  action: 'uniqueget',
  entity: 'rates',
  params: [
    { page: 0 },
    { size: 9999 },
    { project: JSON.stringify(project) },
    { sort: JSON.stringify(project) },
    { query: JSON.stringify({
      key: { $regex: key, $options: 'i' },
    }) },
    { states: JSON.stringify([0, 1]) },
  ],
});

export const searchPlansByKeyQuery = (name, project = {}) => ({
  action: 'uniqueget',
  entity: 'plans',
  params: [
    { page: 0 },
    { size: 9999 },
    { project: JSON.stringify(project) },
    { sort: JSON.stringify(project) },
    { query: JSON.stringify({
      name: { $regex: name, $options: 'i' },
    }) },
    { states: JSON.stringify([0]) },
  ],
});

export const runningPaymentFilesListQuery = (paymentGateway, fileType) => ({
  action: 'get',
  entity: 'log',
  params: [
    { page: 0 },
    { size: 9999 },
    { project: JSON.stringify({ stamp: 1}) },
    { sort: JSON.stringify({}) },
    { query: JSON.stringify({
      cpg_name: { $in: [paymentGateway]},
      cpg_file_type: { $in: [fileType]},
      start_process_time: { $exists: true },
      process_time: { $exists: false },
    }) },
  ],
});

export const runningRequestPaymentFilesListQuery = (paymentGateway, fileType) => 
  runningPaymentFilesListQuery(paymentGateway, fileType);

export const runningResponsePaymentFilesListQuery = (paymentGateway, fileType) => ({
  action: 'get',
  entity: 'log',
  params: [
    { page: 0 },
    { size: 9999 },
    { project: JSON.stringify({ stamp: 1}) },
    { sort: JSON.stringify({}) },
    { query: JSON.stringify({
      cpg_name: { $in: [paymentGateway]},
      pg_file_type: fileType,
      start_process_time: { $exists: true },
      process_time: { $exists: false },
    }) },
  ],
});

export const sendGenerateNewFileQuery = (paymentGateway, fileType, data) => {
  const params = [
    { payment_gateway: paymentGateway },
    { file_type: fileType },
    { parameters: JSON.stringify(data) },
  ];
  return {
    api: 'custompaymentgateway',
    action: 'generateTransactionsRequestFile',
    params,
  };
}

export const sendTransactionsReceiveFileQuery = (paymentGateway, fileType, file, paymentsFileType, source) => {
  const formData = new FormData();
  formData.append('payment_gateway', paymentGateway);
  formData.append('file_type', fileType);
  formData.append('payments_file_type', paymentsFileType);
  // `source` is the value persisted on `log.source` and looked up by the list query.
  // Pass it explicitly so FE owns the naming contract and BE doesn't need to reconstruct it
  // from `payment_gateway + payments_file_type` (which mis-handled snake_case gateway keys).
  if (typeof source !== 'undefined') {
    formData.append('source', source);
  }
  formData.append('file', file);
  return ({
    api: 'uploadfile',
    options: {
      method: 'POST',
      body: formData,
    },
  });
}

export const generateOneTimeInvoiceQuery = (aid, lines, invoiceType = 'without_charge', sendMail = false, note = '', invoiceUnixtime = '') => {
  const cdrs = lines
    .map(line => Immutable.Map({
      aid: aid,
      sid: line.get('sid', ''),
      rate: line.get('rate', ''),
      credit_time: line.get('date', ''),
      usagev: line.get('volume', ''),
      type: line.get('type', ''),
      aprice: line.get('price', ''),
    }).filter(val => val !== ''));
  const params = [
    { cdrs: JSON.stringify(cdrs) },
    { aid },
    { send_email: sendMail ? 1 : 0 },
  ];
  const adjusts = lines
    .map(line => Immutable.Map({
      invoice_id: line.get('inv_id', ''),
      amount: line.get('price', ''),
    }))
    .filter(adjust => adjust.get('invoice_id', '') !== '');
  if (adjusts && !adjusts.isEmpty()) {
    params.push({ adjusts: JSON.stringify(adjusts) });
  }
  if (typeof note === 'string' && note.length > 0) {
      params.push({ note });
  }
  if (isNumber(invoiceUnixtime)) {
      params.push({ invoice_unixtime: invoiceUnixtime });
  }
  if (typeof lines === 'string' && lines.length > 0) {
      params.push({ lines });
  }
  if (invoiceType === 'without_charge') {
    params.push({ step: 1 });
    params.push({ allow_bill: 1 });
  } else if (invoiceType === 'charge') {
    params.push({ step: 2 });
    params.push({ allow_bill: 1 });
  } else if (invoiceType === 'successful_charge') {
    params.push({ step: 2 });
    params.push({ allow_bill: 1 });
    params.push({ charge_flow: 'charge_before_invoice' });
  } else if (invoiceType === 'expected') {
    params.push({ step: 0 });
    params.push({ expected: 1 });
  } else if (invoiceType === 'download_expected') {
    params.push({ step: 0 });
    params.push({ expected: 1 });
    params.push({ send_back_invoices: 1 });
  }
  return {
    api: 'onetimeinvoice',
    params,
  };
}

export const generateOneTimeInvoiceDownloadExpectedQuery = (aid, lines, note = '', invoiceUnixtime = '') =>
  generateOneTimeInvoiceQuery(aid, lines, 'download_expected', false, note, invoiceUnixtime);

export const generateOneTimeInvoiceExpectedQuery = (aid, lines, note = '', invoiceUnixtime = '') =>
  generateOneTimeInvoiceQuery(aid, lines, 'expected', false, note, invoiceUnixtime);

export const auditTrailListQuery = (query, page, fields, sort, size) => ({
  action: 'get',
  entity: 'audit',
  params: [
    { size },
    { page },
    { project: JSON.stringify(fields) },
    { sort: JSON.stringify(sort) },
    { query: JSON.stringify(query) },
  ],
});

export const getEntitesByKeysQuery = (entity, keyField, keys, project = {}) => {
  const formData = new FormData();
  formData.append('page', 0);
  formData.append('size', 9999);
  formData.append('project', JSON.stringify(project));
  formData.append('sort', JSON.stringify(project));
  formData.append('query', JSON.stringify({
    [keyField]: { $in: keys },
  }));
  return ({
    action: 'uniqueget',
    entity,
    options: {
      method: 'POST',
      body: formData,
    },
  });
}
export const getServicesByKeysQuery = (keys, project = {}) => getEntitesByKeysQuery('services', 'name', keys, project);
export const getProductsByKeysQuery = (keys, project = {}) => getEntitesByKeysQuery('rates', 'key', keys, project);

export const getEntityRevisionsQuery = (collection, revisionByFields, values, size = 9999) => {
  const query = revisionByFields.reduce((queryObj, revisionByField, idx) => {
    let value = values.get(idx, '');
    if (typeof value === 'string') {
      value = escapeRegExp(value);
    }
    switch (collection) {
      case 'subscribers':
        return queryObj.set(revisionByField, value);
      default: {
        return queryObj.set(revisionByField, { $regex: `^${value}$` });
      }
    }
  }, Immutable.Map({}));

  const baseProject = {
    from: 1,
    to: 1,
    description: 1,
    revision_info: 1,
  };
  const project = revisionByFields.reduce((projectObj, revisionByField) =>
    projectObj.set(revisionByField, 1), Immutable.Map(baseProject));

  return ({
    action: 'get',
    entity: collection,
    params: [
      { sort: JSON.stringify({ from: -1 }) },
      { query: JSON.stringify(query) },
      { project: JSON.stringify(project) },
      { page: 0 },
      { size },
      { state: JSON.stringify([0, 1, 2]) },
    ],
  });
};

export const getRebalanceAccountQuery = (aid, billrunKey = '', rate = '') => {
  const params = [{ aid }];
  if (billrunKey !== '') {
    params.push({ billrun_key: billrunKey });
  }
  if (rate !== '') { //BRCD-1396
    params.push({ query: JSON.stringify([[{
      field_name : 'arate_key',
      op : '$eq',
      value : rate
    }]]) });
  }
  return {
    api: 'resetlines',
    params,
  };
};

export const getCyclesQuery = (from, to, newestFirst = true, timeStatus = false) => {
  const params = {
    api: 'billrun',
    action: 'cycles',
    params:[]
  }
  if(from) {
      params['params'].push({from});
  }
  if(to) {
      params['params'].push({to});
  }
  params['params'].push({newestFirst: newestFirst? 1 : 0});
  params['params'].push({timeStatus: timeStatus ? 1 : 0 });
  return params;
};

export const getCycleQuery = billrunKey => ({
  api: 'billrun',
  action: 'cycle',
  params: [
    { stamp: billrunKey },
  ],
});

export const getRunCycleQuery = (billrunKey, rerun, generatePdf) => ({
  api: 'billrun',
  action: 'completecycle',
  params: [
    { stamp: billrunKey },
    { rerun },
    { generate_pdf: generatePdf },
  ],
});

export const getResetCycleQuery = billrunKey => ({
  api: 'billrun',
  action: 'resetcycle',
  params: [
    { stamp: billrunKey },
  ],
});

export const getConfirmCycleInvoiceQuery = (billrunKey, invoiceId) => ({
  api: 'billrun',
  action: 'confirmCycle',
  params: [
    { stamp: billrunKey },
    { invoices: invoiceId },
  ],
});

export const getConfirmCycleAllQuery = billrunKey => ({
  api: 'billrun',
  action: 'confirmCycle',
  params: [
    { stamp: billrunKey },
  ],
});

export const getChargeAllCycleQuery = () => ({
  api: 'billrun',
  action: 'chargeaccount',
});

export const getAllInvoicesQuery = billrunKey => ({
  action: 'get',
  entity: 'billrun',
  params: [
    { query: JSON.stringify({ billrun_key: billrunKey }) },
    { project: JSON.stringify({ _id: 1 }) },
  ],
});

export const getChargeStatusQuery = () => ({
  api: 'billrun',
  action: 'chargestatus',
});

export const getOperationsQuery = () => ({
  api: 'operations',
  params: [
    { action: 'charge_account' },
    { filtration: 'all' },
  ],
});

export const getCollectionDebtQuery = aid => ({
  api: 'bill',
  params: [
    { aid },
  ],
});

export const getOfflinePaymentQuery = (method, aid, amount, payerName, chequeNo, dir, uf, note, urt) => {
  const payment = {
    amount,
    aid,
    payer_name: payerName,
    dir,
    deposit_slip: '',
    deposit_slip_bank: '',
    cheque_no: chequeNo,
    source: 'web',
  };
  if (urt !== '') {
    payment['urt'] = urt;
  }
  if ( note !== '') {
    payment['note'] = note;
  }
  if (uf !== undefined && uf.size !== 0) {
    payment['uf'] = uf;
  }
  return {
    api: 'pay',
    params: [
      { method },
      { payments:  JSON.stringify([payment])},
    ]
  }
};

export const getConfirmationOperationAllQuery = () => ({
  api: 'operations',
  params: [
    { action: 'confirm_cycle' },
    { filtration: 'all' },
  ],
});

export const getConfirmationOperationInvoiceQuery = invoiceId => ({
  api: 'operations',
  params: [
    { action: 'confirm_cycle' },
    { filtration: invoiceId },
  ],
});

export const sendResetMailQuery = email => ({
  api: 'passwordretrieval',
  params: [
    { action: 'sendForm' },
    { email },
  ],
});

export const changePasswordQuery = (itemId, signature, timestamp, password) => ({
  action: 'changepassword',
  entity: 'users',
  params: [
    { query: JSON.stringify({ _id: itemId }) },
    { update: JSON.stringify({ password }) },
    { _sig_: signature },
    { _t_: timestamp },
  ],
});

export const getReportQuery = ({ report, page = 0, size = 10 }) => ({
  api: 'report',
  params: [
    { action: 'generateReport' },
    { report: JSON.stringify(report) },
    { page },
    { size },
  ],
});

export const getReportCSV = ({ report, page = 0, size = 10 }) => ({
  api: 'report',
  params: [
    { action: 'exportCSVReport' },
    { report: JSON.stringify(report) },
    { page },
    { size },
  ],
});

export const getReportCSVQuery = name => ({
  api: 'report',
  params: [
    { action: 'exportCSV' },
    { report: name },
  ],
});

export const getExpectedInvoiceQuery = ( aid, billrunKey ) => ({
  api: 'accountinvoices',
  params: [
    { action: 'expected_invoice' },
    { aid },
    { billrun_key: billrunKey },
  ],
});


// Dashboard reports queries
export const getDashboardQuery = action => ({
  api: 'reports',
  params: [
    { action },
  ],
});
// Dashboard reports queries - end


/** Workers */

export const getWorkersQuery = () => ({
  api: 'billrun',
  action: 'workerstatus',
});

export const pushToCycleQueueQuery = (billrun_key, generate_pdf, include_aids = [], exclude_aids = []) => {
  let config = { billrun_key };
  if (include_aids.length > 0) {
    config['include'] = include_aids;
  }
  if (exclude_aids.length > 0) {
    config['exclude'] = exclude_aids;
  }

  const formData = new FormData();
  formData.append('job_type', 'Cycle');
  formData.append('generate_pdf', isValueOn(generate_pdf) ? 1 : 0);
  formData.append('config', JSON.stringify(config));

  return ({
    api: 'queue',
    action: 'push',
    options: {
      method: 'POST',
      body: formData,
    },
  });
}

export const getChargesQuery = (limit = 1000) => {
    const formData = new FormData();
    formData.append('job_type', 'Charging');
    formData.append('limit', limit);
    formData.append('include_cancelled', true);

    return ({
      api: 'queue',
      action: 'latestjob',
      options: {
        method: 'POST',
        body: formData,
      },
    });
}

export const getChargesScheduleQuery = () => {
    const formData = new FormData();
    formData.append('job_type', 'Charging');
    formData.append('future_only', true);

    return ({
      api: 'queue',
      action: 'latestjob',
      options: {
        method: 'POST',
        body: formData,
      },
    });
}

export const getChargeQuery = (md5) => {
    const formData = new FormData();
    formData.append('job_md5', md5);

    return ({
      api: 'queue',
      action: 'parentjobstats',
      options: {
        method: 'POST',
        body: formData,
      },
    });
}

export const getChargeCancelQuery = (md5) => {
    const formData = new FormData();
    formData.append('job_md5', md5);

    return ({
      api: 'queue',
      action: 'canceljob',
      options: {
        method: 'POST',
        body: formData,
      },
    });
}

export const getChargeCreateQuery = (data, scheduler) => {
    const formData = new FormData();
    formData.append('job_type', 'Charging');
    formData.append('config', JSON.stringify(data));
    if (scheduler !== false) {
        formData.append('schedule', scheduler);
    }

    return ({
      api: 'queue',
      action: 'push',
      options: {
        method: 'POST',
        body: formData,
      },
    });
}

export const pushToConfirmQueueQuery = (billrun_key, include_aids = [], exclude_aids = []) => {
  let config = { billrun_key };
  if (include_aids.length > 0) {
    config['include'] = include_aids;
  }
  if (exclude_aids.length > 0) {
    config['exclude'] = exclude_aids;
  }

  const formData = new FormData();
  formData.append('job_type', 'Confirm');
  formData.append('config', JSON.stringify(config));

  return ({
    api: 'queue',
    action: 'push',
    options: {
      method: 'POST',
      body: formData,
    },
  });
}

export const getExternalLoginQuery = (protocol, returnTo, provider) => {
  const params = [
    { protocol },
    { return_to: returnTo },
  ];
  if (provider) {
    params.push({ provider });
  }
  return ({
    api: 'Auth',
    action: 'login',
    params,
  });
};
