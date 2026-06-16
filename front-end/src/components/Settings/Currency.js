import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';
import ConfirmModal from '@/components/Elements/ConfirmModal';

// BRCD-2840: warning shown when the operator turns multi-currency on.
const ENABLE_MC_WARNING = 'You’ve turned on multi-currency support. ' +
  'We will fetch currencies conversion rates automatically immediately and you can override ' +
  'it in system level or entity level (product, service, plan, etc). ' +
  'Some features are not available when multi currency is on, such as monetary discounts or ' +
  'monetary events and such settings will be removed.';

class Currency extends Component {

  static propTypes = {
    onChange: PropTypes.func.isRequired,
    data: PropTypes.string,
    currencies: PropTypes.arrayOf(PropTypes.object),
    additionalCurrencies: PropTypes.instanceOf(Immutable.List),
  };

  static defaultProps = {
    data: '',
    currencies: [],
    additionalCurrencies: Immutable.List(),
  };

  state = {
    showEnableWarning: false,
    pendingCurrencies: null,
  };

  onChangeCurrency = (value) => {
    this.props.onChange('pricing', 'currency', value);
  };

  // comma-separated currency codes currently configured (multi-select value format)
  getSelectedCodes = () => this.props.additionalCurrencies
    .map(currency => currency.get('currency', ''))
    .filter(code => code !== '')
    .join(',');

  // options for the additional-currencies multi-select (exclude the default currency)
  getAdditionalOptions = () => {
    const { currencies, data } = this.props;
    return currencies.filter(option => option.value !== data);
  };

  // reconcile the selected codes into the additional_currencies array, preserving the
  // auto_sync/multiplier of currencies that stay and defaulting newly added ones.
  buildCurrenciesList = (codesString) => {
    const { additionalCurrencies } = this.props;
    const codes = codesString === '' ? [] : codesString.split(',');
    return Immutable.List(codes.map((code) => {
      const existing = additionalCurrencies.find(currency => currency.get('currency') === code);
      return existing || Immutable.Map({ currency: code, auto_sync: true, multiplier: 1 });
    }));
  };

  applyCurrencies = (list) => {
    this.props.onChange('pricing', 'additional_currencies', list);
  };

  onChangeAdditionalCurrencies = (codesString) => {
    const newList = this.buildCurrenciesList(codesString);
    // Enabling multi-currency (was empty, now has entries) requires confirmation.
    if (this.props.additionalCurrencies.isEmpty() && !newList.isEmpty()) {
      this.setState({ showEnableWarning: true, pendingCurrencies: newList });
      return;
    }
    this.applyCurrencies(newList);
  };

  onConfirmEnable = () => {
    const { pendingCurrencies } = this.state;
    if (pendingCurrencies !== null) {
      this.applyCurrencies(pendingCurrencies);
    }
    this.setState({ showEnableWarning: false, pendingCurrencies: null });
  };

  onCancelEnable = () => {
    this.setState({ showEnableWarning: false, pendingCurrencies: null });
  };

  onChangeAutoSync = (code, e) => {
    const { additionalCurrencies } = this.props;
    const { value } = e.target;
    const index = additionalCurrencies.findIndex(currency => currency.get('currency') === code);
    if (index === -1) {
      return;
    }
    this.applyCurrencies(additionalCurrencies.setIn([index, 'auto_sync'], value));
  };

  onChangeMultiplier = (code, e) => {
    const { additionalCurrencies } = this.props;
    const { value } = e.target;
    const index = additionalCurrencies.findIndex(currency => currency.get('currency') === code);
    if (index === -1) {
      return;
    }
    this.applyCurrencies(additionalCurrencies.setIn([index, 'multiplier'], value));
  };

  renderCurrencyRow = (currency) => {
    const code = currency.get('currency', '');
    const autoSync = currency.get('auto_sync', true);
    const multiplier = currency.get('multiplier', 1);
    return (
      <FormGroup key={code}>
        <Col componentClass={ControlLabel} md={2}>{code}</Col>
        <Col sm={3}>
          <Field
            fieldType="checkbox"
            value={autoSync}
            onChange={e => this.onChangeAutoSync(code, e)}
            label="Auto sync"
          />
        </Col>
        <Col sm={3}>
          <Field
            fieldType="number"
            value={multiplier}
            onChange={e => this.onChangeMultiplier(code, e)}
            disabled={!autoSync}
            preffix="x"
          />
        </Col>
      </FormGroup>
    );
  };

  render() {
    const { data, currencies, additionalCurrencies } = this.props;
    const { showEnableWarning } = this.state;
    return (
      <div className="CurrencyTax">
        <Form horizontal>
          <FormGroup controlId="currency" key="currency">
            <Col componentClass={ControlLabel} md={2}>
              Default Currency
            </Col>
            <Col sm={6}>
              <Field
                fieldType="select"
                options={currencies}
                value={data}
                onChange={this.onChangeCurrency}
              />
            </Col>
          </FormGroup>

          <FormGroup controlId="additional_currencies" key="additional_currencies">
            <Col componentClass={ControlLabel} md={2}>
              Additional Currencies
            </Col>
            <Col sm={6}>
              <Field
                fieldType="select"
                multi
                options={this.getAdditionalOptions()}
                value={this.getSelectedCodes()}
                onChange={this.onChangeAdditionalCurrencies}
              />
            </Col>
          </FormGroup>

          {additionalCurrencies.map(this.renderCurrencyRow).toArray()}
        </Form>

        <ConfirmModal
          show={showEnableWarning}
          onOk={this.onConfirmEnable}
          onCancel={this.onCancelEnable}
          labelOk="Enable"
          labelCancel="Cancel"
          message="Enable multi-currency support?"
        >
          {ENABLE_MC_WARNING}
        </ConfirmModal>
      </div>
    );
  }
}

export default Currency;
