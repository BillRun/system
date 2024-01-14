import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import Field from '@/components/Field';
import { Button, FormGroup, Col, HelpBlock } from 'react-bootstrap';
import { parseConfigSelectOptions } from '@/common/Util';
import ConditionValue from './ConditionValue';


class Condition extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    index: PropTypes.number,
    disabled: PropTypes.bool,
    editable: PropTypes.bool,
    operators: PropTypes.instanceOf(Immutable.List),
    customValueOptions: PropTypes.instanceOf(Immutable.List),
    fields: PropTypes.instanceOf(Immutable.List),
    conditions: PropTypes.instanceOf(Immutable.List),
    error: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.bool,
    ]),
    onChangeField: PropTypes.func,
    onChangeOperator: PropTypes.func,
    onChangeValue: PropTypes.func,
    onRemove: PropTypes.func,
  }

  static defaultProps = {
    item: Immutable.Map(),
    index: 0,
    disabled: false,
    editable: true,
    fields: Immutable.List(),
    conditions: Immutable.List(),
    operators: Immutable.List(),
    customValueOptions: Immutable.List(),
    error: false,
    onChangeField: () => {},
    onChangeOperator: () => {},
    onChangeValue: () => {},
    onRemove: () => {},
  }

  shouldComponentUpdate(nextProps) {
    const { item, index, disabled, fields, conditions, operators, editable, error } = this.props;
    return (
      !Immutable.is(item, nextProps.item)
      || !Immutable.is(fields, nextProps.fields)
      || !Immutable.is(operators, nextProps.operators)
      || !Immutable.is(conditions, nextProps.conditions)
      || index !== nextProps.index
      || disabled !== nextProps.disabled
      || editable !== nextProps.editable
      || error !== nextProps.error
    );
  }

  componentDidUpdate(prevProps, prevState) { // eslint-disable-line no-unused-vars
    const { item, index } = this.props;
    const fieldOps = this.getOpOptions() || [];
    // If only one option avalibale, auto select it.
    if (item.get('op', '') === '' && fieldOps.length === 1) {
      this.props.onChangeOperator(index, fieldOps[0].value);
    }
    // if new field doesn't support old operator, reset selected operator
    else if (
      prevProps.item.get('field', '') !== item.get('field', '')
      && item.get('op', '') !== ''
      && !fieldOps.map(op => op.value).includes(item.get('op', ''))
    ) {
      this.props.onChangeOperator(index, '');
    }
  }

  onChangeField = (e) => {
    const { index } = this.props;
    this.props.onChangeField(index, e);
  };

  onChangeOperator = (e) => {
    const { index } = this.props;
    this.props.onChangeOperator(index, e);
  };

  onChangeValue = (e) => {
    const { index } = this.props;
    this.props.onChangeValue(index, e);
  };

  onRemove = (e) => {
    const { index } = this.props;
    this.props.onRemove(index, e);
  };

  getConfig = () => {
    const { item, fields } = this.props;
    return fields.find(field => field.get('id', '') === item.get('field', ''), null, Immutable.Map());
  }

  getOperator = () => {
    const { item, operators } = this.props;
    const config = this.getConfig();
    return operators
      .filter(option => (!this.isInBlackList(option, config) && this.isInWhiteList(option, config)))
      .find(
        operator => operator.get('id', '') === item.get('op', ''),
        null, Immutable.Map(),
      );
  }

  getFieldOptions = () => {
    const { fields } = this.props;
    return fields
      .filter(field => field.get('filter', true))
      .map(parseConfigSelectOptions)
      .toArray();
  }

  isInBlackList = (option, config) => {
    const { item } = this.props;
    const blackList = option.get('exclude', Immutable.List());
    const fieldKey = `fieldid:${config.get('id', '')}`;
    const fieldType = config.get('type', 'string');
    const fieldOp = `op:${item.get('op', '')}`;
    return blackList.includes(fieldKey) || blackList.includes(fieldType) || blackList.includes(fieldOp);
  }

  isInWhiteList = (option, config) => {
    const { item } = this.props;
    const whiteList = option.get('include', Immutable.List());
    const fieldKey = `fieldid:${config.get('id', '')}`;
    const fieldType = config.get('type', 'string');
    const fieldOp = `op:${item.get('op', '')}`;
    return whiteList.includes(fieldType) || whiteList.includes(fieldKey) || whiteList.includes(fieldOp);
  }

  getOpOptions = () => {
    const { operators } = this.props;
    const config = this.getConfig();
    return operators
      .filter(option => (!this.isInBlackList(option, config) && this.isInWhiteList(option, config)))
      .map(parseConfigSelectOptions)
      .toArray();
  }

  getCustomValueOptions = () => {
    const { customValueOptions } = this.props;
    const config = this.getConfig();
    return customValueOptions
      .filter(option => (!this.isInBlackList(option, config) && this.isInWhiteList(option, config)))
  }

  render() {
    const { item, conditions, disabled, editable, error } = this.props;
    const config = this.getConfig();
    const operator = this.getOperator();
    const fieldOptions = this.getFieldOptions();
    const opOptions = this.getOpOptions();
    const customValueOptions = this.getCustomValueOptions();
    const disableOp = disabled || item.get('field', '') === '';
    const disableVal = disabled || item.get('op', '') === '' || disableOp;
    return (
      <FormGroup className="form-inner-edit-row" validationState={error ? 'error' : null}>
        <Col smHidden mdHidden lgHidden>
          <label htmlFor="field_field">Field</label>
        </Col>
        <Col sm={4} className="pl0">
          <Field
            fieldType="select"
            clearable={false}
            options={fieldOptions}
            value={item.get('field', '')}
            onChange={this.onChangeField}
            disabled={disabled}
            editable={editable}
          />
        </Col>

        <Col smHidden mdHidden lgHidden>
          <label htmlFor="operator_field">Operator</label>
        </Col>
        <Col sm={3}>
          <Field
            fieldType="select"
            clearable={false}
            options={opOptions}
            value={item.get('op', '')}
            onChange={this.onChangeOperator}
            disabled={disableOp}
            editable={editable}
          />
        </Col>

        <Col smHidden mdHidden lgHidden>
          <label htmlFor="value_field">Value</label>
        </Col>
        <Col sm={4}>
          <ConditionValue
            field={item}
            config={config}
            operator={operator}
            conditions={conditions}
            customValueOptions={customValueOptions}
            disabled={disableVal}
            editable={editable}
            onChange={this.onChangeValue}
          />
        </Col>
        {editable && (
          <Col sm={1} className="actions">
            <Button onClick={this.onRemove} bsSize="small" className="pull-left" disabled={disabled}>
              <i className="fa fa-trash-o danger-red" />
            </Button>
          </Col>
        )}
        { error && (
          <Col sm={12}>
            <HelpBlock>
              <small>{error}</small>
            </HelpBlock>
          </Col>
        )}
      </FormGroup>
    );
  }

}

export default Condition;
