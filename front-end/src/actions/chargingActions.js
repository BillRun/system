import Immutable from 'immutable';
import moment from 'moment';
import { apiBillRun, apiBillRunErrorHandler, apiBillRunSuccessHandler } from '../common/Api';
import { startProgressIndicator, dismissProgressIndicator } from './progressIndicatorActions';
import {
    apiTimeOutMessage,
    gotEntity,
    clearEntity,
} from '@/actions/entityActions';
import {
    getList,
    clearList,
} from '@/actions/listActions';

import {
    getChargeQuery,
    getChargesQuery,
    getChargesScheduleQuery,
    getChargeCancelQuery,
    getChargeCreateQuery,
} from '@/common/ApiQueries';
import {
    getChargeStatus,
    getConfig,
} from '@/common/Util';


export const getCharges = size => dispatch => dispatch(getList('charges', getChargesQuery(size)));

export const getChargesSchedule = () => dispatch => dispatch(getList('charges_schedule', getChargesScheduleQuery()));

export const clearCharges = () => dispatch => dispatch(clearList('charges'));

export const getChargeDetails = item => dispatch => {
    dispatch(startProgressIndicator());
    const md5 = item.get('md5', '');
    return apiBillRun(getChargeQuery(md5), { timeOutMessage: apiTimeOutMessage })
        .then((success) => {
            debugger;
            dispatch(dismissProgressIndicator());
            const mergedItem = Immutable.fromJS({...item.toJS(), details: {...success.data[0].data.details}});
            if (item.get('active', false)) {
                dispatch(setRemoveActive(mergedItem));
            }
            return mergedItem;
        })
        .catch(error => {
            dispatch(dismissProgressIndicator());
            return false
        });
}

const setRemoveActive = charge => dispatch => {
    if (getChargeStatus(charge) === 'active') {
        return dispatch(gotEntity('charging_process', charge));
    } else {
        return dispatch(clearCharge());
    }
}

export const setActiveCharge = item => dispatch => 
    dispatch(getChargeDetails(item)).then((charge) => dispatch(setRemoveActive(charge)));

export const clearCharge = () => dispatch => dispatch(clearEntity('charging_process'));

export const cancelCharge = item => dispatch => 
    apiBillRun(getChargeCancelQuery(item.get('md5', '')), { timeOutMessage: apiTimeOutMessage })
        .then((success) => {
            dispatch(apiBillRunSuccessHandler(success, 'Charge successfully canceled'))
            return dispatch(getCharges());
        })
        .catch(error => dispatch(apiBillRunErrorHandler(error, 'Error cancel charge')));

export const startCharge = item => dispatch => {
    const datetimeFormat = getConfig('datetimeFormat', '');
    const apiDateTimeFormat = getConfig('apiDateTimeFormat', '')
    const schedulerDate = moment(item.get('schedule', ''), datetimeFormat);
    const minInvoiceDate = moment(item.get('min_invoice_date', ''), datetimeFormat);
    const runOn = item.get('run_on', '');
    // Update charge details
    let charge = item
        .delete('schedule')
        .delete('run_on');
    if (runOn === 'all') {
        charge = charge.delete('include').delete('exclude');
    }
    if (runOn === 'include') {
        charge = charge.delete('exclude');
    }
    if (runOn === 'exclude') {
        charge = charge.delete('exclude');
    }
    if (minInvoiceDate.isValid()) {
        charge = charge.set('min_invoice_date', minInvoiceDate.utc().format(apiDateTimeFormat));
    }
    const scheduler = schedulerDate.isValid() ? schedulerDate.utc().format(apiDateTimeFormat) : false;
    return apiBillRun(getChargeCreateQuery(charge, scheduler), { timeOutMessage: apiTimeOutMessage })
        .then((success) => {
            dispatch(apiBillRunSuccessHandler(success, 'New charge successfully pushed'))
            dispatch(getCharges());
            return true;
        })
        .catch(error => {
            dispatch(apiBillRunErrorHandler(error));
            return false;
        });
}
