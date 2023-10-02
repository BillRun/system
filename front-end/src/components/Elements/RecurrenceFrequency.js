import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { FormGroup, ControlLabel, Col, InputGroup } from 'react-bootstrap';
import Field from '@/components/Field';
import { getFieldName } from '@/common/Util';

const periodicityOptions = [
  { value: 1, label: getFieldName('recurrence.periodicity.1', '', 1)},
  { value: 2, label: getFieldName('recurrence.periodicity.2', '', 2) },
  { value: 3, label: getFieldName('recurrence.periodicity.3', '', 3) },
  { value: 6, label: getFieldName('recurrence.periodicity.6', '', 6) },
  { value: 12, label: getFieldName('recurrence.periodicity.12', '', 12) }
];

const getOptionsByFrequency = (frequency) => {
  let recurrenceStartOptions = [];
  const months = [
    'Jan' ,'Feb' ,'Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'
  ];
  if (frequency === 1 ) {
    recurrenceStartOptions = [
      [1],
    ]
  } else if (frequency === 2 ) {
    recurrenceStartOptions = [
      [1,3,5,7,9,11], [2,4,6,8,10,12],
    ]
  } else if (frequency === 3 ) {
    recurrenceStartOptions = [
      [1,4,7,10], [2,5,8,11], [3,6,9,12],
    ]
  } else if (frequency === 6 ) {
    recurrenceStartOptions = [
      [1,7], [2,8], [3,9], [4,10], [5,11], [6,12],
    ]
  } else if (frequency === 12 ) {
    recurrenceStartOptions = [
      [1], [2], [3], [4], [5], [6], [7], [8], [9], [10], [11], [12],
    ]
  }
  return recurrenceStartOptions.reduce((acc, options, idx) => acc.push(Immutable.Map({
      value: idx + 1,
      label: options.map(monthNum => months[monthNum-1]).join(', ')
    }))
  , Immutable.List()).toJS();
}


const RecurrenceFrequency = ({
  item, sourceItem, itemName, starPath, frequencyPath, editable, onChange, onRemove
}) => {
  const periodicity = item.getIn(frequencyPath, '');

  const recurrenceStartEditable = editable && periodicity !== '' && periodicity !== 1;

  const recurrenceStartOptions = getOptionsByFrequency(periodicity);

  const getCustomCycleMonthLabel = () => {
    const frequency = item.getIn(frequencyPath, null);
    const start = item.getIn(starPath, null);
    if (frequency !== null && start !== null) {
      const recurrenceStartOptions = Immutable.fromJS(getOptionsByFrequency(frequency));
      if (recurrenceStartOptions.isEmpty()) {
        return null;
      }
      return recurrenceStartOptions.filter(option => option.get('value', null) === start).first().get('label', null);
    }
    return 'Jan';
  }

  const onSelectRecurrenceStart = (value) => {
    if (value === null || value === '' || value === false) {
      onRemove(starPath);
    } else {
      onChange(starPath, value);
    }
  }
  
  const onChangePeriodicity = (value) => {
    const prevStart = item.getIn(starPath);
    const origFrequency = sourceItem.getIn(frequencyPath);
    const origStart = sourceItem.getIn(starPath);

    if (value === null || value === '' || value === false) {
      let wholeElement = [...frequencyPath];
      wholeElement.splice(-1);
      onRemove(wholeElement);
    } else {
      onChange(frequencyPath, value);
      if (origFrequency === value) { // revert value
        onChange(starPath, origStart);
      } else if (value === 1) { // Monthly - can be only 1
        onChange(starPath, 1);
      } else {
        // set the first option on change
        const options = getOptionsByFrequency(value);
        const optionValues = options.map(option => option.value);
        if (!optionValues.includes(prevStart)) {
          if (options[0]) {
            onChange(starPath, options[0].value);
          } else {
            onRemove(starPath);
          }
        }
      }
    }
  }

  return (
    <FormGroup>
      <Col componentClass={ControlLabel} sm={3} lg={2}>
        {getFieldName('billing_frequency', itemName, 'Billing Frequency')}
        <span className="danger-red"> *</span>
      </Col>
      <Col sm={4}>
        <Field
          fieldType="select"
          options={periodicityOptions}
          onChange={onChangePeriodicity}
          value={periodicity}
          editable={editable}
        />
      </Col>

      {(recurrenceStartEditable) && (
        <Col sm={4} lg={5}>
          <InputGroup>
            <InputGroup.Addon>
              <Col componentClass={ControlLabel} className="pt0">
                {getFieldName('fixed_cycle_months', itemName, '')}
                <span className="danger-red"> *</span>
              </Col>
            </InputGroup.Addon>
            <Field
              fieldType="select"
              editable={recurrenceStartEditable}
              value={item.getIn(['recurrence', 'start'], '')}
              options={recurrenceStartOptions}
              onChange={onSelectRecurrenceStart}
            />
          </InputGroup>
        </Col>
      )}
      {(!editable) && (periodicity !== 1) && (
        <>
          <Col componentClass={ControlLabel} sm={2} lg={2}>
            {getFieldName('fixed_cycle_months', itemName, '')}:
          </Col>
          <Col sm={2} lg={3} className="non-editable-field">
            {getCustomCycleMonthLabel(item)}
          </Col>
        </>
      )}
    </FormGroup>
  );
}

RecurrenceFrequency.defaultProps = {
  item: Immutable.Map(),
  itemName: '',
  starPath: ['recurrence', 'start'],
  frequencyPath: ['recurrence', 'frequency'],
  sourceItem: Immutable.Map(),
  editable: true,
};

RecurrenceFrequency.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
  itemName: PropTypes.string,
  starPath: PropTypes.array,
  frequencyPath: PropTypes.array,
  sourceItem: PropTypes.instanceOf(Immutable.Map),
  editable: PropTypes.bool,
  onChange: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
};

export default RecurrenceFrequency;
