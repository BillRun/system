import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Button, FormGroup, Col, HelpBlock, InputGroup, Panel} from 'react-bootstrap';
import Field from '@/components/Field';
import { reportUsageFieldsSelector } from '@/selectors/reportSelectors';
import { parseConfigSelectOptions } from '@/common/Util'


class MapRow extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    index: PropTypes.number.isRequired,
    isFixed: PropTypes.bool,
    exportType: PropTypes.string,
    linesFields: PropTypes.instanceOf(Immutable.List),
    paramsOptions: PropTypes.instanceOf(Immutable.List),
    onChange: PropTypes.func.isRequired,
    onRemove: PropTypes.func.isRequired,
  }

  static defaultProps = {
    item: Immutable.Map(),
    linesFields: Immutable.List(),
    paramsOptions: Immutable.List(),
    isFixed: false,
    exportType: '',
  }

  static defaultLinkedEntity = Immutable.Map({
    field_name: '',
    entity: 'line',
  });

  static opOptions = [
    {value: 'hard_coded_value', label: 'Fixed value'},
    {value: 'predefined_values', label: 'Dynamic value'},
    {value: 'param_name', label: 'Parameter'},
    {value: 'linked_entity', label: 'Related field'},
  ];

  static typeOptions = [
    {value: 'string', label: 'String'},
    {value: 'date', label: 'Date'},
    // {value: 'autoinc', label: 'Auto Increment'},
    {value: 'int', label: 'Integer'}, 
  ];
  
  static predefinedValueOptions = [
    {value: 'now', label: 'Now'},
    {value: 'number_of_data_records', label: 'Number Of Data Records'}, 
    {value: 'number_of_records', label: 'Number Of Records'}, 
  ];

  static paddingDirOptions = [
    {value: 'left', label: 'Left'},
    {value: 'right', label: 'Right'},
  ];

  getValue = () => {
    const { item } = this.props;
    const selectedOp = this.getOp();
    if (selectedOp === 'param_name') {
      return item.get('param_name', '');
    }
    if (selectedOp === 'predefined_values') {
      return item.get('predefined_values', '');
    }
    if (selectedOp === 'hard_coded_value') {
      return item.get('hard_coded_value', '');
    }
    if (selectedOp === 'linked_entity') {
      return item.getIn(['linked_entity', 'field_name'], '');
    }
    return '';
  }

  getType = () => {
    const { item } = this.props;
    return item.get('type', 'string');
  }

  getOp = () => {
    const { item } = this.props;
    if (item.has('predefined_values')) {
      return 'predefined_values';
    }
    if (item.has('hard_coded_value')) {
      return 'hard_coded_value';
    }
    if (item.has('param_name')) {
      return 'param_name';
    }
    if (item.has('linked_entity')) {
      return 'linked_entity';
    }
    return '';
  }

  onRemove = () => {
    const { index } = this.props;
    this.props.onRemove(index);
  }

  onChangeName = (e) => {
    const { value } = e.target;
    const { index } = this.props;
    this.props.onChange([index, 'name'], value);
  }

  onChangePath = (e) => {
    const { value } = e.target;
    const { index } = this.props;
    this.props.onChange([index, 'path'], value);
  }

  onChangeType = (value) => {
    const { index, item } = this.props;
    if (value === 'autoinc') {
      const newItem = item
        .set('type', value)
        .delete('hard_coded_value')
        .delete('param_name')
        .delete('predefined_values')
        .delete('linked_entity')
        .delete('format');
      this.props.onChange([index], newItem);
    } else {
      this.props.onChange([index, 'type'], value);
    }
  }

  onChangeOperator = (value) => {
    const { index, item } = this.props;
    const newItem = item.withMutations((itemWithMutations) => {
      itemWithMutations.delete('hard_coded_value');
      itemWithMutations.delete('param_name');
      itemWithMutations.delete('predefined_values');
      itemWithMutations.delete('linked_entity');
      if (value === 'predefined_values') {
        itemWithMutations.set('predefined_values', '');
      } else if (value === 'hard_coded_value') {
        itemWithMutations.set('hard_coded_value', '');
      } else if (value === 'param_name') {
        itemWithMutations.set('param_name', '');
      } else if (value === 'linked_entity') {
        itemWithMutations.set('linked_entity', MapRow.defaultLinkedEntity);
      }
    });
    this.props.onChange([index], newItem);
  }

  onChangeValue = (e) => {
    const { value } = e.target;
    const { index } = this.props;
    const selectedOp = this.getOp();
    this.props.onChange([index, selectedOp], value);
  }

  onChangePredefinedValue = (value) => {
    const { index } = this.props;
    this.props.onChange([index, 'predefined_values'], value);
  }

  onChangeParamName = (value) => {
    const { index } = this.props;
    this.props.onChange([index, 'param_name'], value);
  }

  onChangeLinkedEntity = (value) => {
    const { index } = this.props;
    this.props.onChange([index, 'linked_entity', 'field_name'], value);
  }
  
  onChangeFormat = (e) => {
    const { value } = e.target;
    const { index } = this.props;
    this.props.onChange([index, 'format'], value);
  }
  
  onChangePaddingDir = (value) => {
    const { index } = this.props;
    this.props.onChange([index, 'padding', 'direction'], value);
  }

  onChangePaddingLength = (e) => {
    const { value } = e.target;
    const { index } = this.props;
    this.props.onChange([index, 'padding', 'length'], value);
  }

  onChangePaddingChar = (e) => {
    const { value } = e.target;
    const { index } = this.props;
    this.props.onChange([index, 'padding', 'character'], value);
  }

  render() {
    const { item, isFixed, linesFields, exportType, paramsOptions } = this.props;
    const error = false;
    const editable = true;

    const selectedType = this.getType();
    const selectedOp = this.getOp();
    const selectedValue = this.getValue();

    const selectedFormat = item.getIn(['format'], '');
    const selectedPaddingDir = item.getIn(['padding', 'direction'], 'left');
    const selectedPaddingLength = item.getIn(['padding', 'length'], '');
    const selectedPaddingChar = item.getIn(['padding', 'character'], '');

    const disableOperator = ['', 'autoinc'].includes(selectedType); 
    const disableValue = [''].includes(selectedOp) || disableOperator; 
    const disableFormat = selectedType !== 'date'; 
    const lineFieldOptions = linesFields.map(parseConfigSelectOptions).toArray();
    const paramsSelectOptions = paramsOptions
      .map(param => ({ value: param.get('param', ''), label: param.get('param', '') }))
      .toArray();
    return (
      <>
      <FormGroup className="form-inner-edit-row" validationState={error ? 'error' : null}>
        <Col sm={12}>
          <Col smHidden mdHidden lgHidden>
            <label htmlFor="name">Field</label>
          </Col>
          <Col sm={2} className="pl0">
            <Field
              value={item.get('name', '')}
              onChange={this.onChangeName}
            />
          </Col>

          <Col smHidden mdHidden lgHidden>
            <label htmlFor="type_field">Type</label>
          </Col>
          <Col sm={2} className="pl0">
            <Field
              fieldType="select"
              clearable={false}
              options={MapRow.typeOptions}
              value={selectedType}
              onChange={this.onChangeType}
            />
          </Col>

          <Col smHidden mdHidden lgHidden>
            <label htmlFor="operator_field">Operator</label>
          </Col>
          <Col sm={2} className="pl0">
            <Field
              fieldType="select"
              clearable={false}
              options={MapRow.opOptions}
              value={selectedOp}
              disabled={disableOperator}
              onChange={this.onChangeOperator}
            />
          </Col>

          <Col smHidden mdHidden lgHidden>
            <label htmlFor="value_field">Value</label>
          </Col>
          <Col sm={3} className="pl0">
            {selectedOp === 'linked_entity' && (
              <Field
                fieldType="select"
                clearable={false}
                options={lineFieldOptions}
                value={selectedValue}
                onChange={this.onChangeLinkedEntity}
                disabled={disableValue}
              />
            )}
            { selectedOp === 'predefined_values' && (
              <Field
                fieldType="select"
                clearable={false}
                options={MapRow.predefinedValueOptions}
                value={selectedValue}
                onChange={this.onChangePredefinedValue}
                disabled={disableValue}
              />
            )}
            { selectedOp === 'param_name' && (
              <Field
                fieldType="select"
                clearable={false}
                options={paramsSelectOptions}
                value={selectedValue}
                onChange={this.onChangeParamName}
                disabled={disableValue}
              />
            )}
            {!['linked_entity', 'predefined_values', 'param_name'].includes(selectedOp) && (
              <Field
                value={selectedValue}
                disabled={disableValue}
                onChange={this.onChangeValue}
              />
            )}
          </Col>

          <Col smHidden mdHidden lgHidden>
            <label htmlFor="fromat_field">Format</label>
          </Col>
          <Col sm={2} className="pl0">
            <Field
              value={selectedFormat}
              disabled={disableFormat}
              onChange={this.onChangeFormat}
            />
          </Col>
          {editable && (
            <Col sm={1} className="actions pr0 rl0 action-fix-height">
              <Button onClick={this.onRemove} bsSize="small" className="pull-right">
                <i className="fa fa-trash-o danger-red" />
              </Button>
            </Col>
          )}
        </Col>
        { exportType ==='xml' && (
          <Col sm={12} className="mt5 pl0">
            <Field
              preffix="Path"
              value={item.get('path', '')}
              onChange={this.onChangePath}
            />
          </Col>
        )}
        {isFixed && (
          <Col sm={12} className="mt5 pl0">
            <Panel className="collapsible" collapsible header="Padding">
              <Col sm={4} className="pl0">
                <InputGroup>
                  <InputGroup.Addon>Direction</InputGroup.Addon>
                  <Field
                    fieldType="select"
                    clearable={false}
                    options={MapRow.paddingDirOptions}
                    value={selectedPaddingDir}
                    onChange={this.onChangePaddingDir}
                  />
                </InputGroup>
              </Col>
              <Col sm={4} className="pl0">
                <Field
                  fieldType="integer"
                  preffix="Length"
                  value={selectedPaddingLength}
                  onChange={this.onChangePaddingLength}
                />
              </Col>
              <Col sm={4} className="pl0">
                <Field
                  preffix="Character"
                  value={selectedPaddingChar}
                  onChange={this.onChangePaddingChar}
                />
              </Col>
            </Panel>
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
      <hr/>
      </>
    );
  }

}


const mapStateToProps = (state, props) => ({
  linesFields: reportUsageFieldsSelector(state, props),
});

export default connect(mapStateToProps)(MapRow);
