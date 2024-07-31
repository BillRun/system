import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { List, Map } from 'immutable'; // eslint-disable-line no-unused-vars
import { Col, FormGroup, Form, ControlLabel, HelpBlock, InputGroup } from 'react-bootstrap';
import getSymbolFromCurrency from 'currency-symbol-map';
import Field from '@/components/Field';
import { ModalWrapper } from '@/components/Elements'
import { currencySelector } from '@/selectors/settingsSelector';
import { offlinePaymentFieldsSelector } from '@/selectors/offlinePaymentSelectors';
import { payOffline } from '@/actions/paymentsActions';
import { getSettings } from '@/actions/settingsActions';
import moment from 'moment';

class OfflinePayment extends Component {
  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    onClose: PropTypes.func.isRequired,
    aid: PropTypes.number.isRequired,
    payerName: PropTypes.string,
    methods: PropTypes.instanceOf(Map),
    currency: PropTypes.string,
    debt: PropTypes.number,
    uf: PropTypes.instanceOf(List()),
  };

  static defaultProps = {
    payerName: '',
    methods: Map({
      cash: 'Cash',
      cheque: 'Cheque',
      credit: 'Credit Card',
    }),
    currency: '',
    debt: null,
    uf: List(),
  };

  state = {
    validationErrors: Map({
      method: 'required',
      monetaryValue: 'required',
    }),
    method: '',
    monetaryValue: '',
    urt: moment().set({hour:0,minute:0,second:0,millisecond:0}),
    progress: false,
    fc: true,
    note: '',
    additionalFields: Map()
  }

  componentDidMount() {
    this.initDefaultValues();
    this.props.dispatch(getSettings(['payments']));
  }

  initDefaultValues = () => {
    const { debt } = this.props;
    if (debt !== null) {
      this.onChangeValueById({ target: { value: Math.abs(debt), id: 'monetaryValue'} });
    }
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

  onChangeMethodValue = (value) => {
    this.onChangeValue('method', value);
  }

  onChangeUfValue = (e) => {
    const { id, value } = e.target;
    const { additionalFields } = this.state;
    this.setState({additionalFields: additionalFields.set(id, value)});
  }

  onChangeValueById = (e) => {
    const { id, value } = e.target;
    this.onChangeValue(id, value);   
  }

  onChangePaymentType = (e) => {
    const { value } = e.target;
    if(value === 'tc'){
      this.onChangeValue('fc', false);
    }
    if(value === 'fc'){
      this.onChangeValue('fc', true);
    }
  }

  onChangeUrtValue = (value) => {
    this.onChangeValue('urt', value);
  }

  getAvailableMethods = () => {
    const { methods } = this.props;
    return methods
      .map((methodName, method) => ({ value: method, label: methodName }))
      .toList()
      .toArray();
  }

  onPay = () => {
    const { aid, payerName } = this.props;
    const { method, monetaryValue, chequeNum, fc, additionalFields, note, urt } = this.state;
    this.setState(() => ({ progress: true }));
    const dir = !fc ? 'tc' : 'fc';
    this.props.dispatch(payOffline(method, aid, monetaryValue, payerName, chequeNum, dir, additionalFields, note, urt))
      .then(this.afterPay);
  }

  afterPay = (response) => {
    this.setState(() => ({ progress: false }));
    if (response.status) {
      this.props.onClose();
    }
  }

 renderUserFields = () => {
    const { additionalFields } = this.state;
    const { uf } = this.props;
    return (
      <div>
    {uf.map((userField, idx) => {
      return (<FormGroup key={idx}>
        <Col sm={2} componentClass={ControlLabel}>{userField.get('title', '')}</Col>
          <Col sm={8}>
            <Field
              id={userField.get('field_name', '')}
              onChange={this.onChangeUfValue}
              value={additionalFields.get(userField.get('field_name', ''), '')}
            />
        </Col>
    </FormGroup>)
    })}
    </div>)
  }
  render() {
    const { currency } = this.props;
    const { validationErrors, method, monetaryValue, chequeNum, progress, fc, note, urt } = this.state;
    const availableMethods = this.getAvailableMethods();
    return (
      <ModalWrapper
        show={true}
        labelOk="Pay"
        title="Offline Payment"
        onOk={this.onPay}
        onCancel={this.props.onClose}
        progress={progress}
      >
        <Form horizontal>

          <FormGroup validationState={validationErrors.get('method', '').length > 0 ? 'error' : null}>
            <Col sm={2} componentClass={ControlLabel}>Method</Col>
            <Col sm={8}>
              <Field
                fieldType="select"
                onChange={this.onChangeMethodValue}
                value={method}
                options={availableMethods}
              />
              { validationErrors.get('method', '').length > 0 ? <HelpBlock>{validationErrors.get('method', '')}</HelpBlock> : ''}
            </Col>
          </FormGroup>

          <FormGroup validationState={validationErrors.get('monetaryValue', '').length > 0 ? 'error' : null}>
            <Col sm={2} componentClass={ControlLabel}>Monetary Value</Col>
            <Col sm={8}>
              <InputGroup>
                <Field
                  id="monetaryValue"
                  onChange={this.onChangeValueById}
                  value={monetaryValue}
                  fieldType="number"
                />
                <InputGroup.Addon>
                  {getSymbolFromCurrency(currency)}
                </InputGroup.Addon>
              </InputGroup>
              { validationErrors.get('monetaryValue', '').length > 0 ? <HelpBlock>{validationErrors.get('monetaryValue', '')}</HelpBlock> : ''}
            </Col>
          </FormGroup>

          {method === 'cheque' &&
            (<FormGroup>
              <Col sm={2} componentClass={ControlLabel}>Cheque Number</Col>
              <Col sm={8}>
                <Field
                  id="chequeNum"
                  onChange={this.onChangeValueById}
                  value={chequeNum}
                  fieldType="number"
                />
              </Col>
            </FormGroup>)
          }

          <FormGroup>
              <Col sm={2} componentClass={ControlLabel}>Payment direction</Col>
              <Col sm={8}>
              <span className="inline mr10">
              <Field
                  fieldType="radio"
                  onChange={this.onChangePaymentType}
                  name="payment_type"
                  value="fc"
                  label={
                    <span>
                      From Customer
                    </span>
                  }
                  checked={fc}
                />
                 </span>
              <span className="inline">
              <Field
                  fieldType="radio"
                  onChange={this.onChangePaymentType}
                  name="payment_type"
                  value="tc"
                  label={
                    <span>
                     To Customer
                    </span>
                  }
                  checked={!fc}
                />
              </span>
              </Col>
            </FormGroup>
            <FormGroup>
              <Col sm={2} componentClass={ControlLabel}>Note</Col>
              <Col sm={8}>
                <Field
                  id="note"
                  onChange={this.onChangeValueById}
                  value={note}
                />
              </Col>
            </FormGroup>
	              <FormGroup>
              <Col sm={2} componentClass={ControlLabel}>Time</Col>
                <Col sm={8}>
                  <Field
                    id="urt"
                    onChange={this.onChangeUrtValue}
                    value={urt}
                    fieldType="datetime"
                  />
              </Col>
          </FormGroup>
	          {this.renderUserFields()}
        </Form>
      </ModalWrapper>
    );
  }
}

const mapStateToProps = (state, props) => ({
  currency: currencySelector(state, props),
  uf: offlinePaymentFieldsSelector(state, props) || undefined,
});

export default connect(mapStateToProps)(OfflinePayment);
