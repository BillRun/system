import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Button, FormGroup, Col } from 'react-bootstrap';
import { SortableElement } from 'react-sortable-hoc';
import Field from '@/components/Field';
import { DragHandle } from '@/components/Elements';
import { formatSelectOptions } from '@/common/Util';

class Sort extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    idx: PropTypes.number,
    disabled: PropTypes.bool,
    options: PropTypes.instanceOf(Immutable.List),
    sortOperators: PropTypes.instanceOf(Immutable.List),
    onChangeField: PropTypes.func,
    onChangeOperator: PropTypes.func,
    onRemove: PropTypes.func,
  }

  static defaultProps = {
    item: Immutable.Map(),
    idx: 0,
    disabled: false,
    options: Immutable.List(),
    sortOperators: Immutable.List(),
    onChangeField: () => {},
    onChangeOperator: () => {},
    onRemove: () => {},
  }

  shouldComponentUpdate(nextProps) {
    const { item, idx, disabled, options, sortOperators } = this.props;
    return (
      !Immutable.is(item, nextProps.item)
      || !Immutable.is(options, nextProps.options)
      || !Immutable.is(sortOperators, nextProps.sortOperators)
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

  getFieldOptions = () => {
    const { options } = this.props;
    return options
      .map(formatSelectOptions)
      .toArray();
  }

  getOpOptions = () => {
    const { sortOperators } = this.props;
    return sortOperators
      .map(formatSelectOptions)
      .toArray();
  }

  render() {
    const { item, disabled } = this.props;
    const fieldOptions = this.getFieldOptions();
    const opOptions = this.getOpOptions();
    const disableOp = disabled || item.get('field', '') === '';
    return (
      <FormGroup className="form-inner-edit-row">
        <Col sm={1} className="text-center">
          <DragHandle />
        </Col>

        <Col smHidden mdHidden lgHidden>
          <label htmlFor="field_field">Field</label>
        </Col>
        <Col sm={5}>
          <Field
            fieldType="select"
            clearable={false}
            options={fieldOptions}
            value={item.get('field', '')}
            onChange={this.onChangeField}
            disabled={disabled}
          />
        </Col>

        <Col smHidden mdHidden lgHidden>
          <label htmlFor="order_field">Order</label>
        </Col>
        <Col sm={5}>
          <Field
            fieldType="select"
            clearable={false}
            options={opOptions}
            value={item.get('op', '')}
            onChange={this.onChangeOperator}
            disabled={disableOp}
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

export default SortableElement(Sort);
