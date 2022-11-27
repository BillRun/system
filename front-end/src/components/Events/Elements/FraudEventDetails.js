import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { FormGroup, Col, ControlLabel, InputGroup, DropdownButton, MenuItem, HelpBlock } from 'react-bootstrap';
import Field from '@/components/Field';
import {
  gitTimeOptions,
  gitPeriodLabel,
} from '../EventsUtil';
import {
  validateFieldEventCode,
  validateFieldRecurrenceValue,
  validateFieldDateRangeValue,
} from '@/actions/eventActions';


const FraudEventDetails = ({ item, eventsSettings, errors, onUpdate, setError }) => {
  const recurrenceUnit = item.getIn(['recurrence', 'type'], '');
  const recurrenceUnitTitle = gitPeriodLabel(recurrenceUnit);
  const recurrenceOptions = gitTimeOptions(recurrenceUnit);
  const dateRangeUnit = item.getIn(['date_range', 'type'], '');
  const dateRangeUnitTitle = gitPeriodLabel(dateRangeUnit);
  const dateRangeOptions = gitTimeOptions(dateRangeUnit);
  const globalAddresses = eventsSettings.getIn(['email', 'global_addresses'], Immutable.List());

  const onChaneEventCode = (e) => {
    const { value } = e.target;
    onUpdate(['event_code'], value);
    const isValid = validateFieldEventCode(value);
    setError('event_code', isValid === true ? null : isValid);
  };
  const onChaneEventDescription = (e) => {
    const { value } = e.target;
    onUpdate(['event_description'], value);
  };
  const onChangeLinesOverlap = (e) => {
    const { value } = e.target;
    onUpdate(['lines_overlap'], value);
  };
  const onChangeRecurrenceValue = (value) => {
    onUpdate(['recurrence', 'value'], value);
    const isValid = validateFieldRecurrenceValue(value);
    setError('recurrence.value', isValid === true ? null : isValid);
  };
  const onChangeRecurrenceType = (value) => {
    onUpdate(['recurrence', 'type'], value);
    onUpdate(['recurrence', 'value'], '');
  };
  const onChangeDateRangeType = (value) => {
    onUpdate(['date_range', 'type'], value);
    onUpdate(['date_range', 'value'], '');
  };
  const onChangeDateRangeValue = (value) => {
    onUpdate(['date_range', 'value'], value);
    const isValid = validateFieldDateRangeValue(value);
    setError('date_range.value', isValid === true ? null : isValid);
  };
  const onChangeActive = (e) => {
    const { value } = e.target;
    onUpdate(['active'], value === 'yes');
  };

  const onChangeNotifyByEmailAdresses = (emails) => {
    const emailsList = Immutable.List((emails.length) ? emails.split(',') : []);
    if (emailsList.includes('global')) {
      onUpdate(['notify_by_email', 'use_global_addresses'], true);
    } else {
      onUpdate(['notify_by_email', 'use_global_addresses'], false);
    }
    const globalIndex = emailsList.findIndex(email => email === 'global');
    const emailsListWithoutGlobal = (globalIndex === -1)
      ? emailsList
      : emailsList.remove(globalIndex);
    onUpdate(['notify_by_email', 'additional_addresses'], emailsListWithoutGlobal);
  };

  const onChangeNotifyByEmailStatus = (e) => {
    const { value } = e.target;
    onUpdate(['notify_by_email', 'notify'], value);
    onUpdate(['notify_by_email', 'use_global_addresses'], value);
    if (!value) {
      onUpdate(['notify_by_email', 'additional_addresses'], Immutable.List());
    }
  };

  const prepareEmailAddressesValue = () => {
    const emails = item.getIn(['notify_by_email', 'additional_addresses'], Immutable.List());
    const useGlobalAddresses = item.getIn(['notify_by_email', 'use_global_addresses'], false);
    if (useGlobalAddresses && !globalAddresses.isEmpty()) {
      return emails.insert(0, 'global').toArray();
    }
    return emails.toArray();
  };
  const emailAddressesSelectOptions = globalAddresses.isEmpty() ? [] : [{ value: 'global', label: `Global emails (${globalAddresses.join(', ')})` }];
  const emailAdderssesValue = prepareEmailAddressesValue();
  const isNotifyByEmail = item.getIn(['notify_by_email', 'notify'], false);
  const isEventCodeError = errors.get('event_code', false);
  const isRecurrenceValueError = errors.get('recurrence.value', false);
  const isDatRangeValueError = errors.get('date_range.value', false);
  return (
    <Col sm={12}>
      <FormGroup validationState={isEventCodeError ? 'error' : null}>
        <Col componentClass={ControlLabel} sm={3}>
          Event Code <span className="danger-red"> *</span>
        </Col>
        <Col sm={7}>
          <Field
            onChange={onChaneEventCode}
            value={item.get('event_code', '')}
          />
          { isEventCodeError && (
            <HelpBlock>{isEventCodeError}</HelpBlock>
          )}
        </Col>
      </FormGroup>
      <FormGroup>
        <Col componentClass={ControlLabel} sm={3}>
          Description
        </Col>
        <Col sm={7}>
          <Field
            onChange={onChaneEventDescription}
            value={item.get('event_description', '')}
          />
        </Col>
      </FormGroup>
      <FormGroup>
        <Col componentClass={ControlLabel} sm={3}>
          Notify also by email
        </Col>
        <Col sm={7}>
          <InputGroup>
            <InputGroup.Addon>
              <Field
                fieldType="checkbox"
                id="computed-must-met"
                value={isNotifyByEmail}
                onChange={onChangeNotifyByEmailStatus}
                label=""
              />
            </InputGroup.Addon>
            <Field
              allowCreate={true}
              multi={true}
              placeholder={isNotifyByEmail ? 'Select or add new email address' : 'No'}
              addLabelText="Add {label}?"
              noResultsText="Add email address..."
              clearable={true}
              fieldType="select"
              options={emailAddressesSelectOptions}
              value={emailAdderssesValue.join(',')}
              onChange={onChangeNotifyByEmailAdresses}
              disabled={!isNotifyByEmail}
            />
          </InputGroup>
        </Col>
      </FormGroup>
      <FormGroup validationState={isRecurrenceValueError ? 'error' : null}>
        <Col componentClass={ControlLabel} sm={3} >
          Run every <span className="danger-red"> *</span>
        </Col>
        <Col sm={7}>
          <InputGroup>
            <Field
              fieldType="select"
              options={recurrenceOptions}
              value={item.getIn(['recurrence', 'value'], '')}
              onChange={onChangeRecurrenceValue}
            />
            <DropdownButton
              id="balance-period-unit"
              componentClass={InputGroup.Button}
              title={recurrenceUnitTitle}
            >
              <MenuItem eventKey="minutely" onSelect={onChangeRecurrenceType}>Minutes</MenuItem>
              <MenuItem eventKey="hourly" onSelect={onChangeRecurrenceType}>Hours</MenuItem>
            </DropdownButton>
          </InputGroup>
          { isRecurrenceValueError && (
            <HelpBlock>{isRecurrenceValueError}</HelpBlock>
          )}
        </Col>
      </FormGroup>
      <FormGroup validationState={isDatRangeValueError ? 'error' : null}>
        <Col componentClass={ControlLabel} sm={3} >
          For the previous <span className="danger-red"> *</span>
        </Col>
        <Col sm={7}>
          <InputGroup>
            <Field
              fieldType="select"
              options={dateRangeOptions}
              value={item.getIn(['date_range', 'value'], '')}
              onChange={onChangeDateRangeValue}
            />
            <DropdownButton
              id="balance-period-unit"
              componentClass={InputGroup.Button}
              title={dateRangeUnitTitle}
            >
              <MenuItem eventKey="minutely" onSelect={onChangeDateRangeType}>Minutely</MenuItem>
              <MenuItem eventKey="hourly" onSelect={onChangeDateRangeType}>Hourly</MenuItem>
            </DropdownButton>
          </InputGroup>
          { isDatRangeValueError && (
            <HelpBlock>{isDatRangeValueError}</HelpBlock>
          )}
        </Col>
      </FormGroup>
      <FormGroup>
        <Col componentClass={ControlLabel} sm={3}>Status</Col>
        <Col sm={7}>
          <span>
            <span style={{ display: 'inline-block', marginRight: 20 }}>
              <Field
                fieldType="radio"
                onChange={onChangeActive}
                name="step-active-status"
                value="yes"
                label="Active"
                checked={item.get('active', true)}
              />
            </span>
            <span style={{ display: 'inline-block' }}>
              <Field
                fieldType="radio"
                onChange={onChangeActive}
                name="step-active-status"
                value="no"
                label="Inactive"
                checked={!item.get('active', true)}
              />
            </span>
          </span>
        </Col>
      </FormGroup>
      <FormGroup>
        <Col componentClass={ControlLabel} sm={3}>
          &nbsp;
        </Col>
        <Col sm={7} style={{ marginTop: 10, paddingLeft: 18 }}>
          <Field
            fieldType="checkbox"
            id="computed-must-met"
            value={item.get('lines_overlap', '')}
            onChange={onChangeLinesOverlap}
            label={'Events\' lines overlap is allowed'}
          />
        </Col>
      </FormGroup>
    </Col>
  );
};

FraudEventDetails.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
  eventsSettings: PropTypes.instanceOf(Immutable.Map),
  errors: PropTypes.instanceOf(Immutable.Map),
  onUpdate: PropTypes.func.isRequired,
  setError: PropTypes.func.isRequired,
};

FraudEventDetails.defaultProps = {
  item: Immutable.Map(),
  eventsSettings: Immutable.Map(),
  errors: Immutable.Map(),
};


export default FraudEventDetails;
