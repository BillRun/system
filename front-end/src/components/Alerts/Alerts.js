import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Button } from 'react-bootstrap';
import { CSSTransition } from 'react-transition-group';
import { hideAlert, hideAllAlerts } from '@/actions/alertsActions';
import Alert from './Alert';

class Alerts extends Component {

  static defaultProps = {
    alerts: Immutable.List(),
    clearAllButtobLabel: 'Clear All',
    showClearAllButton: true,
    enterTimeout: 500,
    exitTimeout: 300,
  };

  static propTypes = {
    alerts: PropTypes.instanceOf(Immutable.List),
    clearAllButtobLabel: PropTypes.string,
    showClearAllButton: PropTypes.bool,
    hideAllAlerts: PropTypes.func.isRequired,
    hideAlert: PropTypes.func.isRequired,
    enterTimeout: PropTypes.number,
    exitTimeout: PropTypes.number,
  };

  handleAlertsDismiss = () => {
    this.props.hideAllAlerts();
  }

  handleAlertDismiss = (id) => {
    this.props.hideAlert(id);
  }

  renderClearAll = () => (
    <div style={{ textAlign: 'right' }}>
      <Button onClick={this.handleAlertsDismiss}>{this.props.clearAllButtobLabel}</Button>
    </div>
  );

  renderAlert = (alert) => {
    const { enterTimeout, exitTimeout } = this.props;
    return (
      <Alert
        alert={alert}
        handleAlertDismiss={this.handleAlertDismiss}
        key={alert.get('id')}
        transitioTime={(enterTimeout + exitTimeout + 100)}
      />
    );
  }

  render() {
    const { enterTimeout, exitTimeout, alerts: items, showClearAllButton } = this.props;
    const alerts = items.map(this.renderAlert);
    return (
      <CSSTransition
        timeout={{
         enter: enterTimeout,
         exit: exitTimeout,
        }}
        classNames="alerts"
      >
        <div className="alert-notifier-container">
          { alerts }
          { showClearAllButton && alerts.size > 1 && this.renderClearAll()}
        </div>
      </CSSTransition>
    );
  }
}

const mapDispatchToProps = (
  { hideAllAlerts, hideAlert }
);
const mapStateToProps = state => (
  { alerts: state.alerts }
);
export default connect(mapStateToProps, mapDispatchToProps)(Alerts);
