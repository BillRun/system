import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import { Form, FormGroup, Col, ControlLabel, Panel, HelpBlock } from 'react-bootstrap';
import { getConditionDescription } from './../EventsUtil';
import Field from '@/components/Field';
import { Actions, CreateButton } from '@/components/Elements';
import BalanceEventCondition from './BalanceEventCondition';
import {
  validateFieldEventCode,
} from '@/actions/eventActions';
import { usageTypesDataSelector, propertyTypeSelector, currencySelector } from '@/selectors/settingsSelector';

class BalanceEvent extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    updateField: PropTypes.func.isRequired,
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    errors: PropTypes.instanceOf(Immutable.Map),
    currency: PropTypes.string,
    setError: PropTypes.func.isRequired,
  };

  static defaultProps = {
    item: Immutable.Map(),
    propertyTypes: Immutable.List(),
    usageTypesData: Immutable.List(),
    errors: Immutable.Map(),
    currency: '',
  };

  state = {
    editedConditionIndex: -1,
  };

  onChangeEventCode = (e) => {
    const { value } = e.target;
    const isValid = validateFieldEventCode(value);
    this.props.setError('event_code', isValid === true ? null : isValid);
    this.props.updateField(['event_code'], value);
  };

  onChangeField = path => (e) => {
    const { value } = e.target;
    this.props.updateField(path, value);
  };

  onChangeActive = (e) => {
    const { value } = e.target;
    this.props.updateField(['active'], value === 'yes');
  };

  addCondition = () => {
    const { item } = this.props;
    const conditions = item.get('conditions', Immutable.List()).push(Immutable.Map());
    this.props.updateField(['conditions'], conditions);
    this.setState({
      editedConditionIndex: conditions.size - 1,
    });
  };

  editCondition = index => () => {
    this.setState({
      editedConditionIndex: index,
    });
  }

  hideEditCondition = () => {
    this.setState({
      editedConditionIndex: -1,
    });
  }

  removeCondition = index => () => {
    const { item } = this.props;
    const conditions = item.get('conditions', Immutable.List()).delete(index);
    this.props.updateField(['conditions'], conditions);
  };

  renderConditionEditForm = (condition, index) => {
    const { propertyTypes, usageTypesData } = this.props;
    return (
      <BalanceEventCondition
        item={condition}
        index={index}
        onChangeField={this.props.updateField}
        propertyTypes={propertyTypes}
        usageTypesData={usageTypesData}
      />
    );
  }

  showConditionDetails = (index) => {
    const { editedConditionIndex } = this.state;
    return editedConditionIndex === index;
  }

  getConditionActions = index => [
    { type: 'edit', onClick: this.editCondition(index), show: !this.showConditionDetails(index) },
    { type: 'collapse', onClick: this.hideEditCondition, show: this.showConditionDetails(index) },
    { type: 'remove', onClick: this.removeCondition(index) },
  ];

  renderCondition = (condition, index) => {
    const { propertyTypes, usageTypesData, currency } = this.props;
    const activityType = 'counter';
    const params = ({ propertyTypes, usageTypesData, currency, activityType });
    return (
      <FormGroup key={index} className="mb0">
        <Col sm={12}>
          <div className="inline pr100">
            { getConditionDescription(condition, params) }
          </div>
          <span className="pull-right List row pr10 line-actions">
            <Actions actions={this.getConditionActions(index)} />
          </span>
        </Col>
        <Col sm={12}>
          <Panel collapsible expanded={this.state.editedConditionIndex === index}>
            { this.renderConditionEditForm(condition, index) }
          </Panel>
        </Col>
      </FormGroup>
    );
  }

  renderConditions = () => this.props.item.get('conditions', Immutable.List())
    .map(this.renderCondition)
    .toArray();

  render() {
    const { item, errors } = this.props;
    const isEventCodeError = errors.get('event_code', false);
    return (
      <Form horizontal>
        <Panel header={<span>Details</span>}>
          <FormGroup validationState={isEventCodeError ? 'error' : null}>
            <Col componentClass={ControlLabel} sm={3}>
              Event Code
              <span className="danger-red"> *</span>
            </Col>
            <Col sm={7}>
              <Field id="label" onChange={this.onChangeEventCode} value={item.get('event_code', '')} />
              { isEventCodeError && (
                <HelpBlock>{isEventCodeError}</HelpBlock>
              )}
            </Col>
          </FormGroup>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3}>
              Description
            </Col>
            <Col sm={7}>
              <Field id="description" onChange={this.onChangeField(['event_description'])} value={item.get('event_description', '')} />
            </Col>
          </FormGroup>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3}>Status</Col>
            <Col sm={7}>
              <span>
                <span className="mr20 inline">
                  <Field
                    fieldType="radio"
                    onChange={this.onChangeActive}
                    name="step-active-status"
                    value="yes"
                    label="Active"
                    checked={item.get('active', true)}
                  />
                </span>
                <span className="inline">
                  <Field
                    fieldType="radio"
                    onChange={this.onChangeActive}
                    name="step-active-status"
                    value="no"
                    label="Inactive"
                    checked={!item.get('active', true)}
                  />
                </span>
              </span>
            </Col>
          </FormGroup>
        </Panel>

        <Panel header={<span>Conditions</span>}>
          <FormGroup>
            <Col sm={12}>
              { this.renderConditions() }
            </Col>
            <Col sm={12}>
              <CreateButton onClick={this.addCondition} label="Add Condition" />
            </Col>
          </FormGroup>
        </Panel>
      </Form>
    );
  }
}

const mapStateToProps = (state, props) => ({
  propertyTypes: propertyTypeSelector(state, props),
  usageTypesData: usageTypesDataSelector(state, props),
  currency: currencySelector(state, props),
});

export default connect(mapStateToProps)(BalanceEvent);
