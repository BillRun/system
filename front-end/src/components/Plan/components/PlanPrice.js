import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Button, FormGroup, Col, ControlLabel, HelpBlock } from 'react-bootstrap';
import Field from '@/components/Field';
import { getConfig } from '@/common/Util';


export default class PlanPrice extends Component {

  static propTypes = {
    onPlanTariffRemove: PropTypes.func.isRequired,
    onPlanCycleUpdate: PropTypes.func.isRequired,
    onPlanPriceUpdate: PropTypes.func.isRequired,
    index: PropTypes.number.isRequired,
    count: PropTypes.number.isRequired,
    mode: PropTypes.string,
    isTrialExist: PropTypes.bool.isRequired,
    item: PropTypes.instanceOf(Immutable.Map),
    planCycleUnlimitedValue: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.number,
    ]).isRequired,
  }

  static defaultProps = {
    planCycleUnlimitedValue: getConfig('planCycleUnlimitedValue', 'UNLIMITED'),
    mode: 'create',
  };

  state = {
    cycleError: '',
    priceError: '',
  }

  shouldComponentUpdate(nextProps, nextState) {
    const { props: { count, index, mode }, state: { cycleError, priceError } } = this;
    // if count was changed and this is last item
    const isLastAdded = count < nextProps.count && index === (count - 1);
    const isLastremoved = count > nextProps.count && index === (count - 2);
    const error = nextState.cycleError !== cycleError || nextState.priceError !== priceError;
    return !Immutable.is(this.props.item, nextProps.item)
      || index !== nextProps.index
      || mode !== nextProps.mode
      || isLastAdded
      || isLastremoved
      || error;
  }

  onCycleUpdateValue = (value) => {
    const { index } = this.props;
    let cycleError = '';
    let newValue = value;
    if (typeof value === 'undefined' || value === null || value === '') {
      cycleError = 'Cycle is required';
      newValue = 0;
    } else if (isNaN(value) || !(Math.sign(value) > 0)) {
      cycleError = 'Value must be positive number';
      newValue = 0;
    }
    this.props.onPlanCycleUpdate(index, newValue);
    this.setState({ cycleError });
  }

  onCycleUpdateEvent = (e) => {
    this.onCycleUpdateValue(e.target.value);
  }

  onPlanPriceUpdate = (e) => {
    const { index } = this.props;
    const { value } = e.target;
    let priceError = '';
    if (typeof value === 'undefined' || value === null || value === '') {
      priceError = 'Price is required';
    } else if (isNaN(value) || !(Math.sign(value) >= 0)) {
      priceError = 'Value must be positive number';
    }
    this.setState({ priceError });
    this.props.onPlanPriceUpdate(index, value);
  }

  onPlanTariffRemove = () => {
    const { index } = this.props;
    this.props.onPlanTariffRemove(index);
  }

  getCycleDisplayValue = () => {
    const { item, planCycleUnlimitedValue } = this.props;
    const to = item.get('to', '');
    const from = item.get('from', '');
    switch (to) {
      case planCycleUnlimitedValue: return 'Infinite';
      case '': return '';
      default: return (to - from);
    }
  }

  render() {
    const { cycleError, priceError } = this.state;
    const { item, index, count, isTrialExist, planCycleUnlimitedValue, mode } = this.props;
    const price = item.get('price', '');
    const trial = item.get('trial', false);
    const to = item.get('to', '');
    const cycle = this.getCycleDisplayValue();
    const isFirst = (index === 0 || (isTrialExist && index === 1));
    const isLast = ((count === 0) || (count - 1 === index));
    const showRemoveButton = trial || isLast;
    const editable = (mode !== 'view');

    return (
      <Col sm={12} className="form-inner-edit-row mb0">
        <Col sm={2} className="text-center">
          { isFirst && (
            <ControlLabel className="mb5">Period</ControlLabel>
          )}
          <p className="non-editable-field mb0">{ index + 1 }</p>
        </Col>
        <Col sm={3} className="pr0">
          <FormGroup validationState={cycleError.length ? 'error' : null} className="ml0 mr0">
            { isFirst && (
              <ControlLabel className="mb5">Cycles</ControlLabel>
            )}
            { (to === planCycleUnlimitedValue)
              ? <Field value={cycle} disabled={true} editable={editable} />
              : <Field value={cycle} onChange={this.onCycleUpdateEvent} fieldType="number" min={0} editable={editable} />
            }
            { cycleError.length > 0 && <HelpBlock>{cycleError}.</HelpBlock>}
          </FormGroup>
        </Col>

        <Col sm={3} className="pr0">
          <FormGroup validationState={priceError.length ? 'error' : null} className="ml0 mr0">
            { isFirst && (
              <ControlLabel className="mb5">Price</ControlLabel>
            )}
            <Field onChange={this.onPlanPriceUpdate} value={price} editable={editable} fieldType="price"/>
            { priceError.length > 0 && <HelpBlock>{priceError}.</HelpBlock>}
          </FormGroup>
        </Col>

        <Col sm={4}>
          { showRemoveButton && editable &&
            <FormGroup className="actions ml0 mr0">
              { isFirst && (
                <ControlLabel className="mb5">&nbsp;</ControlLabel>
              )}
              <div className="text-left">
                <Button onClick={this.onPlanTariffRemove} bsSize="small">
                  <i className="fa fa-trash-o danger-red" />
                </Button>
              </div>
            </FormGroup>
          }
        </Col>
      </Col>
    );
  }
}
