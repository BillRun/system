import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import { FormGroup, Col, ControlLabel, InputGroup } from 'react-bootstrap';
import { upperCaseFirst } from 'change-case';
import isNumber from 'is-number';
import Field from '@/components/Field';
import { WithTooltip } from '@/components/Elements';
import UsageTypesSelector from '../../UsageTypes/UsageTypesSelector';
import { getGroupsOptions } from '@/actions/reportsActions';
import { showWarning } from '@/actions/alertsActions';
import { EventDescription } from '@/language/FieldDescriptions';
import {
  groupsOptionsSelector,
  groupsDataSelector,
  servicesDataSelector,
  propertyTypesSelector,
} from '@/selectors/listSelectors';
import { currencySelector } from '@/selectors/settingsSelector';
import {
  eventConditionsOperatorsSelectOptionsSelector,
} from '@/selectors/eventSelectors';
import {
  getBalanceConditionData,
  getPathParams,
  buildBalanceConditionPath,
  getUnitTitle,
  createGroupOption,
 } from '../EventsUtil';
import {
  getGroupUsaget,
  getUsagePropertyType,
} from '@/common/Util';

class BalanceEventCondition extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    item: PropTypes.instanceOf(Immutable.Map),
    index: PropTypes.number,
    onChangeField: PropTypes.func,
    trigger: PropTypes.string,
    limitation: PropTypes.string,
    activityType: PropTypes.string,
    groupNames: PropTypes.string,
    overGroup: PropTypes.string,
    conditionsOperators: PropTypes.array,
    groupsOptions: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    groupsData: PropTypes.instanceOf(Immutable.Map),
    servicesData: PropTypes.instanceOf(Immutable.Map),
    propertyTypeOptions: PropTypes.instanceOf(Immutable.Set),
    currency: PropTypes.string,
  }

  static defaultProps = {
    item: Immutable.Map(),
    index: -1,
    onChangeField: () => {},
    trigger: '',
    limitation: '',
    activityType: '',
    groupNames: '',
    overGroup: 'none',
    conditionsOperators: [],
    groupsOptions: Immutable.List(),
    propertyTypes: Immutable.List(),
    usageTypesData: Immutable.List(),
    groupsData: Immutable.Map(),
    servicesData: Immutable.Map(),
    propertyTypeOptions: Immutable.Set(),
    currency: '',
  }

  static defaultProps = { eventType: 'balance' };

  state = {
    unitLabel: '',
  };

  componentDidMount() {
    this.props.dispatch(getGroupsOptions());
    const { trigger } = this.props;
    // if event condition trigger property not set, set USAGE as default
    if (trigger === '') {
      const e = {target: {value: 'usagev'} };
      this.onChangeTrigger(e);
    }
  }

  componentDidUpdate(prevProps, prevState) {
    const { onChangeField, index, item, conditionsOperators } = this.props;
    const newConditionType = item.get('type', '')
    const oldConditionType = prevProps.item.get('type', '');
    if (oldConditionType !== newConditionType) {
      const convertedCondition = item.withMutations((conditionWithMutations) => {
        const selectedConditionData = getBalanceConditionData(newConditionType);
        const newValueType = selectedConditionData.get('type', 'text');
        const oldSelectedConditionData = getBalanceConditionData(oldConditionType);
        const oldValueType = oldSelectedConditionData.get('type', 'text');
        if (newValueType !== oldValueType) {
          conditionWithMutations.set('value', '');
        }
        if (newConditionType === 'reached_percentage') {
          conditionWithMutations.set('unit', '');
          conditionWithMutations.set('value', '');
        }
      });
      onChangeField(['conditions', index], convertedCondition);
    }
    // reset condition type and values if selected condition type not exists in options
    if (item.get('type', '') !== '' && conditionsOperators.findIndex(conditionsOperator => conditionsOperator.value === item.get('type', '')) === -1) {
      this.onChangeType('');
    }
  }

  onChangeTrigger = (e) => {
    const { onChangeField, item, index, limitation, servicesData } = this.props;
    const { value } = e.target;
    const limitationToSave = (value === 'usagev' && limitation === 'none' ? 'group' : limitation);
    const paths = buildBalanceConditionPath(value, limitationToSave, { activityType: '', groupNames: '', overGroup: '', servicesData });
    const condition = item.withMutations((itemWithMutation) => {
      itemWithMutation.set('paths', paths);
      itemWithMutation.set('unit', '');
      itemWithMutation.set('usaget', '');
      itemWithMutation.set('property_type', '');
    });
    onChangeField(['conditions', index], condition);
  };

  onChangeOverGroup = (e) => {
    const { onChangeField, index, trigger, activityType, groupNames, limitation, servicesData } = this.props;
    const { value } = e.target;
    const params = { activityType, groupNames, overGroup: value, servicesData };
    const paths = buildBalanceConditionPath(trigger, limitation, params);
    onChangeField(['conditions', index, 'paths'], paths);
  }

  onChangeLimitation = (e) => {
    const { onChangeField, index, trigger, activityType, groupNames, servicesData } = this.props;
    const { value } = e.target;
    const params = { activityType, groupNames, overGroup: '', servicesData };
    const paths = buildBalanceConditionPath(trigger, value, params);
    onChangeField(['conditions', index, 'paths'], paths);
    onChangeField(['conditions', index, 'property_type'], '');
    onChangeField(['conditions', index, 'unit'], '');
    onChangeField(['conditions', index, 'usaget'], '');
  };

  onChangePropertyType = (propertyType) => {
    const { onChangeField, item, index, trigger, limitation, servicesData } = this.props;
    const paths = buildBalanceConditionPath(trigger, limitation, { activityType: '', groupNames: '', overGroup: '', servicesData });
    const condition = item.withMutations((itemWithMutation) => {
      itemWithMutation.set('property_type', propertyType);
      itemWithMutation.set('paths', paths);
      itemWithMutation.set('unit', '');
      itemWithMutation.set('usaget', '');
    });
    onChangeField(['conditions', index], condition);
  };

  onChangeActivityType = (value) => {
    const { onChangeField, item, index, trigger, limitation, overGroup, servicesData } = this.props;
    const paths = buildBalanceConditionPath(trigger, limitation, { activityType: value, groupNames: '', overGroup, servicesData });
    const condition = item.withMutations((itemWithMutation) => {
      itemWithMutation.set('paths', paths);
      itemWithMutation.set('unit', '');
      itemWithMutation.set('usaget', value);
    });
    onChangeField(['conditions', index], condition);
  };

  onChangeGroupNames = (value) => {
    const { onChangeField, item, index, trigger, limitation, servicesData } = this.props;
    const paths = buildBalanceConditionPath(trigger, limitation, { activityType: '', groupNames: value, servicesData });
    const unit = value !== '' ? this.getGroupUnit(value.split(',').pop()) : '';
    const usaget = value !== '' ? this.getGroupActivityType(value.split(',').pop()) : '';

    const condition = item.withMutations((itemWithMutation) => {
      itemWithMutation.set('paths', paths);
      itemWithMutation.set('unit', unit);
      itemWithMutation.set('usaget', usaget);
    });
    onChangeField(['conditions', index], condition);
  };

  onChangeType = (value) => {
    const { onChangeField, index, item } = this.props;
    if (value === 'reached_percentage') {
      const convertedCondition = item.set('type', value).set('unit', '');
      onChangeField(['conditions', index], convertedCondition);
    } else {
      onChangeField(['conditions', index, 'type'], value);
    }
  };

  onChangeValue = (e) => {
    const { onChangeField, index } = this.props;
    const { value } = e.target;
    onChangeField(['conditions', index, 'value'], value);
  };

  onChangeTagValue = (value) => {
    const { onChangeField, index, item } = this.props;
    if (item.get('type', '') === 'reached_percentage') {
      const filterPercentageMultiValues = this.filterPercentageMultiValues(value);
      return onChangeField(['conditions', index, 'value'], filterPercentageMultiValues);
    }
    return onChangeField(['conditions', index, 'value'], value);
  };

  onChangeUnit = (unit) => {
    const { onChangeField, index } = this.props;
    onChangeField(['conditions', index, 'unit'], unit);
  };

  filterPercentageMultiValues = values => values
    .split(",")
    .filter(val => val !== '')
    .filter((val) => {
      if (!isNumber(val)) {
        this.props.dispatch(showWarning('Condition value must be numeric'));
        return false;
      }
      const numericValue = parseFloat(val);
      if (numericValue <= 0) {
        this.props.dispatch(showWarning('Condition value should be greater than zero'));
        return false
      }
      if (numericValue > 100) {
        this.props.dispatch(showWarning('Condition value cannot be greater than 100'));
        return false;
      }
      return true;
    })
    .join(',');

  filterRelevantGroups = (group) => {
    const { trigger, usageTypesData, item, groupsData } = this.props;
    const isGroupHasCost = this.props.groupsData.hasIn([group, 'cost']);
    if (trigger === 'cost' && isGroupHasCost) {
      return true;
    }
    if (trigger === 'usagev' && !isGroupHasCost) {
      const isRelevantUsageType = item.get('property_type', '') === getUsagePropertyType(usageTypesData, groupsData.getIn([group, 'usage_types'], Immutable.Map()).keySeq().first());
      return isRelevantUsageType;
    }
    return false;
  }

  getGroupNamesOptions = () => this.props.groupsOptions
    .filter(this.filterRelevantGroups)
    .map(group => createGroupOption(group, this.props.servicesData))
    .toArray();

  getPropertyTypesOptions = () => this.props.propertyTypeOptions
    .map(propType => ({ value: propType, label: upperCaseFirst(propType) }))
    .toArray();

  getGroupUnit = group => this.props.groupsData.getIn([group, 'unit'], '');
  
  getGroupActivityType = group => getGroupUsaget(this.props.groupsData.get(group, Immutable.Map()));

  onChangeMultiValues = (e) => {
    if (Array.isArray(e)) {
      this.onChangeTagValue(e.join(','));
    } else {
      this.onChangeTagValue('');
    }
  };
  renderCustomInputNumber =({ addTag, onChange, value, ...other }) => (
    <input type="number" onChange={onChange} value={value} {...other} />
  );

  render() {
    const {
      item,
      index,
      conditionsOperators,
      trigger,
      limitation,
      activityType,
      groupNames,
      overGroup,
      propertyTypes,
      usageTypesData,
      currency,
    } = this.props;

    const usaget = (limitation === 'group' ? item.get('usaget', '') : activityType);
    const propertyType = item.get('property_type', '');
    const unitLabel = getUnitTitle(item.get('unit', ''), trigger, usaget, propertyTypes, usageTypesData, currency, item.get('type', ''))
    const selectedConditionData = getBalanceConditionData(item.get('type', ''));
    const UomEnabled = trigger === 'usagev' && limitation === 'group' && groupNames !== '' && item.get('type', '') !== 'reached_percentage';
    return (
      <Col sm={12}>

        <FormGroup>
          <Col sm={4} smOffset={1} xsOffset={0} xs={12} className="text-left" componentClass={ControlLabel}>Condition Trigger</Col>
          <Col sm={7} smOffset={0} xsOffset={1} xs={11} className="pl30">
            <Col sm={12}>
              <span className="inline mr40">
                <Field
                  fieldType="radio"
                  name={`condition-trigger-${index}`}
                  id={`condition-trigger-monetary-${index}`}
                  value="cost"
                  checked={trigger === 'cost'}
                  onChange={this.onChangeTrigger}
                  label="Monetary"
                />
              </span>
              <span className="inline">
                <Field
                  fieldType="radio"
                  name={`condition-trigger-${index}`}
                  id={`condition-trigger-usagev-${index}`}
                  value="usagev"
                  checked={trigger === 'usagev'}
                  onChange={this.onChangeTrigger}
                  label="Usage"
                />
              </span>
            </Col>
          </Col>
        </FormGroup>

        <FormGroup>
          <Col sm={11} smOffset={1} xsOffset={0} xs={12} className="text-left" componentClass={ControlLabel}>Condition Limitations</Col>
          <Col sm={10} smOffset={2} xsOffset={1} xs={11}>
            <Field
              fieldType="radio"
              name={`condition-limitation-${index}`}
              id={`condition-limitation-none-${index}`}
              value="none"
              checked={limitation === 'none'}
              onChange={this.onChangeLimitation}
              disabled={trigger === 'usagev'}
              label="Total Amount"
            />
          </Col>
          <Col sm={10} smOffset={2} xsOffset={1} xs={11}>
            <Field
              fieldType="radio"
              name={`condition-limitation-${index}`}
              id={`condition-limitation-group-${index}`}
              value="group"
              checked={limitation === 'group'}
              onChange={this.onChangeLimitation}
              label="Limit to any of the Groups"
            />
          </Col>
          <Col sm={10} smOffset={2} xsOffset={2} xs={10}>
            { trigger === 'usagev' && (
              <>
                <Col sm={4} componentClass={ControlLabel}> Property Type:</Col>
                <Col sm={8} className="form-inner-edit-row pr0">
                  <Field
                    fieldType="select"
                    id={`condition-limitation-property-type-${index}`}
                    onChange={this.onChangePropertyType}
                    value={propertyType}
                    options={this.getPropertyTypesOptions()}
                    disabled={limitation !== 'group'}
                  />
                </Col>
              </>
            )}

            <Col sm={4} componentClass={ControlLabel}> Groups Included:</Col>
            <Col sm={8} className="form-inner-edit-row pr0">
              <Field
                fieldType="select"
                onChange={this.onChangeGroupNames}
                value={groupNames}
                options={this.getGroupNamesOptions()}
                disabled={limitation !== 'group' || (propertyType === '' && trigger === 'usagev')}
                multi={true}
              />
            </Col>
            {trigger === 'usagev' && item.get('type', '') !== 'reached_percentage' ? (
              <>
                <Col sm={4} componentClass={ControlLabel}>Units of Measure:</Col>
                <Col sm={8} className="form-inner-edit-row pr0">
                  <UsageTypesSelector
                    usaget={usaget}
                    unit={groupNames !== '' ? item.get('unit', '') : ''}
                    onChangeUsaget={this.onChangeActivityType}
                    onChangeUnit={this.onChangeUnit}
                    enabled={UomEnabled}
                    showUnits={true}
                    showAddButton={false}
                    showSelectTypes={false}
                  />
                </Col>
              </>
            ) : (
              <Col sm={12} className="form-inner-edit-row pr0 pn10 input-min-line-height">&nbsp;</Col>
            )}

          </Col>

          <Col sm={3} smOffset={2} xsOffset={1} xs={11}>
            <Field
              fieldType="radio"
              name={`condition-limitation-${index}`}
              id={`condition-limitation-activity-${index}`}
              value="activity_type"
              checked={limitation === 'activity_type'}
              onChange={this.onChangeLimitation}
              label="Limit to Activity Type"
            />
          </Col>
          <Col sm={7} smOffset={0} xsOffset={2} xs={10} className="form-inner-edit-row pl40 pr15">
            <UsageTypesSelector
              usaget={activityType}
              unit={item.get('unit', '')}
              onChangeUsaget={this.onChangeActivityType}
              onChangeUnit={this.onChangeUnit}
              enabled={limitation === 'activity_type'}
              showUnits={trigger === 'usagev' && limitation === 'activity_type'}
            />
          </Col>
          { trigger === 'usagev' && limitation === 'activity_type' && (
            <Col sm={10} smOffset={2} xsOffset={2} xs={10}>
              <Col sm={8} smOffset={4} className="form-inner-edit-row">
              <Col sm={12}>
                <span className="inline mr40">
                  <Field
                    fieldType="radio"
                    name={`condition-over-group-${index}`}
                    id={`condition-over-group-all-units-${index}`}
                    value="none"
                    checked={overGroup !== 'over_group'}
                    onChange={this.onChangeOverGroup}
                    enabled={limitation === 'activity_type'}
                    label={(
                      <span className="helpable">
                        <WithTooltip helpText={EventDescription.over_group}>
                          Per balance
                        </WithTooltip>
                      </span>
                    )}
                  />
                </span>
                <span className="inline">
                  <Field
                    fieldType="radio"
                    name={`condition-over-group-${index}`}
                    id={`condition-over-group-exceeding-units-${index}`}
                    value="over_group"
                    checked={overGroup === 'over_group'}
                    onChange={this.onChangeOverGroup}
                    label="Exceeding units"
                    disabled={limitation !== 'activity_type'}
                  />
                </span>
              </Col>
            </Col>
            </Col>
          )}
          </FormGroup>

          <FormGroup>
            <Col sm={4} smOffset={1} xsOffset={0} xs={12} className="text-left" componentClass={ControlLabel}>Condition Type</Col>
            <Col sm={7} smOffset={0} xsOffset={2} xs={10} className="pl40">
              <Field
                fieldType="select"
                onChange={this.onChangeType}
                value={item.get('type', '')}
                options={conditionsOperators}
              />
            </Col>
          </FormGroup>

          { selectedConditionData.get('extra_field', true) && (
            <FormGroup>
              <Col sm={4} smOffset={1} xsOffset={0} xs={12} className="text-left" componentClass={ControlLabel}>Condition Value</Col>
                <Col sm={7} smOffset={0} xsOffset={2} xs={10} className="pl40">
                  <InputGroup className="full-width">
                    {selectedConditionData.get('type', 'text') !== 'tags' ? (
                      <Field
                        id={`cond-value-${index}`}
                        onChange={this.onChangeValue}
                        value={item.get('value', '')}
                        fieldType={selectedConditionData.get('type', 'text')}
                      />
                    ) : (
                      <Field
                        fieldType="tags"
                        id={`cond-value-${index}`}
                        onChange={this.onChangeMultiValues}
                        value={String(item.get('value', '')).split(',').filter(val => val !== '')}
                        renderInput={this.renderCustomInputNumber}
                        onlyUnique={selectedConditionData.get('type', '') === 'tags'}
                      />
                    )}
                    { unitLabel !== '' && (
                      <InputGroup.Addon>{unitLabel}</InputGroup.Addon>
                    )}
                  </InputGroup>
                </Col>
            </FormGroup>
          )}
      </Col>
    );
  }
}

const mapStateToProps = (state, props) => {
  const {
    trigger, limitation, activityType, groupNames, overGroup
  } = getPathParams(props.item.get('paths', Immutable.List()));

  let conditionsOperators = eventConditionsOperatorsSelectOptionsSelector(null, BalanceEventCondition.defaultProps);
  if (limitation === 'activity_type') {
    conditionsOperators = conditionsOperators.filter(conditionsOperator => conditionsOperator.value !== 'reached_percentage');
  }

  return {
    trigger,
    limitation,
    activityType,
    groupNames,
    propertyTypeOptions: propertyTypesSelector(state, props) || Immutable.Set(),
    overGroup,
    groupsOptions: groupsOptionsSelector(state, props) || Immutable.List(),
    groupsData: groupsDataSelector(state, props) || Immutable.Map(),
    currency: currencySelector(state, props),
    servicesData: servicesDataSelector(state, props) || Immutable.Map(),
    conditionsOperators,
  };
};

export default connect(mapStateToProps)(BalanceEventCondition);
