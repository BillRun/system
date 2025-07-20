import React from 'react';
import { connect } from 'react-redux';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import moment from 'moment';
import { Col, Form, FormGroup, ControlLabel, Panel } from 'react-bootstrap';
import Field from '@/components/Field';
import {
    runOnTitleParser,
    chargeRunOnParser,
    chargeTypeParser,
    chargePayModeParser,
} from '@/common/Parsers';
import {
    getConfig,
    getFieldName,
} from '@/common/Util';


const ChargingDetails = ({ item, dateTimeFormat }) => {

    const schedule = moment(item.get('schedule', null));
    const scheduleDateTime = moment.isMoment(schedule) && schedule.isValid() ? schedule.format(dateTimeFormat) : 'No';
    
    const created = moment(item.get('created', null));
    const createdDateTime = moment.isMoment(created) && created.isValid() ? created.format(dateTimeFormat) : '-';
    
    const starTime = moment(item.get('start_time', null));
    const starDateTime = moment.isMoment(starTime) && starTime.isValid() ? starTime.format(dateTimeFormat) : '-';
    
    const completeTime = moment(item.get('complete_time', null));
    const completeDateTime = moment.isMoment(completeTime) && completeTime.isValid() ? completeTime.format(dateTimeFormat) : '-';
    
    const timeout = moment(item.get('timeout', null));
    const timeoutDateTime = moment.isMoment(timeout) && timeout.isValid() ? timeout.format(dateTimeFormat) : '-';
    
    const cancelTime = moment(item.get('cancel_time', null));
    const cancelDateTime = moment.isMoment(cancelTime) && cancelTime.isValid() ? cancelTime.format(dateTimeFormat) : '-';
    
    const minInvoiceDate = moment(item.getIn(['body', 'config', 'min_invoice_date'], null));
    const minInvoiceDateTime = moment.isMoment(minInvoiceDate) && minInvoiceDate.isValid() ? minInvoiceDate.format(dateTimeFormat) : '-';
    
    return (
        <Form horizontal>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('md5', 'charging_process', 'ID')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={item.get('md5', '-')} />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('schedule', 'charging_process', 'Schedule')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={scheduleDateTime}/>
                </Col>
            </FormGroup>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('handle', 'charging_process', 'Handle')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={item.get('handle', '-')} />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('try', 'charging_process', 'try')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={item.get('try', '-')} />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('is_done', 'charging_process', 'Is Done')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={item.get('done', '') === 1 ? 'Yes' : 'No'} />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('cancelled', 'charging_process', 'Is Cancelled')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={item.get('cancelled', '') === 1 ? 'Yes' : 'No'} />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('created', 'charging_process', 'Created Time')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={createdDateTime} />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('start_time', 'charging_process', 'Start Time')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={starDateTime} />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('complete_time', 'charging_process', 'Complete Time')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={completeDateTime} />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('timeout', 'charging_process', 'Timeout')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={timeoutDateTime} />
                </Col>
            </FormGroup>

            <FormGroup>
                <Col componentClass={ControlLabel} sm={5}>
                    {getFieldName('cancel_time', 'charging_process', 'Cancel Time')}:
                </Col>
                <Col sm={6}>
                    <Field editable={false} value={cancelDateTime} />
                </Col>
            </FormGroup>

            <Panel header="Progress">
                <FormGroup>
                    <Col componentClass={ControlLabel} sm={5}>
                        {getFieldName('total', 'charging_process', 'Total')}:
                    </Col>
                    <Col sm={6}>
                        <Field editable={false} value={item.getIn(['details', 'total'], '-')} />
                    </Col>
                </FormGroup>

                <FormGroup>
                    <Col componentClass={ControlLabel} sm={5}>
                        {getFieldName('done', 'charging_process', 'Done')}:
                    </Col>
                    <Col sm={6}>
                        <Field editable={false} value={item.getIn(['details', 'done'], '-')} />
                    </Col>
                </FormGroup>
            </Panel>

            <Panel header="Config">
                <FormGroup>
                    <Col componentClass={ControlLabel} sm={5}>
                        {getFieldName('mode', 'charging_process', 'Charge Type')}:
                    </Col>
                    <Col sm={6}>
                        <Field editable={false} value={chargeTypeParser(item)} />
                    </Col>
                </FormGroup>

                <FormGroup>
                    <Col componentClass={ControlLabel} sm={5}>
                        {getFieldName('billrun_key', 'charging_process', 'Cycle')}:
                    </Col>
                    <Col sm={6}>
                        <Field editable={false} value={item.getIn(['body', 'config', 'billrun_key'], '-')} />
                    </Col>
                </FormGroup>

                <FormGroup>
                    <Col componentClass={ControlLabel} sm={5}>
                        {getFieldName('pay_mode', 'charging_process', 'Pay Mode')}:
                    </Col>
                    <Col sm={6}>
                        <Field editable={false} value={chargePayModeParser(item)} />
                    </Col>
                </FormGroup>

                <FormGroup>
                    <Col componentClass={ControlLabel} sm={5}>
                        {getFieldName('min_invoice_date', 'charging_process', 'Min Invoice Date')}:
                    </Col>
                    <Col sm={6}>
                        <Field editable={false} value={minInvoiceDateTime} />
                    </Col>
                </FormGroup>

                <FormGroup>
                    <Col componentClass={ControlLabel} sm={5}>
                        {runOnTitleParser(item)}:
                    </Col>
                    <Col sm={6}>
                        {chargeRunOnParser(item)}
                    </Col>
                </FormGroup>
            </Panel>
        </Form>
    );
}

ChargingDetails.defaultProps = {
    item: Immutable.Map(),
};

ChargingDetails.propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
};

const mapStateToProps = (state, props) => ({
    dateTimeFormat: getConfig('datetimeFormat', 'DD/MM/YYYY HH:mm'),
})

export default connect(mapStateToProps)(ChargingDetails);