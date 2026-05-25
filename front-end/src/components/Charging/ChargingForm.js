import React, { useState } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Col, Form } from 'react-bootstrap';
import { ControlLabel, FormGroup, HelpBlock } from '@/common/BootstrapCompat';
import Immutable from 'immutable';
import moment from 'moment';
import Field from '@/components/Field';
import CyclesSelector from '@/components/Cycle/CyclesSelector';
import {
    chargeSelector,
    chargingScheduleListSelector,
} from '@/selectors/chargingSelectors';
import {
    getConfig,
    getFieldName,
    parseIncludeExcludeIdsListValue,
} from '@/common/Util';


const ChargingForm = ({
    updateField,
    removeField,
    item = Immutable.Map(),
    typeOptions,
    runOnOptions,
    datetimeFormat,
    timezone,
    scheduleItemsDates,
    allowStartScheduleRun,
 }) => {
    const [includeDisplay, setDisplayInclude] = useState('');
    const [excludeDisplay, setDisplayExclude] = useState('');

    const onChangeMode = (e) => {
        const { value } = e.target;
        if (value === '') {
            removeField('pay_mode');
        } else {
            updateField('pay_mode', e.target.value);
        }
    };
    const onChangeType = (value) => {
        if (value === '') {
            removeField('mode');
        } else {
            updateField('mode', value);
        }
    }
    const onChangeSelectedCycle = (value) => {
        if (value === '') {
            removeField('billrun_key');
        } else {
            updateField('billrun_key', value);
        }
    }
    const onChangeRunOn = (value) => {
        updateField('run_on', value);
        // setDisplayExclude('');
        // setDisplayInclude('');
        // removeField('include');
        // removeField('exclude');
    };
    const onChangeInclude = (e) => {
        const value = e.target.value;
        setDisplayInclude(value);
        updateField('include', parseIncludeExcludeIdsListValue(value));
    };
    const onChangeExclude = (e) => {
        const value = e.target.value;
        setDisplayExclude(value);
        updateField('exclude', parseIncludeExcludeIdsListValue(value));
    };
    const onChangeMinInvoiceDate = (newDate) => {
        if (moment.isMoment(newDate) && newDate.isValid()) {
            updateField('min_invoice_date', newDate.format());
        } else {
            removeField('min_invoice_date');
        }
    };
    const onChangeScheduler = (newDate) => {
        if (moment.isMoment(newDate) && newDate.isValid()) {
            updateField('schedule', newDate.format());
        } else {
            removeField('schedule');
        }
    };

    const include = item.get('include', []);
    const exclude = item.get('exclude', []);
    const schedulerDate = moment(item.get('schedule', ''));
    const minInvoiceDate = moment(item.get('min_invoice_date', ''));

    const inputProps = {
        fieldType: 'datetime',
        isClearable: false,
        placeholder: 'Select Start Date...',
        minDate: moment(),
        excludeDates: scheduleItemsDates.map(scheduleItemsDate => moment(scheduleItemsDate)).toJS(),
        style: {display: 'grid'},
    };

    return (
        <Form id='charging-wrapper' className="form-horizontal">
            {allowStartScheduleRun && (
                <FormGroup>
                    <Col as={ControlLabel} sm={3}>&nbsp;</Col>
                    <Col sm={6}>
                        <Field
                            fieldType="toggeledInput"
                            value={schedulerDate.isValid() ? schedulerDate : null}
                            onChange={onChangeScheduler}
                            label={getFieldName('is_schedule', 'charging_process', 'Schedule')}
                            inputProps={inputProps}
                            disabledDisplayValue={null}
                            suffix={moment.tz.guess()}
                        />
                    </Col>
                </FormGroup>
            )}
            <FormGroup>
                <Col as={ControlLabel} sm={4}>
                    {getFieldName('mode', 'charging_process', 'Charge Type')}:
                </Col>
                <Col sm={5}>
                    <Field
                        fieldType="select"
                        clearable={false}
                        options={typeOptions}
                        onChange={onChangeType}
                        value={item.get('mode', '')}
                    />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col as={ControlLabel} sm={4} >
                    {getFieldName('billrun_key', 'charging_process', 'Select cycle')}:
                </Col>
                <Col sm={5}>
                    <CyclesSelector
                        onChange={onChangeSelectedCycle}
                        statusesToDisplay={Immutable.List(['past'])}
                        selectedCycles={item.get('billrun_key', '')}
                        timeStatus={true}
                    />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col as={ControlLabel} sm={4} >
                    {getFieldName('pay_mode', 'charging_process', 'Charge Bill Mode')}:
                </Col>
                <Col sm={5}>
                    <Field
                        fieldType="radio"
                        onChange={onChangeMode}
                        name="pay_mode"
                        value="one_payment"
                        label={getFieldName('total_debt', 'charging_process', 'Total Debt')}
                        checked={item.get('pay_mode', '') === 'one_payment'}
                        className="inline"
                    />
                    <Field
                        fieldType="radio"
                        onChange={onChangeMode}
                        name="pay_mode"
                        value="multiple_payments"
                        label={getFieldName('per_bill', 'charging_process', 'Per Bill')}
                        checked={item.get('pay_mode', '') === 'multiple_payments'}
                        className="inline ml10"
                    />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col as={ControlLabel} sm={4}>
                    {getFieldName('min_invoice_date', 'charging_process', 'Minimum Invoice Date')}:
                </Col>
                <Col sm={5}>
                    <Field
                        fieldType="date"
                        value={minInvoiceDate.isValid() ? minInvoiceDate : null}
                        onChange={onChangeMinInvoiceDate}
                    />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col as={ControlLabel} sm={4}>
                    {getFieldName('run_on', 'charging_process', 'Specific Accounts')}:
                </Col>
                <Col sm={5}>
                    <Field
                        fieldType="select"
                        clearable={false}
                        options={runOnOptions}
                        onChange={onChangeRunOn}
                        value={item.get('run_on', '')}
                    />
                </Col>
            </FormGroup>

            {item.get('run_on', '') === 'include' && (
                <FormGroup>
                    <Col as={ControlLabel} sm={4}>
                        {getFieldName('include_aids', 'charging_process', 'Include Customer IDs')}:
                        <HelpBlock><small>Comma \ new line separated numbers</small></HelpBlock>
                    </Col>
                    <Col sm={5}>
                        <Field fieldType="textarea" onChange={onChangeInclude} value={includeDisplay} />
                        {include.length > 0 && (
                            <Field fieldType="json" className="included-excluded-items" value={include} editable={false} />
                        )}
                    </Col>
                </FormGroup>
            )}

            {item.get('run_on', '') === 'exclude' && (
                <FormGroup>
                    <Col as={ControlLabel} sm={4}>
                        {getFieldName('exclude_aids', 'charging_process', 'Exclude Customer IDs')}:
                        <HelpBlock><small>Comma \ new line separated numbers</small></HelpBlock>
                    </Col>
                    <Col sm={5}>
                        <Field fieldType="textarea" onChange={onChangeExclude} value={excludeDisplay}/>
                        {exclude.length > 0 && (
                            <Field fieldType="json" className="included-excluded-items" value={exclude} editable={false} />
                        )}
                    </Col>
                </FormGroup>
            )}
        </Form>
    );
}



