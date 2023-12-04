import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { sentenceCase } from 'change-case';
import pluralize from 'pluralize';
import isNumber from 'is-number';
import { FormGroup, Col, ControlLabel, InputGroup, MenuItem, DropdownButton, Panel } from 'react-bootstrap';
import Field from '@/components/Field';
import { Conditions, Actions } from '@/components/Elements';
import { reportUsageFieldsSelector } from '@/selectors/reportSelectors';
import { getConfig, getFieldName, parsePeriodPhpFormat, getConditionFromConfig } from '@/common/Util'


class Filtration extends Component {

  static propTypes = {
    data: PropTypes.instanceOf(Immutable.Map),
    linesFields: PropTypes.instanceOf(Immutable.List),
    onChange: PropTypes.func.isRequired,
    onRemove: PropTypes.func.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    data: Immutable.Map(),
    linesFields: Immutable.List(),
  };

  static defaultCondition = Immutable.Map({
    field: '',
    op: '',
    value: '',
  });

  static queryPath = ['filtration', 0, 'query'];

  static timeRangePath = ['filtration', 0, 'time_range'];

  static conditionsOperators = getConditionFromConfig(['exportGenerator', 'filtrationConditions']);

  onChangeTimeRange = (e) => {
    const { value } = e.target;
    const { data } = this.props;
    const curValue = data.getIn(Filtration.timeRangePath, '-');
    const { prefix, suffix } = parsePeriodPhpFormat(curValue);
    const newValue = isNumber(value) ? Math.abs(parseFloat(value)) : '';
    this.props.onChange(Filtration.timeRangePath, `${prefix}${newValue} ${suffix}`);
  }

  onSelectTimeRangeUnit = (unit) => {
    const { data } = this.props;
    const curValue = data.getIn(Filtration.timeRangePath, '-');
    const { prefix, number } = parsePeriodPhpFormat(curValue);
    this.props.onChange(Filtration.timeRangePath, `${prefix}${number} ${unit}`);
  }

  onAddCondition = () => {
    const { data } = this.props;
    const filtrationConditions = data.getIn(Filtration.queryPath, Immutable.List());
    this.props.onChange(Filtration.queryPath, filtrationConditions.push(Filtration.defaultCondition));
  }

  onRemoveCondition = (idx) => {
    const { data } = this.props;
    const filtrationConditions = data.getIn(Filtration.queryPath, Immutable.List());
    this.props.onChange(Filtration.queryPath, filtrationConditions.delete(idx));
  }

  onChangeConditionField = (idx, value) => {
    this.props.onChange([...Filtration.queryPath, idx, 'field'], value);
  }
  
  onChangeConditionOp = (idx, value) => {
    const { data } = this.props;
    const opPath = [...Filtration.queryPath, idx, 'op'];
    this.props.onChange(opPath, value);
    // reset value if type is not same
    const oldOp = data.getIn(opPath, '');
    const oldOpType = Filtration.conditionsOperators
      .find(op => op.get('id', '') === oldOp, null, Immutable.Map())
      .get('type', '');
    const newOpType = Filtration.conditionsOperators
      .find(op => op.get('id', '') === value, null, Immutable.Map())
      .get('type', '');
    if (oldOpType !== newOpType) {
      this.props.onChange([...Filtration.queryPath, idx, 'value'], '');
    }
  }
  
  onChangeConditionValue = (idx, value) => {
    this.props.onChange([...Filtration.queryPath, idx, 'value'], value);
  }

  getListActions = () => [{
    type: 'add',
    actionStyle: 'primary',
    actionSize: 'xsmall',
    label: 'Add Condition',
    onClick: this.onAddCondition,
  }];

  render() {
    const { data, linesFields } = this.props;
    const timeRangeOptions = getConfig(['exportGenerator', 'timeRangeOptions'], Immutable.List());
    const { prefix, number, suffix } = parsePeriodPhpFormat(data.getIn(Filtration.timeRangePath, '-'));
    const timeRangeSuffix = (suffix === '')
      ? 'Select unit...'
      : sentenceCase(pluralize(suffix, Number(number)));
    return (
      <>
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>
            {getFieldName('time_range', 'export_generator', 'Time Range')}
          </Col>
          <Col sm={8} lg={9}>
          <InputGroup className="full-width">
            <Field
              fieldType="number"
              min="1"
              step="1"
              value={number}
              onChange={this.onChangeTimeRange}
              preffix={prefix}
            />
            <DropdownButton
              id="balance-period-unit"
              componentClass={InputGroup.Button}
              title={timeRangeSuffix}
            >
              {timeRangeOptions.map((timeRangeOption, idx) => (
                <MenuItem
                  key={idx}
                  eventKey={timeRangeOption}
                  onSelect={this.onSelectTimeRangeUnit}
                >
                  {sentenceCase(timeRangeOption)}
                </MenuItem>
              ))}
            </DropdownButton>
          </InputGroup>
          </Col>
        </FormGroup>

        <Panel header="Conditions" className="mb0">
          <Col sm={12}>
            <Conditions
              conditions={data.getIn(Filtration.queryPath, Immutable.List())}
              fields={linesFields}
              operators={Filtration.conditionsOperators}
              onChangeField={this.onChangeConditionField}
              onChangeOperator={this.onChangeConditionOp}
              onChangeValue={this.onChangeConditionValue}
              onRemove={this.onRemoveCondition}
            />
          </Col>
          <Col sm={12} className="mt10 ml15">
            <Actions actions={this.getListActions()} />
          </Col>
        </Panel>
      </>
    );
  }
}

const mapStateToProps = (state, props) => ({
  linesFields: reportUsageFieldsSelector(state, props),
});

export default connect(mapStateToProps)(Filtration);
  