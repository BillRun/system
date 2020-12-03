var globalSetting = {
  storageVersion: '0.0.1',
  //serverUrl : "http://10.162.20.191:1337", // Roman
  //serverUrl : "http://10.162.20.86", // Eran
  // serverUrl : "http://10.162.20.247", // Shani
  serverUrl: "http://billrun",
  //serverUrl: "",
  serverApiVersion: '5.13.2',
  serverApiTimeOut: 300000, // 5 minutes
  serverApiDebug: false,
  serverApiDebugQueryString: 'XDEBUG_SESSION_START=netbeans-xdebug',
  datetimeFormat: "DD/MM/YYYY HH:mm",
  datetimeLongFormat: "DD/MM/YYYY HH:mm:ss",
  dateFormat: "DD/MM/YYYY",
  timeFormat: "HH:mm",
  apiDateFormat: "YYYY-MM-DD",
  apiDateTimeFormat: "YYYY-MM-DD[T]HH:mm:ss.SSS[Z]",
  currency: '$',
  list: {
    maxItems: 100,
    defaultItems: 10,
  },
  statusMessageDisplayTimeout: 5000,
  planCycleUnlimitedValue: 'UNLIMITED',
  serviceCycleUnlimitedValue: 'UNLIMITED',
  productUnlimitedValue: 'UNLIMITED',
  keyUppercaseRegex: /^[A-Z0-9_]+$/,
  keyUppercaseCleanRegex: /[^A-Z|0-9_]/g,
  keyRegex: /^[A-Za-z0-9_]*$/,
  keyCleanRegex: /[^a-z|A-Z|0-9_]/g,
  defaultLogo: 'billRun-cloud-logo.png',
  billrunCloudLogo: 'billRun-cloud-logo.png',
  billrunLogo: 'billRun-logo.png',
  queue_calculators: ['customer', 'rate', 'pricing'],
  mail_support: 'cloud_support@billrun.com',
  logoMaxSize: 2,
  importMaxSize: 8,
  chargingBufferDays: 5,
};