ChargingForm.propTypes = ({
    item: PropTypes.instanceOf(Immutable.Map),
});

const mapStateToProps = (state, props) => {
    const scheduleItems = chargingScheduleListSelector(state, props);
    const activeCharge = chargeSelector(state, props);
    const typeOptions = [
        { value: 'all', label: getFieldName('charging_type.all', 'charging_process', 'All')},
        { value: 'charge', label: getFieldName('charging_type.charge', 'charging_process', 'Charge')},
        { value: 'refund', label: getFieldName('charging_type.refund', 'charging_process', 'Refund')},
    ];
    const runOnOptions = [
        { value: 'all', label: getFieldName('run_on.all', 'charging_process', 'All')},
        { value: 'include', label: getFieldName('include_aids', 'charging_process', 'Include')},
        { value: 'exclude', label: getFieldName('exclude_aids', 'charging_process', 'Exclude')},
    ];
    
    const scheduleItemsDates = Immutable.List.isList(scheduleItems)
        ? scheduleItems.map(scheduleItem => scheduleItem.get('schedule', '')) : Immutable.List();

    const maxAllowScheduleCharge = parseInt(getConfig('maxAllowScheduleCharge', 5)) || 5;

    return ({
        typeOptions,
        runOnOptions,
        scheduleItemsDates,
        allowStartRun: !(Immutable.Map.isMap(activeCharge) && activeCharge.get('md5', '') !== ''),
        allowStartScheduleRun: !Immutable.List.isList(scheduleItems) || scheduleItems.size < maxAllowScheduleCharge,
        datetimeFormat: getConfig('datetimeFormat', 'DD/MM/YYYY HH:mm'),
        timezone: state.settings.getIn([ 'billrun','timezone']),
    });
}

export default connect(mapStateToProps)(ChargingForm);