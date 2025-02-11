import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { FormGroup, Col, ControlLabel, Panel, PanelGroup, Button } from 'react-bootstrap';
import Field from '@/components/Field';
import { Actions, Conditions } from '@/components/Elements';
import { reportUsageFieldsSelector } from '@/selectors/reportSelectors';
import { getSettings } from '@/actions/settingsActions';
import { getConfig, getFieldName, getConditionFromConfig, parseConfigSelectOptions } from '@/common/Util'

class Segmentation extends Component {

  static propTypes = {
    data: PropTypes.instanceOf(Immutable.Map),
    linesFields: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    onChange: PropTypes.func.isRequired,
    onRemove: PropTypes.func.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    data: Immutable.Map(),
    linesFields: Immutable.List(),
    mode: 'create',
  };

  static defaultCondition = Immutable.Map({
    field: '',
    op: '',
    value: '',
  });

  static fileTypesOptions = getConfig(['exportGenerator', 'fileTypes'], Immutable.List())
    .map(parseConfigSelectOptions)
    .toArray();

  static conditionsOperators = getConditionFromConfig(['exportGenerator', 'recordTypeConditions']);

  state = {
    fileNamePlaceholdersOpen: false,
  };

  componentDidMount() {
    this.props.dispatch(getSettings([
      'lines.fields',
    ]));
  }

  onChangeName = (e) => {
    const { value } = e.target;
    this.props.onChange('name', value);
  }

  onChangeActive = (e) => {
    const { value } = e.target;
    this.props.onChange('enabled', value === 'yes');
  }

  onChangeFileName = (e) => {
    const { value } = e.target;
    this.props.onChange('file_name', value);
  }

  onChangeType = (type) => {
    this.props.onChange(['generator', 'type'], type);
  }

  onChangeRecordTypes = (recordTypes) => {
    const { data } = this.props;
    if (Array.isArray(recordTypes) && recordTypes.length > 0) {
      // remove removed types
      const recordTypeMappingWithRemoved = data
        .getIn(['generator', 'record_type_mapping'], Immutable.List())
        .filter(recordType => recordTypes.includes(recordType.get('record_type', '')));
      // add new added types
      const recordTypeMappingWithAdded = recordTypeMappingWithRemoved.withMutations((recordMapWithMutations) => {
        const existingTypes = data.getIn(['generator', 'record_type_mapping'], Immutable.List()).map(recordTypeMap => recordTypeMap.get('record_type', ''));
        recordTypes.forEach((recordType) => {
          if(!existingTypes.includes(recordType)) {
            recordMapWithMutations.push(Immutable.Map({
              record_type: recordType,
              conditions: Immutable.List()
            }));
          }
        });
      });
      return this.props.onChange(['generator', 'record_type_mapping'], recordTypeMappingWithAdded);
    } else {
      this.props.onChange(['generator', 'record_type_mapping'], Immutable.List());
    }
  }

  onAddCondition = (recordTypeIndex) => {
    const { data } = this.props;
    const path = ['generator', 'record_type_mapping', recordTypeIndex, 'conditions'];
    const recordTypeMappingConditions = data.getIn(path, Immutable.List());
    this.props.onChange(path, recordTypeMappingConditions.push(Segmentation.defaultCondition));
  }

  onRemoveCondition = (recordTypeIndex) => (condIndex) => {
    const { data } = this.props;
    const path = ['generator', 'record_type_mapping', recordTypeIndex, 'conditions'];
    const recordTypeMappingConditions = data.getIn(path, Immutable.List());
    this.props.onChange(path, recordTypeMappingConditions.delete(condIndex));
  }

  onChangeConditionField = (recordTypeIndex) => (condIndex, value) => {
    const path = ['generator', 'record_type_mapping', recordTypeIndex, 'conditions'];
    this.props.onChange([...path, condIndex, 'field'], value);
  }
  
  onChangeConditionOp = (recordTypeIndex) => (condIndex, value) => {
    const { data } = this.props;
    const conditionsPath = ['generator', 'record_type_mapping', recordTypeIndex, 'conditions'];
    const opPath = [...conditionsPath, condIndex, 'op'];
    this.props.onChange(opPath, value);
    // reset value if type is not same
    const oldOp = data.getIn(opPath, '');
    const oldOpType = Segmentation.conditionsOperators
      .find(op => op.get('id', '') === oldOp, null, Immutable.Map())
      .get('type', '');
    const newOpType = Segmentation.conditionsOperators
      .find(op => op.get('id', '') === value, null, Immutable.Map())
      .get('type', '');
    if (oldOpType !== newOpType) {
      this.props.onChange([...conditionsPath, condIndex, 'value'], '');
    }
  }
  
  onChangeConditionValue = (recordTypeIndex) => (condIndex, value) => {
    const path = ['generator', 'record_type_mapping', recordTypeIndex, 'conditions'];
    this.props.onChange([...path, condIndex, 'value'], value);
  }

  onToggleFileNamePlaceHolders = () => {
    this.setState({ fileNamePlaceholdersOpen: !this.state.fileNamePlaceholdersOpen })
  }

  getListActions = () => [{
    type: 'add',
    actionStyle: 'primary',
    actionSize: 'xsmall',
    label: 'Add Condition',
    onClick: this.onAddCondition,
  }];
  
