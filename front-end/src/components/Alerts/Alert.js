import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { Alert as BootstrapAlert } from 'react-bootstrap';
import { SUCCESS, DANGER, INFO, WARNING } from '@/actions/alertsActions';


export default class Alert extends Component {

  static defaultProps = {
    transitioTime: 0,
  };

  static propTypes = {
    alert: PropTypes.shape({
      id: PropTypes.oneOfType([
        PropTypes.string,
        PropTypes.number,
      ]).isRequired,
      message: PropTypes.string.isRequired,
      type: PropTypes.string.isRequired,
      timeout: PropTypes.number,
    }).isRequired,
    handleAlertDismiss: PropTypes.func.isRequired,
    transitioTime: PropTypes.number,
  }

  constructor(props) {
    super(props);
    this.autoHideTimer = null;
  }

  componentDidMount() {
    const { alert: { timeout }, transitioTime } = this.props;
    if (timeout > 0) {
      clearTimeout(this.autoHideTimer);
      this.autoHideTimer = setTimeout(this.handleAlertDismiss, timeout + transitioTime);
    }
  }

  componentWillUnmount() {
    clearTimeout(this.autoHideTimer);
  }

  handleAlertDismiss = () => {
    const { alert: { id } } = this.props;
    this.props.handleAlertDismiss(id);
  }

  render() {
    const { alert: { type, message } } = this.props;
    const alertType = [SUCCESS, DANGER, INFO, WARNING].includes(type) ? type : INFO;
    return (
      <BootstrapAlert bsStyle={alertType} onDismiss={this.handleAlertDismiss}>
        <div>{message}</div>
      </BootstrapAlert>
    );
  }
}
