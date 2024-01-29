import React from 'react';
import PropTypes from 'prop-types';
import { Map, List } from 'immutable';
import { FormGroup, Col, Button, HelpBlock } from 'react-bootstrap';
import Field from '../Field';
import ConditionValue from '../Report/Editor/ConditionValue';
import { formatSelectOptions } from '@/common/Util';


const CustomFieldForeignCondition = ({
  condition, index, onRemove, onUpdate, error,
  conditionsFields,
  conditionsOperatorsSelectOptions,
  conditionField, conditionOperator,
}) => {
  const isValueArray = op => ['nin', 'in', '$nin', '$in'].includes(op);

  const onChangeConditionsField = (fieldId) => {
    const resetCondition = Map({
      field: fieldId,
      op: '',
      value: '',
    });
    onUpdate([index], resetCondition);
  };

  const onChangeConditionsOperator = (newOp) => {
    onUpdate([index, 'op'], newOp);
    const oldOp = condition.getIn(['op'], '');
    // if type was changed - reset value
    if (isValueArray(oldOp) !== isValueArray(newOp)) {
      const defaultEmptyValue = isValueArray(newOp) ? List() : '';
      onUpdate([index, 'value'], defaultEmptyValue);
    }
  };

  const onChangeConditionsValue = (value) => {
    const op = condition.getIn(['op'], '');
    if (isValueArray(op)) {
      const values = List((value.length) ? value.split(',') : []);
      onUpdate([index, 'value'], values);
    } else {
      onUpdate([index, 'value'], value);
    }
  };

  const onRemoveCondition = () => {
    onRemove(index);
  };
  const field = condition.getIn(['field'], '');
  const operator = condition.getIn(['op'], '');
  const disableOp = field === '';
  const disableVal = operator === '' || disableOp;
  const conditionForValue = isValueArray(operator)
    ? condition.set('value', condition.get('value', List()).join(','))
    : condition;
  const conditionsFieldSelectOptions = conditionsFields.map(formatSelectOptions);
  return (
    <FormGroup className="form-inner-edit-row" validationState={error ? 'error' : null}>
      <Col smHidden mdHidden lgHidden>
        <label htmlFor="condition_field">field</label>
      </Col>
      <Col sm={4}>
        <Field
          id="condition_field"
          fieldType="select"
          options={conditionsFieldSelectOptions}
          onChange={onChangeConditionsField}
          value={field}
        />
      </Col>

      <Col smHidden mdHidden lgHidden>
        <label htmlFor="condition_operator">Operator</label>
      </Col>
      <Col sm={3}>
        <Field
          id="condition_operator"
          fieldType="select"
          options={conditionsOperatorsSelectOptions}
          onChange={onChangeConditionsOperator}
          value={condition.getIn(['op'], '')}
          disabled={disableOp}
        />
      </Col>

      <Col smHidden mdHidden lgHidden>
        <label htmlFor="condition_value">Value</label>
      </Col>
      <Col sm={4}>
        <ConditionValue
          field={conditionForValue}
          config={conditionField}
          operator={conditionOperator}
          disabled={disableVal}
          onChange={onChangeConditionsValue}
        />
      </Col>
      <Col sm={1} className="actions">
        <Button onClick={onRemoveCondition} bsSize="small" className="pull-left">
          <i className="fa fa-trash-o danger-red" />
        </Button>
      </Col>
      { error && (
        <Col sm={12}>
          <HelpBlock>
            <small>{error}</small>
          </HelpBlock>
        </Col>
      )}
    </FormGroup>
  );
};


CustomFieldForeignCondition.propTypes = {
  condition: PropTypes.instanceOf(Map),
  index: PropTypes.number.isRequired,
  conditionField: PropTypes.instanceOf(Map),
  conditionsFields: PropTypes.array,
  conditionOperator: PropTypes.instanceOf(Map),
  conditionsOperatorsSelectOptions: PropTypes.array,
  onUpdate: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
  error: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.bool,
  ]),
};


CustomFieldForeignCondition.defaultProps = {
  condition: Map(),
  conditionField: Map(),
  conditionsFields: [],
  conditionOperator: Map(),
  conditionsOperatorsSelectOptions: [],
  error: '',
};


export default CustomFieldForeignCondition;
