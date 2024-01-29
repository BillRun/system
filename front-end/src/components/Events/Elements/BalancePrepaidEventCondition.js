import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { List, Map } from 'immutable';
import { usageTypesDataSelector, propertyTypeSelector, currencySelector } from '@/selectors/settingsSelector';
import { FormGroup, Col, ControlLabel, InputGroup, HelpBlock } from 'react-bootstrap';
import isNumber from 'is-number';
import Field from '@/components/Field';
import { getBucketsOptions } from '@/actions/reportsActions';
import { showWarning } from '@/actions/alertsActions';
import {
  bucketsSelectOptionsSelector,
} from '@/selectors/listSelectors';
import {
  eventConditionsOperatorsSelectOptionsSelector,
} from '@/selectors/eventSelectors';
import {
  getBalanceConditionData,
  getUnitTitle,
  getBalancePrepaidConditionIndexByType,
} from '../EventsUtil';


class BalancePrepaidEventCondition extends Component {

  static propTypes = {
    conditions: PropTypes.instanceOf(List),
    conditionsOperators: PropTypes.array,
    bucketsOptions: PropTypes.array,
    errors: PropTypes.instanceOf(Map),
    propertyTypes: PropTypes.instanceOf(List),
    currency: PropTypes.string,
    usageTypesData: PropTypes.instanceOf(List),
    onChangeField: PropTypes.func,
    bucketConditionIndex: PropTypes.number.isRequired,
    valueConditionIndex: PropTypes.number.isRequired,
    setError: PropTypes.func,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    conditions: List(),
    conditionsOperators: [],
    bucketsOptions: [],
    errors: Map(),
    propertyTypes: List(),
    currency: '',
    usageTypesData: List(),
    onChangeField: () => {},
    setError: () => {},
  };

  componentDidMount() {
    this.props.dispatch(getBucketsOptions());
  }

  onChangeBucket = (bucket_external_id, { option = {} }) => {
    const { bucketConditionIndex, valueConditionIndex } = this.props;
    const { charging_by, charging_by_usaget, charging_by_usaget_unit } = option;
    this.props.setError('bucket');
    let path = 'balance.totals';
    if (charging_by === 'total_cost') {
      path += '.cost';
    } else {
      path += `.${charging_by_usaget}.${charging_by}`;
    }
    this.props.onChangeField(['conditions', bucketConditionIndex, 'value'], bucket_external_id);
    this.props.onChangeField(['conditions', valueConditionIndex, 'unit'], charging_by_usaget_unit);
    this.props.onChangeField(['conditions', valueConditionIndex, 'usaget'], charging_by_usaget);
    this.props.onChangeField(['conditions', valueConditionIndex, 'paths'], List([Map({path})]));
  };

  onChangeCondition = (condition) => {
    const { valueConditionIndex } = this.props;
    this.props.setError('operator');
    this.props.setError('value');
    this.props.onChangeField(['conditions', valueConditionIndex, 'type'], condition);
  };

  onChangeMultiValues = (e) => {
    if (Array.isArray(e)) {
      this.onChangeTagValue(e.join(','));
    } else {
      this.onChangeTagValue('');
    }
  };

  onChangeTagValue = (value) => {
    const { onChangeField, conditions, valueConditionIndex } = this.props;
    this.props.setError('value');
    const value_condition = conditions.get(valueConditionIndex, Map());
    if (value_condition.get('type', '') === 'reached_percentage') {
      const filterPercentageMultiValues = this.filterPercentageMultiValues(value);
      return onChangeField(['conditions', valueConditionIndex, 'value'], filterPercentageMultiValues);
    }
    return onChangeField(['conditions', valueConditionIndex, 'value'], value);
  };

  onChangeValue = (e) => {
    const { valueConditionIndex } = this.props;
    const { value } = e.target;
    this.props.setError('value');
    this.props.onChangeField(['conditions', valueConditionIndex, 'value'], value);
  };

  filterPercentageMultiValues = values => values
    .split(",")
    .filter(val => val !== '')
    .filter((val) => {
      if (!isNumber(val)) {
        this.props.dispatch(showWarning('Condition value must be numeric'));
        return false;
      }
      const numericValue = parseFloat(val);
      if (numericValue <= 0) {
        this.props.dispatch(showWarning('Condition value should be greater than zero'));
        return false
      }
      if (numericValue > 100) {
        this.props.dispatch(showWarning('Condition value cannot be greater than 100'));
        return false;
      }
      return true;
    })
    .join(',');

