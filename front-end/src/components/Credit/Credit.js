import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import moment from 'moment';
import { Map, List } from 'immutable';
import { Col, FormGroup, HelpBlock, Form, ControlLabel } from 'react-bootstrap';
import getSymbolFromCurrency from 'currency-symbol-map';
import { ModalWrapper } from '@/components/Elements';
import Field from '@/components/Field';
import { getRetailProductsWithRatesQuery, getSubscribersQuery } from '../../common/ApiQueries';
import { getList } from '@/actions/listActions';
import { getSettings } from '@/actions/settingsActions';
import { creditCharge } from '@/actions/creditActions';
import {
  currencySelector,
  usageTypeSelector,
  usageTypesDataSelector,
  propertyTypeSelector,
} from '@/selectors/settingsSelector';
import {
  getRateByKey,
  getRateUsaget,
  getRateUnit,
  getUnitLabel,
  getValueByUnit,
} from '@/common/Util';

class Credit extends Component {
  static defaultProps = {
    allRates: List(),
    currency: '',
    usageTypesData: List(),
    propertyTypes: List(),
    subscribers: List(),
  };

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    allRates: PropTypes.instanceOf(List),
    currency: PropTypes.string,
    usageTypesData: PropTypes.instanceOf(List),
    propertyTypes: PropTypes.instanceOf(List),
    onClose: PropTypes.func.isRequired,
    aid: PropTypes.number.isRequired,
    subscribers: PropTypes.instanceOf(List),
  };

  state = {
    validationErrors: Map({
      aprice: 'required',
      usagev: '',
      rate: 'required',
      subscriber: 'required',
    }),
    helperMsg: Map({
      aprice: '',
    }),
    paramKeyError: '',
    rateBy: 'fix',
    aprice: '',
    usagev: 1,
    rate: '',
    progress: false,
    subscriber: '',
  }

  componentDidMount() {
    const { aid } = this.props;

    this.props.dispatch(getList('all_retail_rates', getRetailProductsWithRatesQuery()));
    this.props.dispatch(getSettings('usage_types'));
    this.props.dispatch(getList('subscribers_for_aid', getSubscribersQuery(aid)));
  }

  onChangeValue = (field, value) => {
    const { validationErrors } = this.state;
    const newState = {};
    newState[field] = value;
    if (value.length === 0) {
      newState.validationErrors = validationErrors.set(field, 'required');
    } else {
      newState.validationErrors = validationErrors.set(field, '');
    }
    this.setState(newState);
  };

  updateChargingMessage = (usagev, aprice) => {
    const { currency } = this.props;
    const { helperMsg, rateBy } = this.state;
    if (rateBy !== 'fix') {
      return;
    }
    const costValue = usagev !== '' ? usagev * aprice : aprice;
    const displayCost = `${Math.abs(costValue)}${getSymbolFromCurrency(currency)}`;
    const msg = costValue >= 0
      ? `Subscriber will be charged by ${displayCost}`
      : `${displayCost} will be refunded to the subscriber`;
    this.setState({ helperMsg: helperMsg.set('aprice', msg) });
  }

  onChangeCreditUsagevValue = (e) => {
    const { value } = e.target;
    const { aprice } = this.state;
    this.onChangeValue('usagev', value);
    this.updateChargingMessage(value, aprice);
  }

  onChangeCreditApriceValue = (e) => {
    const { value } = e.target;
    const { usagev } = this.state;
    this.onChangeValue('aprice', value);
    this.updateChargingMessage(usagev, value);
  }

  onChangeRateValue = (value) => {
    this.onChangeValue('rate', value);
  }

  onChangeSubscriberValue = (value) => {
    this.onChangeValue('subscriber', value);
  }

  onChangeCreditBy = (e) => {
    const { value } = e.target;
    const { validationErrors, helperMsg } = this.state;
    let newState;
    if (value === 'fix') {
      newState = {
        rateBy: value,
        usagev: 1,
        validationErrors: validationErrors.set('aprice', 'required').set('usagev', ''),
        helperMsg: helperMsg.set('aprice', ''),
      };
    } else {
      newState = {
        rateBy: value,
        aprice: '',
        usagev: '',
        validationErrors: validationErrors.set('aprice', '').set('usagev', 'required'),
        helperMsg: helperMsg.set('aprice', 'The refund amount will be calculated based on the volume'),
      };
    }
    this.setState(newState);
  }

  onCreditCharge = () => {
    const { aid, propertyTypes, usageTypesData } = this.props;
    const { rateBy, aprice, usagev, rate, validationErrors, subscriber } = this.state;
    if (validationErrors.valueSeq().includes('required')) {
      return;
    }
    const sid = (subscriber === 'account_level') ? 0 : subscriber;

    let params = [
      { aid },
      { sid },
      { rate },
      { credit_time: moment().toISOString() },
    ];
    if (rateBy === 'fix') {
      params = [...params, { aprice }, { usagev }];
    } else {
      const selectedRate = this.getSelectedRate(rate);
      const usaget = getRateUsaget(selectedRate);
      const unit = getRateUnit(selectedRate, usaget);
      params = [...params,
        { usagev: getValueByUnit(propertyTypes, usageTypesData, usaget, unit, usagev) },
      ];
    }
    this.setState({ progress: true });
    this.props.dispatch(creditCharge(params)).then(this.afterCharge);
  };

  afterCharge = (response) => {
    this.setState({ progress: false });
    if (response.status) {
      this.props.onClose();
    }
  }

  getAvailableRates = () => {
    const { allRates } = this.props;
    return allRates
      .map(rate => ({ value: rate.get('key'), label: rate.get('key') }))
      .toArray();
  }

  getSubscribersBySid = () => {
    const { subscribers } = this.props;
    const sidsArray = subscribers
      .map(subscriber => ({
        value: subscriber.get('sid'),
        label: subscriber.get('sid').toString()
      }))
      .toArray();
    return [{
      value: 'account_level',
      label: 'account level' },
      ...sidsArray
    ];
  }

  getSelectedRate = rateKey => getRateByKey(this.props.allRates, rateKey);

  getRateUnitLabel = (rateKey) => {
    const { propertyTypes, usageTypesData } = this.props;
    const selectedRate = this.getSelectedRate(rateKey);
    const usaget = getRateUsaget(selectedRate);
    const unit = getRateUnit(selectedRate, usaget);
    const unitLabel = getUnitLabel(propertyTypes, usageTypesData, usaget, unit);
    return unitLabel !== '' ? `(${unitLabel})` : '';
  }

  render() {
    const { rateBy, aprice, usagev, rate, validationErrors, helperMsg, progress, subscriber } = this.state;
    const availableRates = this.getAvailableRates();
    const subscribersForAid = this.getSubscribersBySid();

    return (
      <ModalWrapper
        show={true}
        progress={progress}
        labelProgress="Processing..."
        labelOk="Apply"
        title="Manual charge / refund"
        onOk={this.onCreditCharge}
        onCancel={this.props.onClose}
      >
        <Form horizontal>
          <FormGroup>
            <Col sm={2} componentClass={ControlLabel}>Rate By</Col>
            <Col sm={10}>
              <Col sm={3}>
                <Field
                  fieldType="radio"
                  name="rate-by"
                  id="rate-by-fix"
                  value="fix"
                  checked={rateBy === 'fix'}
                  onChange={this.onChangeCreditBy}
                  label="Fixed price"
                />
              </Col>
              <Col sm={3}>
                <Field
                  fieldType="radio"
                  name="rate-by"
                  id="rate-by-usagev"
                  value="usagev"
                  checked={rateBy === 'usagev'}
                  onChange={this.onChangeCreditBy}
                  label="Volume"
                />
              </Col>
            </Col>
          </FormGroup>

          <FormGroup validationState={validationErrors.get('subscriber', '').length > 0 ? 'error' : null}>
            <Col sm={2} componentClass={ControlLabel}>Subscriber</Col>
            <Col sm={10}>
              <Field
                fieldType="select"
                onChange={this.onChangeSubscriberValue}
                value={subscriber}
                options={subscribersForAid}
              />
              { validationErrors.get('subscriber', '').length > 0 ? <HelpBlock>{validationErrors.get('subscriber', '')}</HelpBlock> : ''}
            </Col>
          </FormGroup>

          <FormGroup validationState={validationErrors.get('aprice', '').length > 0 ? 'error' : null}>
            <Col sm={2} componentClass={ControlLabel}>Charge</Col>
            <Col sm={10}>
              <Field
                onChange={this.onChangeCreditApriceValue}
                value={aprice}
                fieldType="price"
                disabled={rateBy !== 'fix'}
              />
              <HelpBlock>
                { validationErrors.get('aprice', '').length > 0
                  ? validationErrors.get('aprice', '')
                  : helperMsg.get('aprice', '') }
              </HelpBlock>
            </Col>
          </FormGroup>

          <FormGroup validationState={validationErrors.get('usagev', '').length > 0 ? 'error' : null}>
            <Col sm={2} componentClass={ControlLabel}>{rateBy === 'usagev' ? `Volume ${this.getRateUnitLabel(rate)}` : 'Quantity'}</Col>
            <Col sm={10}>
              <Field
                onChange={this.onChangeCreditUsagevValue}
                value={usagev}
                fieldType="number"
              />
              { validationErrors.get('usagev', '').length > 0 ? <HelpBlock>{validationErrors.get('usagev', '')}</HelpBlock> : ''}
            </Col>
          </FormGroup>

          <FormGroup validationState={validationErrors.get('rate', '').length > 0 ? 'error' : null}>
            <Col sm={2} componentClass={ControlLabel}>Product</Col>
            <Col sm={10}>
              <Field
                fieldType="select"
                onChange={this.onChangeRateValue}
                value={rate}
                options={availableRates}
              />
              { validationErrors.get('rate', '').length > 0 ? <HelpBlock>{validationErrors.get('rate', '')}</HelpBlock> : ''}
            </Col>
          </FormGroup>
        </Form>
      </ModalWrapper>
    );
  }
}

const mapStateToProps = (state, props) => ({
  usageTypes: usageTypeSelector(state, props),
  currency: currencySelector(state, props),
  usageTypesData: usageTypesDataSelector(state, props),
  propertyTypes: propertyTypeSelector(state, props),
  allRates: state.list.get('all_retail_rates'),
  subscribers: state.list.get('subscribers_for_aid'),
});

export default connect(mapStateToProps)(Credit);
