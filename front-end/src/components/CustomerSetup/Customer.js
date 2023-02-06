import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import moment from 'moment';
import { Form, FormGroup, Col, Button, ControlLabel, Row } from 'react-bootstrap';
import getSymbolFromCurrency from 'currency-symbol-map';
import classNames from 'classnames';
import OfflinePayment from '../Payments/OfflinePayment';
import CyclesSelector from '../Cycle/CyclesSelector';
import { EntityFields } from '../Entity';
import Credit from '../Credit/Credit';
import { ConfirmModal, Actions } from '@/components/Elements';
import { currencySelector, paymentGatewaysSelector } from '@/selectors/settingsSelector';
import { getSettings } from '@/actions/settingsActions';
import { rebalanceAccount, getCollectionDebt } from '@/actions/customerActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { buildRequestUrl } from '@/common/Api';
import { getExpectedInvoiceQuery } from '@/common/ApiQueries';
import { getConfig } from '@/common/Util';


class Customer extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    customer: PropTypes.instanceOf(Immutable.Map),
    supportedGateways: PropTypes.instanceOf(Immutable.List),
    onChangePaymentGateway: PropTypes.func.isRequired,
    onChange: PropTypes.func.isRequired,
    onRemoveField: PropTypes.func.isRequired,
    action: PropTypes.string,
    currency: PropTypes.string,
    payment_gateways: PropTypes.object.isRequired,
  };

  static defaultProps = {
    action: 'create',
    currency: '',
    customer: Immutable.Map(),
    supportedGateways: Immutable.List(),
    payment_gateways: undefined,
  };

  state = {
    showRebalanceConfirmation: false,
    showOfflinePayement: false,
    debt: null,
    selectedCyclesNames: '',
    expectedCyclesNames: '',
    showCreditCharge: false,
  };

  componentDidMount() {
    const { action } = this.props;
    if (action !== 'create') {
      this.initDebt();
    }
    this.props.dispatch(getSettings('payment_gateways'));
  }

  initDebt = () => {
    const { customer } = this.props;
    const aid = customer.get('aid', null);
    this.props.dispatch(getCollectionDebt(aid))
      .then((response) => {
        if (response.status && response.data && response.data.balance) {
          this.setState({ debt: response.data.balance.total });
        }
      });
  }

  onRemovePaymentGateway = () => {
    this.props.onChange(['payment_gateway', 'active'], Immutable.Map());
  }

  renderPaymentGatewayLabel = () => {
    const { customer, supportedGateways } = this.props;
    let customerPgName = customer.getIn(['payment_gateway', 'active', 'instance_name'], '');
    if (!customerPgName) {
      customerPgName = customer.getIn(['payment_gateway', 'active', 'name'], '');
    }
    const pg = supportedGateways.filter(item => customerPgName === item.get('name'));
    return (!pg.isEmpty() && pg.get(0).get('image_url', ''))
      ? <span>
          <img src={`${getConfig(['env','serverApiUrl'], '')}/${pg.get(0).get('image_url', '')}`} height="30" alt={pg.get(0).get('title', customerPgName)} /> {pg.get(0).get('title', customerPgName)}
        </span>
      : customerPgName;
  }

  getPaymentGatewaysActions = () => {
    const { customer, payment_gateways } = this.props;
    const hasPaymentGateway = !(customer.getIn(['payment_gateway', 'active'], Immutable.Map()).isEmpty());
    return ([{
      type: 'add',
      label: 'Add ',
      onClick: this.props.onChangePaymentGateway,
      show: !hasPaymentGateway,
      actionStyle: 'primary',
      actionSize: 'xsmall',
      enable: !payment_gateways.isEmpty(),
    }, {
      type: 'edit',
      label: 'Change',
      onClick: this.props.onChangePaymentGateway,
      actionStyle: 'primary',
      actionSize: 'xsmall',
      show: hasPaymentGateway,
    }, {
      type: 'remove',
      label: 'Remove',
      onClick: this.onClickRemovePaymentGateway,
      actionStyle: 'danger',
      actionSize: 'xsmall',
      show: hasPaymentGateway && false, // button hardcoded hidden - remove '&& false' to unhide from ui
    }]);
  }

  onClickRemovePaymentGateway = (customer) => {
    const paymentGatewayName = customer.getIn(['payment_gateway', 'active', 'name'], 'payment gateway');
    const confirm = {
      message: `Are you sure you want to remove ${paymentGatewayName}`,
      onOk: this.onRemovePaymentGateway,
      type: 'delete',
      labelOk: 'Remove',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  renderChangePaymentGateway = () => {
    const { customer } = this.props;
    const hasPaymentGateway = !(customer.getIn(['payment_gateway', 'active'], Immutable.Map()).isEmpty());
    const label = hasPaymentGateway ? this.renderPaymentGatewayLabel() : 'None';
    return (
      <FormGroup controlId="payment_gateway">
        <Col componentClass={ControlLabel} sm={3} lg={2}>
          Payment Gateway
        </Col>
        <Col sm={8} lg={9}>
          {label}
          <div className="inline ml10">
            <Actions actions={this.getPaymentGatewaysActions()} data={customer} />
          </div>
        </Col>
      </FormGroup>
    );
  }

  renderDebt = () => {
    const { currency } = this.props;
    const { debt } = this.state;
    const debtClass = classNames('non-editable-field', {
      'danger-red': debt > 0,
    });
    return debt !== null && (
      <FormGroup controlId="total_debt">
        <Col componentClass={ControlLabel} sm={3} lg={2}>
          Total Debt
        </Col>
        <Col sm={8} lg={9}>
          <div className={debtClass}>{debt}{getSymbolFromCurrency(currency)}</div>
        </Col>
      </FormGroup>
    );
  }

  renderInCollection = () => {
    const { customer } = this.props;
    const dateFormat = getConfig('dateFormat', 'DD/MM/YYYY')
    if (customer.get('in_collection', false) === true || customer.get('in_collection', 0) === 1) {
      const fromDate = moment(customer.get('in_collection_from', '')).format(dateFormat);
      return (<p className="danger-red">In collection from {fromDate}</p>);
    }
    return null;
  }

  onClickRebalance = () => {
    this.setState({ showRebalanceConfirmation: true });
  }

  onRebalanceConfirmationClose = () => {
    this.setState({ showRebalanceConfirmation: false, selectedCyclesNames: '' });
  }

  onRebalanceConfirmationOk = () => {
    const { customer } = this.props;
    const { selectedCyclesNames } = this.state;
    this.props.dispatch(rebalanceAccount(customer.get('aid'), selectedCyclesNames));
    this.onRebalanceConfirmationClose();
  }

  onClickOfflinePayment = () => {
    this.setState({ showOfflinePayement: true });
  }

  onCloseOfflinePayment = () => {
    this.setState({ showOfflinePayement: false });
    this.initDebt();
  }

  onChangeSelectedCycle = (selectedCyclesNames) => {
    this.setState({ selectedCyclesNames });
  }

  onChangeExpectedCycle = (expectedCyclesNames) => {
    this.setState({ expectedCyclesNames });
  }

  onClickExpectedInvoice = () => {
    const { customer } = this.props;
    const { expectedCyclesNames } = this.state;
    const query = getExpectedInvoiceQuery(customer.get('aid'), expectedCyclesNames);
    window.open(buildRequestUrl(query));
  }


  renderRebalanceButton = () => {
    const { customer } = this.props;
    const { showRebalanceConfirmation, selectedCyclesNames } = this.state;
    const confirmationTitle = `Are you sure you want to rebalance account ${customer.get('aid')}?`;
    return (
      <div>
        <Button bsSize="xsmall" className="btn-primary" onClick={this.onClickRebalance}>Rebalance</Button>
        <ConfirmModal onOk={this.onRebalanceConfirmationOk} onCancel={this.onRebalanceConfirmationClose} show={showRebalanceConfirmation} message={confirmationTitle} labelOk="Yes">
          <FormGroup>
            <Row>
              <Col sm={12} lg={12}>
                Rebalance operation will send all customer billing lines
                for recalculation price and bundles options.
                It can take few hours to finish the recalculations.
                Meanwhile lines pricing properties will be empty.
                <br />
                This operation is useful when an update is required with reference data
                (plans, products, services, etc) that raised non-desired pricing results.
                After fixing the reference data, the operation will recalculate the billing lines.
                <br /><br />
              </Col>
            </Row>
            <Row>
              <Col sm={3} lg={2} componentClass={ControlLabel} className={'non-editable-field'}>Select cycle/s</Col>
              <Col sm={9} lg={8}>
                <CyclesSelector
                  onChange={this.onChangeSelectedCycle}
                  statusesToDisplay={Immutable.List(['current', 'to_run'])}
                  selectedCycles={selectedCyclesNames}
                  multi={true}
                />
              </Col>
            </Row>
          </FormGroup>
        </ConfirmModal>
      </div>
    );
  }

  renderOfflinePaymentsButton = () => {
    const { customer } = this.props;
    const { showOfflinePayement, debt } = this.state;
    const payerName = `${customer.get('firstname', '')} ${customer.get('lastname', '')}`;
    return (
      <FormGroup>
        <Col sm={8} lg={9} smOffset={3} lgOffset={2}>
          <Button bsSize="xsmall" className="btn-primary" style={{ marginTop: 12 }} onClick={this.onClickOfflinePayment}>Offline Payment</Button>
          { showOfflinePayement && (
            <OfflinePayment
              aid={customer.get('aid')}
              payerName={payerName}
              debt={debt}
              onClose={this.onCloseOfflinePayment}
            />
          )}
        </Col>
      </FormGroup>

    );
  }

  renderExpectedInvoiceButton = () => {
    const { expectedCyclesNames } = this.state;
    const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]');
    return (
      <span className="inline">
        , Or generate expected invoices for :
        <span className="inline" style={{ verticalAlign: 'middle', minWidth: 290, marginLeft: 5, marginRight: 5 }} >
          <CyclesSelector
            className="inline"
            onChange={this.onChangeExpectedCycle}
            statusesToDisplay={Immutable.List(['current', 'to_run', 'future'])}
            selectedCycles={expectedCyclesNames}
            multi={false}
            from={moment().subtract(6, 'month').format(apiDateTimeFormat)}
            to={moment().add(6, 'month').format(apiDateTimeFormat)}
            newestFirst={false}
          />
        </span>
        <Button bsSize="small" className="btn-primary inline" disabled={!expectedCyclesNames} onClick={this.onClickExpectedInvoice}>Generate expected invoice</Button>
      </span>
    );
  }

  renderCreditCharge = () => {
    const { customer } = this.props;
    const { showCreditCharge } = this.state;
    const aid = customer.get('aid', null);
    return (
      <div>
        <Button bsSize="xsmall" className="btn-primary" onClick={this.onShowCreditCharge}>Manual charge / refund</Button>
        { showCreditCharge && (<Credit aid={aid} onClose={this.onCloseCreditCharge} />) }
      </div>
    );
  }

  onShowCreditCharge = () => {
    this.setState({ showCreditCharge: true });
  }

  onCloseCreditCharge = () => {
    this.setState({ showCreditCharge: false });
  }


  render() {
    const { customer, action } = this.props;
    // in update mode wait for item before render edit screen
    if (action !== 'create' && typeof customer.getIn(['_id', '$id']) === 'undefined') {
      return (<div> <p>Loading...</p> </div>);
    }

    return (
      <div className="Customer">
        <Form horizontal>
          <EntityFields
            entityName={['subscribers', 'account']}
            entity={customer}
            onChangeField={this.props.onChange}
            onRemoveField={this.props.onRemoveField}
          />
          { (action !== 'create') && this.renderChangePaymentGateway() }
          { (action !== 'create') && this.renderOfflinePaymentsButton() }
          { (action !== 'create') && this.renderDebt() }
        </Form>
        {(action !== 'create') &&
          <div>
            <hr />
            { this.renderInCollection() }
            <div>See Customer <Link to={`/usage?base={"aid": ${customer.get('aid')}}`}>Usage</Link></div>
            <div>See Customer <Link to={`/invoices?base={"aid": ${customer.get('aid')}}`}>Invoices</Link> { this.renderExpectedInvoiceButton() }</div>
            { this.renderRebalanceButton() }
            { <hr /> }
            { this.renderCreditCharge() }
          </div>
        }
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  currency: currencySelector(state, props),
  payment_gateways: paymentGatewaysSelector(state, props),
});

export default connect(mapStateToProps)(Customer);