  renderCustomInputNumber =({ addTag, onChange, value, ...other }) => (
    <input type="number" onChange={onChange} value={value} {...other} />
  );

  render () {
    const {
      conditions,
      errors,
      conditionsOperators,
      bucketsOptions,
      propertyTypes,
      usageTypesData,
      bucketConditionIndex,
      valueConditionIndex,
      currency,
    } = this.props;

    const value_condition = conditions.get(valueConditionIndex, Map());
    const bucket_condition = conditions.get(bucketConditionIndex, Map());
    const selectedConditionData = getBalanceConditionData(value_condition.get('type', ''));
    const unitLabel = getUnitTitle(value_condition.get('unit', ''), '', value_condition.get('usaget', ''), propertyTypes, usageTypesData, currency, value_condition.get('type', ''));

    const isBucketError = errors.get('bucket', false);
    const isOperatorError = errors.get('operator', false);
    const isValueError = errors.get('value', false);

    return (
      <Col sm={12}>

        <FormGroup validationState={isBucketError ? 'error' : null}>
          <Col sm={4} smOffset={1} xsOffset={0} xs={12} className="text-left" componentClass={ControlLabel}>
            Bucket
          </Col>
          <Col sm={7} smOffset={0} xsOffset={2} xs={10} className="pl40">
              <Field
              fieldType="select"
              onChange={this.onChangeBucket}
              value={bucket_condition.get('value', '')}
              options={bucketsOptions}
            />
            { isBucketError && (
              <HelpBlock>{isBucketError}</HelpBlock>
            )}
          </Col>
        </FormGroup>

        <FormGroup validationState={isOperatorError ? 'error' : null}>
          <Col sm={4} smOffset={1} xsOffset={0} xs={12} className="text-left" componentClass={ControlLabel}>
            Condition
          </Col>
          <Col sm={7} smOffset={0} xsOffset={2} xs={10} className="pl40">
            <Field
              fieldType="select"
              onChange={this.onChangeCondition}
              value={value_condition.get('type', '')}
              options={conditionsOperators}
            />
            { isOperatorError && (
              <HelpBlock>{isOperatorError}</HelpBlock>
            )}
          </Col>
        </FormGroup>

        { selectedConditionData.get('extra_field', true) && (
          <FormGroup validationState={isValueError ? 'error' : null}>
            <Col sm={4} smOffset={1} xsOffset={0} xs={12} className="text-left" componentClass={ControlLabel}>
              Value
            </Col>
            <Col sm={7} smOffset={0} xsOffset={2} xs={10} className="pl40">
              <InputGroup className="full-width">
                {selectedConditionData.get('type', 'text') !== 'tags' ? (
                  <Field
                    onChange={this.onChangeValue}
                    value={value_condition.get('value', '')}
                    fieldType={selectedConditionData.get('type', 'text')}
                  />
                ) : (
                  <Field
                    fieldType="tags"
                    onChange={this.onChangeMultiValues}
                    value={String(value_condition.get('value', '')).split(',').filter(val => val !== '')}
                    renderInput={this.renderCustomInputNumber}
                    onlyUnique={selectedConditionData.get('type', '') === 'tags'}
                  />
                )}
                { unitLabel !== '' && (
                  <InputGroup.Addon>{unitLabel}</InputGroup.Addon>
                )}
              </InputGroup>
              { isValueError && (
                <HelpBlock>{isValueError}</HelpBlock>
              )}
            </Col>
          </FormGroup>
        )}
      </Col>
    );
  }
}


const mapStateToProps = (state, props) => ({
  propertyTypes: propertyTypeSelector(state, props),
  usageTypesData: usageTypesDataSelector(state, props),
  currency: currencySelector(state, props),
  conditionsOperators: eventConditionsOperatorsSelectOptionsSelector(null, { eventType: 'balancePrepaid' }),
  bucketsOptions: bucketsSelectOptionsSelector(state, props),
  bucketConditionIndex: getBalancePrepaidConditionIndexByType('bucket', props.conditions),
  valueConditionIndex: getBalancePrepaidConditionIndexByType('value', props.conditions),
});

export default connect(mapStateToProps)(BalancePrepaidEventCondition);
