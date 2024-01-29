import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Form, FormGroup, Col, Panel, Label } from 'react-bootstrap';
import { CreateButton, LoadingItemPlaceholder } from '@/components/Elements';
import {
  eventPropertyTypesSelector,
  eventThresholdOperatorsSelectOptionsSelector,
  eventThresholdFieldsSelectOptionsSelector,
} from '@/selectors/eventSelectors';
import { eventsSettingsSelector } from '@/selectors/settingsSelector';
import FraudEventDetails from './FraudEventDetails';
import FraudEventCondition from './FraudEventCondition';
import FraudEventThreshold from './FraudEventThreshold';
import { getSettings } from '@/actions/settingsActions';
import { getEventRates } from '@/actions/eventActions';

class FraudEvent extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    mode: PropTypes.string,
    eventsSettings: PropTypes.instanceOf(Immutable.Map),
    eventPropertyType: PropTypes.instanceOf(Immutable.Set),
    thresholdFieldsSelectOptions: PropTypes.array,
    thresholdOperatorsSelectOptions: PropTypes.array,
    errors: PropTypes.instanceOf(Immutable.Map),
    setError: PropTypes.func.isRequired,
    updateField: PropTypes.func.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    item: Immutable.Map(),
    mode: 'create',
    eventsSettings: Immutable.Map(),
    eventPropertyType: Immutable.Set(),
    thresholdFieldsSelectOptions: [],
    thresholdOperatorsSelectOptions: [],
    errors: Immutable.Map(),
  };

  state = {
    status: 'loading', // one of [done, loadin, error]
  };

  componentDidMount() {
    this.initEvent();
  }

  initEvent = () => {
    this.props.dispatch(getSettings(['lines.fields']))
      .then(this.initEventRates)
      .then(this.setStatusDone)
      .catch(this.setStatusError);
  }

  initEventRates = () => {
    const { item } = this.props;
    const ratesToFetch = item.getIn(['ui_flags', 'eventUsageType', 'arate_key'], Immutable.List());
    return (!ratesToFetch.isEmpty())
      ? this.props.dispatch(getEventRates(ratesToFetch))
      : Promise.resolve();
  }

  setStatusDone = () => {
    this.setState(() => ({ status: 'done' }));
  }

  setStatusError = () => {
    this.setState(() => ({ status: 'error' }));
  }

  getEventRates = (eventRates) => {
    if (!eventRates.isEmpty()) {
      this.props.dispatch(getEventRates(eventRates));
    }
  }

  onChangeText = path => (e) => {
    const { value } = e.target;
    this.props.updateField(path, value);
  };

  onChangeSelect = path => (value) => {
    this.props.updateField(path, value);
  }

  onRemoveCondition = (index) => {
    const { item } = this.props;
    const conditions = item.getIn(['conditions', 0], Immutable.List()).delete(index);
    this.props.updateField(['conditions', 0], conditions);
  }

  onUpdateCondition = (path, value) => {
    this.props.updateField(['conditions', 0, ...path], value);
  }

  onChangeThreshold = (path, value) => {
    this.props.updateField(['threshold_conditions', 0, ...path], value);
  }

  setEventUsageType = (usageTypes) => {
    this.props.updateField(['ui_flags', 'eventUsageType'], usageTypes);
  }

  onAddCondition = () => {
    const { item } = this.props;
    const conditions = Immutable.List([
      ...item.getIn(['conditions', 0], Immutable.List()),
      Immutable.Map({ field: '', op: '', value: Immutable.List() }),
    ]);
    this.props.updateField(['conditions', 0], conditions);
  }

  renderConditions = () => {
    const { item } = this.props;
    const usedFields = item.getIn(['conditions', 0], Immutable.List()).map(condition => condition.get('field', ''));
    const eventUsageTypes = item.getIn(['ui_flags', 'eventUsageType'], Immutable.Map());
    const conditionsRows = item.getIn(['conditions', 0], Immutable.List()).map((condition, index) => (
      <FraudEventCondition
        key={`condition_0_${index}`}
        condition={condition}
        index={index}
        usedFields={usedFields}
        eventUsageTypes={eventUsageTypes}
        onUpdate={this.onUpdateCondition}
        onRemove={this.onRemoveCondition}
        setEventUsageType={this.setEventUsageType}
        getEventRates={this.getEventRates}
      />
    ));
    const disableAdd = false; // fieldsOptions.isEmpty();
    const disableCreateNewtitle = disableAdd ? 'No more filter options' : '';
    return (
      <div className="fraud-event-conditions">
        <Col sm={12} className="form-inner-edit-rows">
          { !conditionsRows.isEmpty() && (
            <FormGroup className="form-inner-edit-row">
              <Col sm={4} xsHidden><label htmlFor="field_field">Filter</label></Col>
              <Col sm={2} xsHidden><label htmlFor="operator_field">Operator</label></Col>
              <Col sm={4} xsHidden><label htmlFor="value_field">Value</label></Col>
            </FormGroup>
          )}
        </Col>
        <Col sm={12}>
          { conditionsRows }
        </Col>
        <Col sm={12} className="pl0 pr0">
          <CreateButton
            onClick={this.onAddCondition}
            label="Add Condition"
            disabled={disableAdd}
            title={disableCreateNewtitle}
          />
        </Col>
      </div>
    );
  }

  renderThreshold = () => {
    const {
      item,
      eventPropertyType,
      thresholdFieldsSelectOptions,
      thresholdOperatorsSelectOptions,
      errors,
    } = this.props;
    const index = 0;
    const threshold = item.getIn(['threshold_conditions', 0, index], Immutable.Map());
    return (
      <Col sm={12}>
        <FormGroup className="form-inner-edit-row">
          <Col sm={3} xsHidden><label htmlFor="threshold_field">
            Field
            <span className="danger-red"> *</span></label>
          </Col>
          <Col sm={3} xsHidden><label htmlFor="threshold_operator">
            Operator
            <span className="danger-red"> *</span></label>
          </Col>
          <Col sm={4} xsHidden><label htmlFor="threshold_value">
            Value <span className="danger-red"> *</span></label>
          </Col>
          {eventPropertyType.size === 1 && !['aprice', 'final_charge'].includes(threshold.getIn(['field'], '')) && (
            <Col sm={2} xsHidden><label htmlFor="threshold_uof">
              Unit of measure
              <span className="danger-red"> *</span></label>
            </Col>
          )}
        </FormGroup>
        <FraudEventThreshold
          threshold={threshold}
          index={index}
          eventPropertyType={eventPropertyType}
          thresholdFieldsSelectOptions={thresholdFieldsSelectOptions}
          thresholdOperatorsSelectOptions={thresholdOperatorsSelectOptions}
          onUpdate={this.onChangeThreshold}
          errors={errors}
          setError={this.props.setError}
        />
      </Col>
    );
  }

  render() {
    const { status } = this.state;
    const { mode, item, eventsSettings, errors } = this.props;
    if (status === 'loading') {
      return (<LoadingItemPlaceholder />);
    }
    if (status === 'error') {
      return (<Label bsStyle="danger">Oops! Something went wrong, please try again.</Label>);
    }
    return (
      <Form horizontal>
        <Panel header={<span>Details</span>}>
          <FraudEventDetails
            item={item}
            eventsSettings={eventsSettings}
            onUpdate={this.props.updateField}
            setError={this.props.setError}
            errors={errors}
          />
        </Panel>
        <Panel header={<span>Conditions</span>} collapsible defaultExpanded={['create', 'clone'].includes(mode)} className="collapsible">
          { this.renderConditions() }
        </Panel>
        <Panel header={<span>Threshold</span>}>
          { this.renderThreshold() }
        </Panel>
      </Form>
    );
  }
}

const mapStateToProps = (state, props) => ({
  eventPropertyType: eventPropertyTypesSelector(state, props),
  thresholdFieldsSelectOptions: eventThresholdFieldsSelectOptionsSelector(state, { ...props, eventType: 'fraud' }),
  thresholdOperatorsSelectOptions: eventThresholdOperatorsSelectOptionsSelector(null, { eventType: 'fraud' }),
  eventsSettings: eventsSettingsSelector(state, props),
});

export default connect(mapStateToProps)(FraudEvent);
