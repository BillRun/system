import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import { Form, FormGroup, ControlLabel, Col } from 'react-bootstrap';
import Field from '@/components/Field';
import {
  currencySelector,
  additionalCurrenciesSelector,
  manualCurrenciesSelector,
} from '@/selectors/settingsSelector';

class ExchangeRateDetails extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map).isRequired,
    mode: PropTypes.string.isRequired,
    onFieldUpdate: PropTypes.func.isRequired,
    defaultCurrency: PropTypes.string,
    additionalCurrencies: PropTypes.instanceOf(Immutable.List),
    manualCurrencies: PropTypes.instanceOf(Immutable.List),
  }

  static defaultProps = {
    defaultCurrency: '',
    additionalCurrencies: Immutable.List(),
    manualCurrencies: Immutable.List(),
  }

  componentDidMount() {
    // BRCD-2852: the base currency is always the system default currency.
    const { item, mode, defaultCurrency } = this.props;
    if (mode === 'create' && !item.get('base_currency') && defaultCurrency) {
      this.props.onFieldUpdate(['base_currency'], defaultCurrency);
    }
  }

  onChangeTargetCurrency = (value) => {
    this.props.onFieldUpdate(['target_currency'], value);
  }

  onChangeRate = (e) => {
    const { value } = e.target;
    this.props.onFieldUpdate(['rate'], value);
  }

  getCurrencyOptions = () => {
    const { additionalCurrencies } = this.props;
    return additionalCurrencies
      .map(currency => ({ value: currency, label: currency }))
      .toArray();
  }

  // The rate is editable only when the currency's exchange rate is manually defined
  // (auto_sync off); auto-synced currencies are read-only (also enforced by the backend).
  isRateEditable = () => {
    const { item, mode, manualCurrencies } = this.props;
    if (mode === 'view') {
      return false;
    }
    if (mode === 'create') {
      return true;
    }
    return manualCurrencies.includes(item.get('target_currency', ''));
  }

  render() {
    const { item, mode, defaultCurrency } = this.props;
    const isCreate = ['clone', 'create'].includes(mode);
    return (
      <Form horizontal>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>Base Currency</Col>
          <Col sm={8} lg={9}>
            <Field value={item.get('base_currency', defaultCurrency)} disabled={true} />
          </Col>
        </FormGroup>

        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            Target Currency<span className="danger-red"> *</span>
          </Col>
          <Col sm={8} lg={9}>
            { isCreate
              ? (
                <Field
                  fieldType="select"
                  options={this.getCurrencyOptions()}
                  value={item.get('target_currency', '')}
                  onChange={this.onChangeTargetCurrency}
                />
              )
              : <Field value={item.get('target_currency', '')} disabled={true} />
            }
          </Col>
        </FormGroup>

        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            Rate<span className="danger-red"> *</span>
          </Col>
          <Col sm={8} lg={9}>
            <Field
              fieldType="number"
              value={item.get('rate', '')}
              onChange={this.onChangeRate}
              editable={this.isRateEditable()}
            />
          </Col>
        </FormGroup>
      </Form>
    );
  }
}

const mapStateToProps = (state, props) => ({
  defaultCurrency: currencySelector(state, props),
  additionalCurrencies: additionalCurrenciesSelector(state, props).map(currency => currency.get('currency')),
  manualCurrencies: manualCurrenciesSelector(state, props),
});

export default connect(mapStateToProps)(ExchangeRateDetails);
