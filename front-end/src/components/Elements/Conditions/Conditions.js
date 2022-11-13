import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Col, FormGroup } from 'react-bootstrap';
import { CreateButton } from '@/components/Elements';
import Condition from './Condition';


class Conditions extends Component {

  static propTypes = {
    conditions: PropTypes.instanceOf(Immutable.List),
    fields: PropTypes.instanceOf(Immutable.List),
    operators: PropTypes.instanceOf(Immutable.List),
    customValueOptions: PropTypes.instanceOf(Immutable.List),
    errors: PropTypes.instanceOf(Immutable.Map),
    disabled: PropTypes.bool,
    editable: PropTypes.bool,
    addConditionLabel: PropTypes.string,
    noConditionsLabel: PropTypes.string,
    onRemove: PropTypes.func,
    onAdd: PropTypes.func,
    onChangeField: PropTypes.func,
    onChangeOperator: PropTypes.func,
    onChangeValue: PropTypes.func,
  }

  static defaultProps = {
    conditions: Immutable.List(),
    fields: Immutable.List(),
    operators: Immutable.List(),
    customValueOptions: Immutable.List(),
    errors: Immutable.Map(),
    disabled: false,
    editable: true,
    addConditionLabel: 'Add Condition',
    noConditionsLabel: 'No conditions found',
    onChangeField: () => {},
    onChangeOperator: () => {},
    onChangeValue: () => {},
    onAdd: null,
    onRemove: () => {},
  }

  static defaultCondition = Immutable.Map({
    field: '',
    op: '',
    value: '',
  });

  shouldComponentUpdate(nextProps) {
    const {
      conditions,
      fields,
      disabled,
      editable,
      operators,
      addConditionLabel,
      noConditionsLabel,
      errors,
    } = this.props;
    return (
      !Immutable.is(conditions, nextProps.conditions)
      || !Immutable.is(fields, nextProps.fields)
      || !Immutable.is(operators, nextProps.operators)
      || !Immutable.is(errors, nextProps.errors)
      || disabled !== nextProps.disabled
      || editable !== nextProps.editable
      || addConditionLabel !== nextProps.addConditionLabel
      || noConditionsLabel !== nextProps.noConditionsLabel
    );
  }

  onAddCondition = () => {
    this.props.onAdd(Conditions.defaultCondition);
  }

  renderRow = (filter, index) => {
    const { operators, fields, customValueOptions, disabled, editable, errors } = this.props;
    return (
      <Condition
        key={index}
        item={filter}
        index={index}
        fields={fields}
        operators={operators}
        customValueOptions={customValueOptions}
        disabled={disabled}
        editable={editable}
        error={errors.get(`${index}`, false)}
        onChangeField={this.props.onChangeField}
        onChangeOperator={this.props.onChangeOperator}
        onChangeValue={this.props.onChangeValue}
        onRemove={this.props.onRemove}
      />
    );
  }

  render() {
    const { conditions, disabled, editable, fields, addConditionLabel, noConditionsLabel } = this.props;
    const conditionsRows = conditions.map(this.renderRow);
    const disableAdd = fields.isEmpty() || disabled;
    return (
      <div className="conditions-list">
        { !conditionsRows.isEmpty() && (
          <Col sm={12} className="form-inner-edit-rows">
            <FormGroup className="form-inner-edit-row">
              <Col sm={4} xsHidden><label htmlFor="field">Field</label></Col>
              <Col sm={3} xsHidden><label htmlFor="operator">Operator</label></Col>
              <Col sm={4} xsHidden><label htmlFor="value">Value</label></Col>
            </FormGroup>
          </Col>
        )}
        { conditionsRows.isEmpty() && noConditionsLabel.length > 0 && (
          <Col sm={12}>
            <small>{noConditionsLabel}</small>
          </Col>
        )}
        { !conditionsRows.isEmpty() && (
          <Col sm={12}>
            { conditionsRows }
          </Col>
        )}
        { editable && this.props.onAdd && (
          <Col sm={12} className="pl0 pr0">
            <CreateButton
              onClick={this.onAddCondition}
              label={addConditionLabel}
              disabled={disableAdd}
            />
          </Col>
        )}
      </div>
    );
  }

}

export default Conditions;
