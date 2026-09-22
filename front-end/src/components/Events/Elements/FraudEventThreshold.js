import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Col } from 'react-bootstrap';
import { ControlLabel, FormGroup, HelpBlock } from '@/common/BootstrapCompat';
import isNumber from 'is-number';
import Field from '@/components/Field';
import ConditionValue from '../../Report/Editor/ConditionValue';
import UsageTypesSelector from '../../UsageTypes/UsageTypesSelector';
import {
  currencySelector,
  usageTypesDataSelector,
} from '@/selectors/settingsSelector';
import {
  eventTresholdFieldsSelector,
} from '@/selectors/eventSelectors';


class FraudEventThreshold extends Component {

  static propTypes = {
    threshold: PropTypes.instanceOf(Immutable.Map),
    index: PropTypes.number.isRequired,
    eventPropertyType: PropTypes.instanceOf(Immutable.Set),
    thresholdFieldsSelectOptions: PropTypes.array,
    thresholdOperatorsSelectOptions: PropTypes.array,
    currency: PropTypes.string,
    usaget: PropTypes.string,
    thresholdFields: PropTypes.instanceOf(Immutable.List),
    errors: PropTypes.instanceOf(Immutable.Map),
    onUpdate: PropTypes.func.isRequired,
    setError: PropTypes.func.isRequired,
  }

  static defaultProps = {
    threshold: Immutable.Map(),
    eventPropertyType: Immutable.Set(),
    thresholdFieldsSelectOptions: [],
    thresholdFields: Immutable.List(),
    thresholdOperatorsSelectOptions: [],
    currency: '',
    usaget: '',
    errors: Immutable.Map(),
  }

  
  onChangeThresholdField = (value) => {
    const { index } = this.props;
    this.props.onUpdate([index, 'field'], value);
    this.props.setError(`threshold_condition.${index}`, null);
  }

  onChangeThresholdOperator = (value) => {
    const { index, threshold } = this.props;
    this.props.onUpdate([index, 'op'], value);
    if (['in', 'nin'].includes(value) && !['in', 'nin'].includes(threshold.getIn(['op'], ''))) {
      this.props.onUpdate([index, 'value'], Immutable.List());
    }
    if (!['in', 'nin'].includes(value) && ['in', 'nin'].includes(threshold.getIn(['op'], ''))) {
      this.props.onUpdate([index, 'value'], '');
    }
    this.props.setError(`threshold_condition.${index}`, null);
  }

  onChangeThresholdValue = (value) => {
    const { index, threshold } = this.props;
    if (['in', 'nin'].includes(threshold.getIn(['op'], ''))) {
      const values = Immutable.List((value.length) ? value.split(',') : [])
        .map(val => (isNumber(val) ? parseFloat(val) : val));
      this.props.onUpdate([index, 'value'], values);
      return;
    }
    const val = isNumber(value) ? parseFloat(value) : value;
    this.props.onUpdate([index, 'value'], val);
    this.props.setError(`threshold_condition.${index}`, null);
  }

  onChangeThresholdUnit = (value) => {
    const { index, usaget } = this.props;
    this.props.onUpdate([index, 'unit'], value);
    this.props.onUpdate([index, 'usaget'], usaget);
    this.props.setError(`threshold_condition.${index}`, null);
  }

  
  componentDidUpdate(prevProps, prevState) {// eslint-disable-line no-unused-vars
    const { index, eventPropertyType } = prevProps;
    if (!Immutable.is(eventPropertyType, this.props.eventPropertyType)) {
      prevProps.onUpdate([index], Immutable.Map());
    }
  }

  render() {
    const {
      index,
      threshold,
      eventPropertyType,
      thresholdFields,
      thresholdFieldsSelectOptions,
      thresholdOperatorsSelectOptions,
      usaget,
      currency,
      errors,
    } = this.props;
    const value = threshold.get('value', Immutable.List());
    const thresholdForValue = Immutable.List.isList(value) || Array.isArray(value)
      ? threshold.set('value', value.join(','))
      : threshold;

    const field = threshold.getIn(['field'], '');
    const operator = threshold.getIn(['op'], '');
    const disableOp = field === '';
    const disableVal = operator === '' || disableOp;
    const thresholdField = thresholdFields.find(thresholdF => thresholdF.get('id') === field, null, Immutable.Map());
    const conditionValueOperator = ['aprice', 'final_charge'].includes(field)
      ? Immutable.Map({ suffix: currency })
      : Immutable.Map();
    const isThresholdError = errors.get(`threshold_condition.${index}`, false);
    const showUOM = eventPropertyType.size === 1 && !['aprice', 'final_charge'].includes(field);
    return (
      <FormGroup className="form-inner-edit-row pl0 pr0" validationState={isThresholdError ? 'error' : null}>
        <Col sm={showUOM ? 3 : 4}>
          <Field
            id="threshold_field"
            fieldType="select"
            options={thresholdFieldsSelectOptions}
            onChange={this.onChangeThresholdField}
            value={threshold.getIn(['field'], '')}
          />
        </Col>

        <Col sm={showUOM ? 3 : 4}>
          <Field
            id="threshold_operator"
            fieldType="select"
            options={thresholdOperatorsSelectOptions}
            onChange={this.onChangeThresholdOperator}
            disabled={disableOp}
            value={threshold.getIn(['op'], '')}
          />
        </Col>

        <Col sm={showUOM ? 3 : 4}>
          <ConditionValue
            field={thresholdForValue}
            config={thresholdField}
            operator={conditionValueOperator}
            disabled={disableVal}
            onChange={this.onChangeThresholdValue}
          />
        </Col>

        {showUOM && (
          <>
            <Col sm={3} className="pr0 pl5">
              <UsageTypesSelector
                usaget={usaget}
                unit={threshold.get('unit', '')}
                onChangeUnit={this.onChangeThresholdUnit}
                enabled={true}
                showUnits={true}
                showSelectTypes={false}
              />
            </Col>
          </>
        )}
        { isThresholdError && (
          <Col sm={12}>
            <HelpBlock>{isThresholdError}</HelpBlock>
          </Col>
        )}
      </FormGroup>
    );
  }
}

const mapStateToProps = (state, props) => {
  const usageTypesData = usageTypesDataSelector(state, props);
  return ({
    currency: currencySelector(state, props),
    thresholdFields: eventTresholdFieldsSelector(null, { eventType: 'fraud' }),
    usaget: props.eventPropertyType.size === 1
      ? usageTypesData.find(
          usageTypeData => usageTypeData.get('property_type', '') === props.eventPropertyType.first(),
          null, Immutable.Map(),
        ).get('usage_type', '')
      : '',
  });
};

export default connect(mapStateToProps)(FraudEventThreshold);
