import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import EmailTemplate from './EmailTemplate';
import {
  accountFieldNamesSelector,
} from '@/selectors/settingsSelector';
import {
  getSettings,
} from '@/actions/settingsActions';

class InvoiceReady extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    accountFields: PropTypes.instanceOf(Immutable.List),
  };

  static defaultProps = {
    accountFields: Immutable.List(),
  };

  componentDidMount() {
    this.props.dispatch(getSettings(['subscribers']));
  }

  getFields = () => {
    const { accountFields } = this.props;
    return accountFields.map(fieldName => (`customer_${fieldName}`));
  }

  render() {
    return (
      <EmailTemplate
        name="invoice_ready"
        fields={this.getFields()}
      />
    );
  }
}

const mapStateToProps = (state, props) => ({
  accountFields: accountFieldNamesSelector(state, props),
});

export default connect(mapStateToProps)(InvoiceReady);