  renderRecordTypesMapping = () => {
    const { data, linesFields } = this.props;
    const conditions = data
      .getIn(['generator', 'record_type_mapping'], Immutable.List())
      .map((recordType, idx) => {
        const isEmpty = recordType.get('conditions', Immutable.List()).isEmpty();
        const recordTypeName = recordType.get('record_type', '');
        return (
          <Panel
            className="collapsible"
            collapsible
            header={`Record Type: ${recordTypeName}`}
            key={`${recordTypeName}_${idx}`}
            defaultExpanded={isEmpty}
          >
            {!isEmpty && (
              <Conditions
                conditions={recordType.get('conditions', Immutable.List())}
                fields={linesFields}
                operators={Segmentation.conditionsOperators}
                onChangeField={this.onChangeConditionField(idx)}
                onChangeOperator={this.onChangeConditionOp(idx)}
                onChangeValue={this.onChangeConditionValue(idx)}
                onRemove={this.onRemoveCondition(idx)}
                noConditionsLabel=""
              />
            )}
            <div className="ml15">
              <Actions actions={this.getListActions()} data={idx} />
            </div>
          </Panel>
        )
      });
    return (
      <PanelGroup className="mt5">
        {conditions}
      </PanelGroup>
    );
  }


  renderOpenFilenamePlaceholders = () => (
    <Button
      bsStyle="link"
      bsSize="xsmall"
      className="pb0 pt0 pr0 pl0"
      onClick={this.onToggleFileNamePlaceHolders}
    >
      Placeholders
    </Button>
  );
  
  render() {
    const { data, mode } = this.props;
    // const { fileNamePlaceholdersOpen } = this.state;
    const recordTypesValue = data
      .getIn(['generator', 'record_type_mapping'], Immutable.List())
      .reduce((acc, recordType) => {
        return acc.push(recordType.get('record_type', ''));
      }, Immutable.List())
      .filter(value => value !== '')
      .toArray();
    const isNameEditable = mode === 'create';
    return (
      <>
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('name', 'export_generator', 'Name')}
          </Col>
          <Col sm={8} lg={9}>
            <Field value={data.get('name', '')} onChange={this.onChangeName} disabled={!isNameEditable}/>
          </Col>
        </FormGroup>

        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('status', 'export_generator', 'Status')}
          </Col>
          <Col sm={4}>
            <span>
              <span className="mr20 inline">
                <Field
                  fieldType="radio"
                  onChange={this.onChangeActive}
                  name="step-active-status"
                  value="yes"
                  label={getFieldName('status_active', 'export_generator', 'Active')}
                  checked={data.get('enabled', true)}
                />
              </span>
              <span className="inline">
                <Field
                  fieldType="radio"
                  onChange={this.onChangeActive}
                  name="step-active-status"
                  value="no"
                  label={getFieldName('status_disabled', 'export_generator', 'Not Active')}
                  checked={!data.get('enabled', true)}
                />
              </span>
            </span>
          </Col>
        </FormGroup>
{/*
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('file_name', 'export_generator', 'File Name')}
          </Col>
          <Col sm={8} lg={9}>
            <Field value={data.get('file_name', '')} disabled={true} placeholder="Filename will be autogenerated"/>
            <Field value={data.get('file_name', '')} onChange={this.onChangeFileName} suffix={this.renderOpenFilenamePlaceholders()}/>
            <Panel collapsible expanded={fileNamePlaceholdersOpen} className="mb0 placeholder-block no-border">
              <Well bsSize="small" className="mb0">
                <small>
                  <ul className="mb0">
                    <li><code>&#123;$sequence_num&#125;</code> - {getFieldName('sequence_number_placeholder_description', 'export_generator', 'Sequence number')}</li>
                    <li><code>&#123;$date_YYYYMMDDHHMMSS&#125;</code> - {getFieldName('date_YYYYMMDDHHMMSS_placeholder_description', 'export_generator', 'Date in format 20201231235959')}</li>
                    <li><code>&#123;$date&#125;</code> - {getFieldName('date', 'export_generator', 'Date')}</li>
                  </ul>
                </small>
              </Well>
            </Panel>
          </Col>
        </FormGroup>
*/}

        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('export_file_type', 'export_generator', 'Export file Type')}
          </Col>
          <Col sm={8} lg={9}>
          <Field
            fieldType="select"
            value={data.getIn(['generator', 'type'], '')}
            options={Segmentation.fileTypesOptions}
            onChange={this.onChangeType}
          />
          </Col>
        </FormGroup>

        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('record_type', 'export_generator', 'Record Type')}
          </Col>
          <Col sm={8} lg={9}>
            <Field
              fieldType="tags"
              value={recordTypesValue}
              onChange={this.onChangeRecordTypes}
            />
          </Col>
          <Col sm={12}>
            <Panel header={`Record Type Conditions`} className="mt10">
              {this.renderRecordTypesMapping()}
            </Panel>
          </Col>
        </FormGroup>
      </>
    );
  }

}

const mapStateToProps = (state, props) => ({
  linesFields: reportUsageFieldsSelector(state, props),
});

export default connect(mapStateToProps)(Segmentation);
