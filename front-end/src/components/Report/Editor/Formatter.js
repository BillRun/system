import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Button, FormGroup, Col } from 'react-bootstrap';
import { SortableElement } from 'react-sortable-hoc';
import Field from '@/components/Field';
import { DragHandle } from '@/components/Elements';
import FormatterValue from './FormatterValue';
import { parseConfigSelectOptions } from '@/common/Util';

class Formatter extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    idx: PropTypes.number,
    disabled: PropTypes.bool,
    options: PropTypes.instanceOf(Immutable.List),
    operators: PropTypes.instanceOf(Immutable.List),
    onChangeField: PropTypes.func,
    onChangeOperator: PropTypes.func,
    onChangeValue: PropTypes.func,
    onChangeValueType: PropTypes.func,
    onRemove: PropTypes.func,
  }

  static defaultProps = {
    item: Immutable.Map(),
    idx: 0,
    disabled: false,
    options: Immutable.List(),
    operators: Immutable.List(),
    onChangeField: () => {},
    onChangeOperator: () => {},
    onChangeValue: () => {},
    onChangeValueType: () => {},
    onRemove: () => {},
  }

  shouldComponentUpdate(nextProps) {
    const { item, idx, disabled, options, operators } = this.props;
    return (
      !Immutable.is(item, nextProps.item)
      || !Immutable.is(options, nextProps.options)
      || !Immutable.is(operators, nextProps.operators)
      || idx !== nextProps.idx
      || disabled !== nextProps.disabled
    );
  }

  onRemove = (e) => {
    const { idx } = this.props;
    this.props.onRemove(idx, e);
  }

  onChangeField = (e) => {
    const { idx } = this.props;
    this.props.onChangeField(idx, e);
  }

  onChangeOperator = (e) => {
    const { idx } = this.props;
    this.props.onChangeOperator(idx, e);
  }

  onChangeValue = (e) => {
    const { idx } = this.props;
    this.props.onChangeValue(idx, e);
  }

  onChangeValueType = (e) => {
    const { idx } = this.props;
    this.props.onChangeValueType(idx, e);
  }

  getFieldOptions = () => {
    const { options } = this.props;
    return options
      .map(option => ({ value: option.get('key', ''), label: option.get('label', '') }))
      .toArray();
  }

  getOpOptions = () => {
    const { operators } = this.props;
    return operators
      .map(parseConfigSelectOptions)
      .toArray();
  }

  render() {
    const { item, disabled, operators } = this.props;
    const fieldOptions = this.getFieldOptions();
    const opOptions = this.getOpOptions();
    const disableOp = disabled || item.get('field', '') === '';
    const selectedOp = operators.find(operator => operator.get('id', '') === item.get('op', ''));
    return (
      <FormGroup className="form-inner-edit-row">
        <Col sm={1} className="text-center">
          <DragHandle />
        </Col>
        <Col sm={4}>
          <Field
            fieldType="select"
            clearable={false}
            options={fieldOptions}
            value={item.get('field', '')}
            onChange={this.onChangeField}
            disabled={disabled}
          />
        </Col>
        <Col sm={3}>
          <Field
            fieldType="select"
            clearable={false}
            options={opOptions}
            value={item.get('op', '')}
            onChange={this.onChangeOperator}
            disabled={disableOp}
          />
        </Col>
        <Col sm={3}>
          <FormatterValue
            onChange={this.onChangeValue}
            onChangeValueType={this.onChangeValueType}
            field={item}
            config={selectedOp}
          />
        </Col>
        <Col sm={1} className="actions">
          <Button onClick={this.onRemove} bsSize="small" className="pull-left" disabled={disabled}>
            <i className="fa fa-trash-o danger-red" />
          </Button>
        </Col>
      </FormGroup>
    );
  }

}

export default SortableElement(Formatter);
