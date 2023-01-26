import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Button, FormGroup, Col } from 'react-bootstrap';
import { SortableElement } from 'react-sortable-hoc';
import { DragHandle } from '@/components/Elements';
import Field from '@/components/Field';
import { parseConfigSelectOptions } from '@/common/Util';
import { reportTypes } from '@/actions/reportsActions';

class Column extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    idx: PropTypes.number,
    disabled: PropTypes.bool,
    isCountColumn: PropTypes.bool,
    type: PropTypes.number,
    fieldsConfig: PropTypes.instanceOf(Immutable.List),
    operators: PropTypes.instanceOf(Immutable.List),
    onChangeField: PropTypes.func,
    onChangeOperator: PropTypes.func,
    onChangeLabel: PropTypes.func,
    onRemove: PropTypes.func,
  }

  static defaultProps = {
    item: Immutable.Map(),
    idx: 0,
    disabled: false,
    isCountColumn: false,
    type: reportTypes.SIMPLE,
    fieldsConfig: Immutable.List(),
    operators: Immutable.List(),
    onChangeField: () => {},
    onChangeOperator: () => {},
    onChangeLabel: () => {},
    onRemove: () => {},
  }

  shouldComponentUpdate(nextProps) {
    const { item, idx, disabled, fieldsConfig, operators, type } = this.props;
    return (
      !Immutable.is(item, nextProps.item)
      || !Immutable.is(fieldsConfig, nextProps.fieldsConfig)
      || !Immutable.is(operators, nextProps.operators)
      || idx !== nextProps.idx
      || disabled !== nextProps.disabled
      || type !== nextProps.type
    );
  }

  onChangeLabel = (e) => {
    const { value } = e.target;
    const { idx } = this.props;
    this.props.onChangeLabel(idx, value);
  }

  onChangeField = (value) => {
    const { idx } = this.props;
    this.props.onChangeField(idx, value);
  }

  onChangeOperator = (value) => {
    const { idx } = this.props;
    this.props.onChangeOperator(idx, value);
  }

  onRemove = () => {
    const { idx } = this.props;
    this.props.onRemove(idx);
  }

  getConfig = () => {
    const { item, fieldsConfig } = this.props;
    return fieldsConfig.find(conf => conf.get('id', '') === item.get('field_name', ''), null, Immutable.Map());
  }

  isInBlackList = (option, config) => {
    const blackList = option.get('exclude', Immutable.List());
    const fieldKey = `fieldid:${config.get('id', '')}`;
    const fieldType = config.get('type', 'string');
    return blackList.includes(fieldKey) || blackList.includes(fieldType);
  }

  isInWhiteList = (option, config) => {
    const whiteList = option.get('include', Immutable.List());
    const fieldKey = `fieldid:${config.get('id', '')}`;
    const fieldType = config.get('type', 'string');
    return whiteList.includes(fieldType) || whiteList.includes(fieldKey);
  }

  getoperators = () => {
    const { operators } = this.props;
    const config = this.getConfig();
    return operators
      .filter(option => (!this.isInBlackList(option, config) && this.isInWhiteList(option, config)))
      .map(parseConfigSelectOptions)
      .toArray();
  }

  getfieldsConfig = () => {
    const { fieldsConfig } = this.props;
    return fieldsConfig
      .filter(fieldConfig => fieldConfig.get('columnable', true))
      .map(parseConfigSelectOptions)
      .toArray();
  }

  render() {
    const { item, disabled, type, isCountColumn } = this.props;
    const fieldOptions = this.getfieldsConfig();
    const opOptions = this.getoperators();
    const disableField = disabled || isCountColumn;
    const disableOp = disabled || item.get('field_name', '') === '';
    const disableLabel = disabled || item.get('field_name', '') === '';

    return (
      <FormGroup className="form-inner-edit-row">
        <Col sm={1} className="text-center">
          <DragHandle />
        </Col>

        <Col smHidden mdHidden lgHidden>
          <label htmlFor="field_field">Field</label>
        </Col>
        <Col sm={4}>
          {!isCountColumn && (
            <Field
              fieldType="select"
              clearable={false}
              options={fieldOptions}
              value={item.get('field_name', '')}
              onChange={this.onChangeField}
              disabled={disableField}
              allowCreate={true}
            />
          )}
        </Col>

        <Col smHidden mdHidden lgHidden>
          {type !== reportTypes.SIMPLE && (
            <label htmlFor="operator_field">Function</label>
          )}
        </Col>
        <Col sm={3}>
          {type !== reportTypes.SIMPLE && (
            <Field
              fieldType="select"
              clearable={false}
              options={opOptions}
              value={item.get('op', '')}
              onChange={this.onChangeOperator}
              disabled={disableOp}
            />
          )}
        </Col>

        <Col smHidden mdHidden lgHidden>
          <label htmlFor="label_field">Label</label>
        </Col>
        <Col sm={3}>
          <Field
            value={item.get('label', '')}
            onChange={this.onChangeLabel}
            disabled={disableLabel}
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

export default SortableElement(Column);
