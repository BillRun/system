webpackHotUpdate(0,{

/***/ 430:
/***/ function(module, exports) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = {
	  entities: ['usage', 'subscription', 'customer', 'logFile', 'queue', 'event', 'bills'],
	  fields: {
	    usage: [// changes to usage will effect on queue
	    // Default settings \ Example
	    // { id: [REQUIRED],  title: 'Demo field', type: 'string', searchable: true, aggregatable: true, inputConfig: {
	    //    inputType: 'select',
	    //    options: ['option1', 'option2'] | [{value: 'val', label: 'Label'}, ...] /* array of values or objects */
	    //    callback: 'getExampleOptions', /* callback function + should be implementation */
	    //    callbackArgument: { 'demo': true },
	    // } },
	    { id: 'urt', type: 'date' }, { id: 'lastname' }, { id: 'firstname' }, { id: 'stamp' }, { id: 'in_group', type: 'number' }, { id: 'full_price', type: 'number', title: 'Plan / Service Full Price' }, { id: 'name', type: 'string', title: 'Plan / Service key' }, { id: 'key', type: 'string', title: 'Discount key' }, { id: 'aprice', type: 'number' }, { id: 'final_charge', type: 'number' }, { id: 'file' }, { id: 'billsec', type: 'number' }, { id: 'sid', type: 'number' }, { id: 'over_group', type: 'number' }, { id: 'usagev', type: 'number' }, { id: 'aid', type: 'number' }, { id: 'process_time', type: 'string' }, { id: 'in_queue', type: 'boolean' }, { id: 'usagesb', type: 'number' }, { id: 'type', title: 'Input processor name / BillRun type', inputConfig: {
	        inputType: 'select',
	        callback: 'getFileTypeOptions',
	        options: [{ value: 'flat', label: 'Subscription' }, 'service', { value: 'credit', label: 'Charge / Refund' }, 'discount', 'all']
	      } }, { id: 'session_id', type: 'string' }, { id: 'billrun_pretend', type: 'boolean' }, { id: 'billrun_status',
	      title: 'Billing cycle status',
	      aggregatable: false,
	      inputConfig: {
	        inputType: 'select',
	        options: [{ value: 'current', label: 'Current' }, { value: 'first_unconfirmed', label: 'First Unconfirmed' }, { value: 'last_confirmed', label: 'Last Confirmed' }, { value: 'confirmed', label: 'Confirmed' }]
	      }
	    }, { id: 'arate_key', inputConfig: { inputType: 'select', callback: 'getProductsOptions' } }, { id: 'arategroup', inputConfig: { inputType: 'select', callback: 'getGroupsOptions' } }, { id: 'billrun', inputConfig: { inputType: 'select', callback: 'getCyclesOptions' } }, { id: 'plan', inputConfig: { inputType: 'select', callback: 'getPlansOptions' } }, { id: 'usaget', inputConfig: { inputType: 'select', callback: 'getUsageTypesOptions' } }, { id: 'pp_includes_name', inputConfig: { inputType: 'select', callback: 'getBucketsOptions' } }, { id: 'pp_includes_external_id', inputConfig: { inputType: 'select', callback: 'getBucketsExternalIdsOptions' } }, { id: 'charging_usaget', inputConfig: { inputType: 'select', callback: 'getUsageTypesOptions' } }, { id: 'balance_before', type: 'number' }, { id: 'balance_after', type: 'number' }, { id: 'balance_normalized', type: 'number' }, { id: 'start', title: 'Proration start date', type: 'date' }, { id: 'end', title: 'Proration end date', type: 'date' }],
	    subscribers: [{ id: 'aid', type: 'number' }, { id: 'sid', type: 'number' }, { id: 'plan', inputConfig: { inputType: 'select', callback: 'getPlansOptions' } }, { id: 'services', inputConfig: { inputType: 'select', callback: 'getServicesOptions' } }, { id: 'plan_activation', type: 'date' }, { id: 'deactivation_date', type: 'date' }, { id: 'play', inputConfig: {
	        inputType: 'select',
	        callback: 'getPlayTypeOptions'
	      } }],
	    account: [{ id: 'aid', type: 'number' }],
	    event: [{ id: 'type',
	      inputConfig: {
	        inputType: 'select',
	        options: ['is', 'in', 'is_not', 'is_less_than', 'is_less_than_or_equal', 'is_greater_than', 'is_greater_than_or_equal', 'reached_constant', 'reached_constant_recurring', 'has_changed', 'has_changed_to', 'has_changed_from']
	      }
	    }, { id: 'aid', type: 'number' }, { id: 'sid', type: 'number' }, { id: 'creation_time', type: 'date' }, { id: 'notify_time', type: 'date' }, { id: 'value' }, { id: 'stamp' }, { id: 'event_code', inputConfig: {
	        inputType: 'select',
	        callback: 'getEventCodeOptions' } }, { id: 'returned_value', searchable: false }],
	    logFile: [{ id: 'file_name', title: 'File name' }, { id: 'stamp', title: 'Unique record ID' }, { id: 'start_process_time', type: 'date' }, { id: 'received_time', type: 'date' }, { id: 'process_time', type: 'date' }, { id: 'logfile_status',
	      columnable: false,
	      title: 'Status',
	      inputConfig: {
	        inputType: 'select',
	        options: [{ value: 'not_processed', label: 'Received' }, { value: 'processing', label: 'Processing' }, { value: 'processed', label: 'Processed' }, { value: 'crashed', label: 'Crashed' }]
	      }
	    }, { id: 'source',
	      title: 'Source',
	      inputConfig: {
	        inputType: 'select',
	        callback: 'getFileTypeOptions'
	      }
	    }],
	    queue: [
	    // use all usage fields
	    { id: 'calc_name',
	      title: 'Calculator Name',
	      inputConfig: {
	        inputType: 'select',
	        callback: 'getCalcNameOptions'
	      }
	    }, { id: 'in_queue_since', type: 'date' }],
	    bills: [{ id: 'type',
	      inputConfig: {
	        inputType: 'select',
	        options: [{ value: 'inv', label: 'Invoice' }, { value: 'rec', label: 'Payment / Transaction' }]
	      },
	      title: 'Type'
	    }, { id: 'cancelled', type: 'boolean', title: 'Cancelled Payment?' }, { id: 'cancel', type: 'string', title: 'Cancelled BillRun Transaction ID' }, { id: 'cancellation', type: 'boolean', title: 'Cancellation Transaction?' }, { id: 'rejected', type: 'boolean', title: 'Rejected Payment?' }, { id: 'rejection', type: 'boolean', title: 'Rejection Transaction?' }, { id: 'aid', type: 'number', title: 'Customer ID' }, { id: 'invoice_id', type: 'number', title: 'Invoice ID' }, { id: 'due_date', type: 'date', title: 'Due Date' }, { id: 'due', type: 'number', title: 'Original Due Date' }, { id: 'payer_name', type: 'string', title: 'Payer Name' }, { id: 'amount', type: 'number', title: 'Original Absolute Due Amount' }, { id: 'lastname', type: 'string', title: 'Customer\'s Last Name' }, { id: 'firstname', type: 'string', title: 'Customer\'s First Name' }, { id: 'payment_method', type: 'string', title: 'Payment Method' }, { id: 'urt', type: 'date', title: 'Creation Time' }, { id: 'invoice_date', type: 'date', title: 'Invoice Date' }, { id: 'total_paid', type: 'number', title: 'Total Paid Amount' }, { id: 'left_to_pay', type: 'number', title: 'Bill Unpaid Amount' }, { id: 'vatable_left_to_pay', type: 'number', title: 'Bill Vatable Left Amount' }, { id: 'waiting_payments', type: 'number', title: 'Pending BillRun\'s Transaction ID' }, { id: 'paid',
	      inputConfig: {
	        inputType: 'select',
	        options: [{ value: '0', label: 'Unpaid' }, { value: '1', label: 'Paid' }, { value: '2', label: 'Awaiting Payment' }]
	      },
	      title: 'Status'
	    }, { id: 'payment_gateway.name',
	      inputConfig: {
	        inputType: 'select',
	        options: [{ value: 'CreditGuard', label: 'CreditGuard' }]
	      },
	      title: 'Payment Gateway'
	    }, { id: 'dir',
	      inputConfig: {
	        inputType: 'select',
	        options: [{ value: 'fc', label: 'From Customer' }, { value: 'tc', label: 'To Customer' }]
	      },
	      title: 'Direction'
	    }, { id: 'gateway_details.four_digits', type: 'string', title: 'Last 4 Digits' }, { id: 'left', type: 'number', title: 'Bill Reserved Amount' }, { id: 'waiting_for_confirmation', type: 'boolean', title: 'Waiting For Confirmation?' }, { id: 'txid', type: 'string', title: 'BillRun Transaction ID' }, { id: 'vendor_response.status', type: 'string', title: 'Vendor Response Status' }, { id: 'last_checked_pending', type: 'date', title: 'Last Status Check' }, { id: 'original_txid', type: 'string', title: 'Original BillRun Transaction ID' }, { id: 'rejection_code', type: 'string', title: 'Rejection Code' }]
	  },
	  conditionsOperators: [{ id: 'last_days', title: 'Last (days)', include: ['fieldid:urt'], type: 'number', suffix: 'Days' }, { id: 'last_days_include_today', title: 'Last (days including today)', include: ['fieldid:urt'], type: 'number', suffix: 'Days' }, { id: 'last_hours', title: 'Last (hours)', include: ['fieldid:urt'], type: 'number', suffix: 'Hours' }, { id: 'eq', title: 'Equals', include: ['date', 'boolean', 'fieldid:billrun_status', 'fieldid:logfile_status'] }, // 'Equals'
	  { id: 'in', title: 'Equals', include: ['string', 'number'], exclude: ['fieldid:billrun_status', 'fieldid:logfile_status'] }, { id: 'ne', title: 'Does not equal', include: ['boolean'], exclude: [] }, // 'Not equals'
	  { id: 'nin', title: 'Does not equal', include: ['string', 'number'], exclude: ['fieldid:billrun_status', 'fieldid:logfile_status'] }, { id: 'lt', title: '<', include: ['number', 'date', 'fieldid:billrun'], exclude: [] }, // 'Less than'
	  { id: 'lte', title: '<=', include: ['number', 'date', 'fieldid:billrun'], exclude: [] }, // 'Less than or equals'
	  { id: 'gt', title: '>', include: ['number', 'date', 'fieldid:billrun'], exclude: [] }, // 'Greater than'
	  { id: 'gte', title: '>=', include: ['number', 'date', 'fieldid:billrun'], exclude: [] }, // 'Greater than or equals'
	  { id: 'like', title: 'Contains', include: ['string', 'number'], exclude: ['fieldid:logfile_status'] }, { id: 'starts_with', title: 'Starts with', include: ['string'], exclude: ['fieldid:logfile_status'] }, { id: 'ends_with', title: 'Ends with', include: ['string'], exclude: ['fieldid:logfile_status'] }, { id: 'in_range', title: 'Include‎', include: ['ranges', 'range'] }, { id: 'nin_range', title: 'Does not include‎', include: ['ranges', 'range'] }, { id: 'exists', title: 'Exists', type: 'boolean',
	    include: ['string', 'number', 'boolean', 'date', 'ranges', 'range'],
	    exclude: ['fieldid:billrun_status', 'fieldid:logfile_status'],
	    options: ['yes', 'no']
	  }],
	  aggregateOperators: [{ id: 'group', title: 'Group', include: ['string', 'number', 'boolean', 'date'], exclude: ['fieldid:count_group'] }, { id: 'sum', title: 'Sum', include: ['number'], exclude: ['fieldid:count_group'] }, { id: 'avg', title: 'Average', include: ['number'], exclude: ['fieldid:count_group'] }, { id: 'first', title: 'First', include: ['string', 'number', 'boolean', 'date', 'ranges', 'range'], exclude: ['fieldid:count_group'] }, { id: 'last', title: 'Last', include: ['string', 'number', 'boolean', 'date', 'ranges', 'range'], exclude: ['fieldid:count_group'] }, { id: 'max', title: 'Max', include: ['number', 'date'], exclude: ['fieldid:count_group'] }, { id: 'min', title: 'Min', include: ['number', 'date'], exclude: ['fieldid:count_group'] }, { id: 'push', title: 'List', include: ['string', 'number', 'boolean', 'date'], exclude: ['fieldid:count_group'] }, { id: 'addToSet', title: 'Unique List', include: ['string', 'number', 'boolean', 'date', 'ranges', 'range'], exclude: ['fieldid:count_group'] }, { id: 'count', title: 'Count', include: ['fieldid:count_group'] }],
	  outputFormats: [{ id: 'date_format', title: 'Date', options: [{ value: 'd/m/Y', label: '31/12/2017' }, { value: 'm/d/Y', label: '12/31/2017' }, { value: 'Y-m-d', label: '2017-12-31' }] }, { id: 'datetime_format', title: 'Date time', options: [{ value: 'd/m/Y H:i', label: '31/12/2017 22:05' }, { value: 'd/m/Y H:i:s', label: '31/12/2017 22:05:59' }, { value: 'm/d/Y h:i A', label: '12/31/2017 10:05 PM' }, { value: 'm/d/Y h:i:s A', label: '12/31/2017 10:05:59 PM' }, { value: 'c', label: 'ISO 8601' }] }, { id: 'date_override', title: 'Subtract / Add time', type: "number", valueTypes: [{ value: 'seconds', label: 'Seconds' }, { value: 'minutes', label: 'Minutes' }, { value: 'hours', label: 'Hours' }, { value: 'days', label: 'Days' }, { value: 'weeks', label: 'Weeks' }, { value: 'months', label: 'Months' }, { value: 'years', label: 'Years' }] }, { id: 'time_format', title: 'Time', options: [{ value: 'H:i', label: '22:05' }, { value: 'H:i:s', label: '22:05:59' }, { value: 'h:i A', label: '10:05 PM' }, { value: 'h:i:s A', label: '10:05:59 PM' }] }, { id: 'multiplication', title: 'Multiply by a number' }, { id: 'default_empty', title: 'Default Value', addOption: true, options: [{ value: 'current_time', label: 'Current Time' }, { value: 'current_start', label: 'Current Billing Cycle Start' }, { value: 'current_end', label: 'Current Billing Cycle End' }, { value: 'first_unconfirmed_start', label: 'First Unconfirmed Billing Cycle Start' }, { value: 'first_unconfirmed_end', label: 'First Unconfirmed Cycle End' }, { value: 'last_confirmed_start', label: 'Last Confirmed Billing Cycle Start' }, { value: 'last_confirmed_end', label: 'Last Confirmed Billing Cycle End' }] }, { id: 'vat_format', title: 'Vat', options: [{ value: 'add_tax', label: 'Add Vat' }, { value: 'remove_tax', label: 'Remove Vat' }] }, { id: 'currency_format', title: 'Currency', options: [{ value: 'suffix', label: '1234$' }, { value: 'prefix', label: '$1234' }] }, { id: 'billing_cycle', title: 'Billing cycle', options: [{ value: 'start', label: 'Start Date' }, { value: 'end', label: 'End Date' }] }]
	};

/***/ }

})