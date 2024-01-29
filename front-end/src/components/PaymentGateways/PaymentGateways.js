import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import PaymentGateway from './PaymentGateway';
import { apiBillRun } from '../../common/Api';
import { savePaymentGatewayQuery, disablePaymentGatewayQuery } from '../../common/ApiQueries';

import { getSettings, addPaymentGateway, removePaymentGateway, updatePaymentGateway } from '@/actions/settingsActions';
import { showSuccess, showDanger } from '@/actions/alertsActions';
import { getList } from '@/actions/listActions';

class PaymentGateways extends Component {

  static defaultProps = {
    payment_gateways: Immutable.List(),
    supported_gateways: Immutable.List(),
  };

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    payment_gateways: PropTypes.instanceOf(Immutable.List),
    supported_gateways: PropTypes.instanceOf(Immutable.List),
  };

  componentDidMount() {
    this.props.dispatch(getSettings('payment_gateways'));
    const paymentGatewaysQuery = {
      api: 'paymentgateways',
      action: 'list',
    };
    this.props.dispatch(getList('supported_gateways', paymentGatewaysQuery));
  }

  onSaveGatewayParams = (gateway, enabled) => {
    apiBillRun(savePaymentGatewayQuery(gateway)).then(
      (success) => { // eslint-disable-line no-unused-vars
        this.props.dispatch(showSuccess('Payment gateway enabled!'));
        if (!enabled) {
          this.props.dispatch(addPaymentGateway(gateway));
        } else {
          this.props.dispatch(updatePaymentGateway(gateway));
        }
      },
      (failure) => { // eslint-disable-line no-unused-vars
        this.props.dispatch(showDanger('Error saving payment gateway'));
      }
    ).catch(
      (error) => { // eslint-disable-line no-unused-vars
        this.props.dispatch(showDanger('Network error - please try again'));
      }
    );
  };

  onDisableGateway = (name) => {
    apiBillRun(disablePaymentGatewayQuery(name)).then(
      (success) => { // eslint-disable-line no-unused-vars
        this.props.dispatch(showSuccess('Payment gateway disabled!'));
        this.props.dispatch(removePaymentGateway(name));
      },
      (failure) => {
        console.log('failed!', failure);
        this.props.dispatch(showDanger('Error saving payment gateway'));
      }
    ).catch(
      (error) => {
        console.log(error);
        this.props.dispatch(showDanger('Network error - please try again'));
      }
    );
  };

  renderPaymentGateways = () => {
    const { supported_gateways, payment_gateways } = this.props;
    return supported_gateways.map((gateway, key) => {
      const enabled = payment_gateways.find(pg => pg.get('name') === gateway.get('name'));
      return (
        <div className="col-lg-4 col-md-4" key={key}>
          <PaymentGateway
            enabled={enabled}
            settings={gateway}
            onDisable={this.onDisableGateway}
            onSaveParams={this.onSaveGatewayParams}
          />
        </div>
      );
    });
  }

  render() {
    return (
      <div className="panel panel-default">
        <div className="panel-heading">
          Available payment gateways
        </div>
        <div className="panel-body">
          <form className="form-horizontal">
            { this.renderPaymentGateways() }
          </form>
        </div>
      </div>
    );
  }
}


const mapStateToProps = state => ({
  payment_gateways: state.settings.get('payment_gateways') || undefined,
  supported_gateways: state.list.get('supported_gateways') || undefined,
});

export default connect(mapStateToProps)(PaymentGateways);
