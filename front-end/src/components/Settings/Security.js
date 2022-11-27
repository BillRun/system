import React, { Component } from 'react';
import PropTypes from 'prop-types';
import moment from 'moment';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import List from '@/components/List';
import { getItemDateValue, getConfig } from '@/common/Util';
import { StateIcon, ConfirmModal, CreateButton } from '@/components/Elements';
import { getSettings, saveSharedSecret } from '@/actions/settingsActions';
import SecurityForm from './Security/SecurityForm';

class Security extends Component {

  static propTypes = {
    data: PropTypes.instanceOf(Immutable.List),
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    data: Immutable.List(),
  };
  state = {
    showConfirmRemove: false,
    itemToRemove: null,
    currentItem: null,
  }

  parseDate = (item, field) => {
    const date = getItemDateValue(item, field.id, false);
    return date ? date.format(getConfig('dateFormat', 'DD/MM/YYYY')) : '';
  }

  parserState = item => (
    <StateIcon
      from={getItemDateValue(item, 'from', moment(0)).toISOString()}
      to={getItemDateValue(item, 'to', moment(0)).toISOString()}
    />
  );

  onClickEdit = (item) => {
    this.setState({ currentItem: item });
  };

  onClickRemove = (item) => {
    this.setState({
      showConfirmRemove: true,
      itemToRemove: item,
    });
  }

  onClickRemoveClose = () => {
    this.setState({
      showConfirmRemove: false,
      itemToRemove: null,
    });
  }

  onCancel = () => {
    this.setState({ currentItem: null });
  }

  handleSave = (secret, action) => {
    this.props.dispatch(saveSharedSecret(secret, action)).then(this.afterSave);
  }

  afterSave = (response) => {
    if (response.status === 1) {
      this.setState({ currentItem: null });
      this.props.dispatch(getSettings(['shared_secret']));
    }
  }

  onClickRemoveOk = () => {
    const { itemToRemove } = this.state;
    const key = itemToRemove.get('key', '');
    this.props.dispatch(saveSharedSecret(key, 'remove')).then(this.afterRemove);
  }

  afterRemove = (response) => {
    if (response.status === 1) {
      this.setState({ showConfirmRemove: false, itemToRemove: null });
      this.props.dispatch(getSettings(['shared_secret']));
    }
  }

  onClickNew = () => {
    this.setState({ currentItem: Immutable.Map() });
  }

  renderList = () => {
    const { data } = this.props;
    const fields = this.getListFields();
    const actions = this.getListActions();
    return (
      <List items={data} fields={fields} edit={false} actions={actions} />
    );
  }

  getListFields = () => [
    { id: 'state', title: 'Status', parser: this.parserState, cssClass: 'state' },
    { id: 'name', title: 'Name' },
    { id: 'key', title: 'Secret Key' },
    { id: 'from', title: 'Creation Date', parser: this.parseDate, cssClass: 'long-date text-center' },
    { id: 'to', title: 'Expiration Date', parser: this.parseDate, cssClass: 'long-date text-center' },
  ]

  getListActions = () => [
    { type: 'edit', showIcon: true, helpText: 'Edit', onClick: this.onClickEdit },
    { type: 'remove', showIcon: true, helpText: 'Remove', onClick: this.onClickRemove },
  ]

  render() {
    const { showConfirmRemove, currentItem } = this.state;
    const removeConfirmMessage = 'Are you sure you want to remove this key ?';

    return (
      <div>
        { this.renderList() }
        <CreateButton onClick={this.onClickNew} label="Add New" />
        { currentItem !== null && (
          <SecurityForm item={currentItem} show={true} onSave={this.handleSave} onCancel={this.onCancel} />
        )}
        <ConfirmModal onOk={this.onClickRemoveOk} onCancel={this.onClickRemoveClose} show={showConfirmRemove} message={removeConfirmMessage} labelOk="Yes" />
      </div>
    );
  }
}

export default withRouter(connect()(Security));
