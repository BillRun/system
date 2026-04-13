import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import moment from 'moment';
import EntityList from '../EntityList';
import { showSuccess } from '@/actions/alertsActions';
import { deleteAutoRenew } from '@/actions/autoRenewActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { getFieldName } from '@/common/Util';
import { lastRenewParser } from './AutoRenewUtil';


class AutoRenewsList extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {}

  state = {
    itemToDelete: null,
    refreshString: '',
  }

  getFilterFields = () => ([
    { id: 'aid', placeholder: getFieldName('aid', 'autorenew') },
    { id: 'sid', placeholder: getFieldName('sid', 'autorenew') },
    { id: 'bucket_group', placeholder: getFieldName('bucket_group', 'autorenew') },
  ]);

  getTableFields = () => ([
    { id: 'aid', title: getFieldName('aid', 'autorenew'), sort: true },
    { id: 'sid', title: getFieldName('sid', 'autorenew'), sort: true },
    { id: 'bucket_group', title: getFieldName('bucket_group', 'autorenew'), sort: true },
    { id: 'cycles', title: getFieldName('cycles', 'autorenew'), sort: true },
    { id: 'cycles_remaining', title: getFieldName('cycles_remaining', 'autorenew'), sort: true },
    { id: 'next_renew', title: getFieldName('next_renew', 'autorenew'), sort: true, type: 'datetime' },
    { id: 'last_renew', title: getFieldName('last_renew', 'autorenew'), sort: true, type: 'datetime', parser: lastRenewParser },
    { id: 'interval', title: getFieldName('interval', 'autorenew'), sort: true },
  ]);

  getProjectFields = () => ({
    aid: 1,
    sid: 1,
    bucket_group: 1,
    cycles: 1,
    cycles_remaining: 1,
    next_renew: 1,
    last_renew: 1,
    interval: 1,
  });

  onAskDelete = (item) => {
    const confirm = {
      message: 'Are you sure you want to delete this recurring charge ?',
      onOk: this.onDeleteOk,
      labelOk: 'Delete',
      type: 'delete',
      onCancel: this.onDeleteClose,
    };
    this.props.dispatch(showConfirmModal(confirm));
    this.setState({
      itemToDelete: item,
    });
  }

  onDeleteClose = () => {
    this.setState({ itemToDelete: null });
  }

  onDeleteOk = () => {
    const { itemToDelete } = this.state;
    this.props.dispatch(deleteAutoRenew(itemToDelete)).then(this.afterDelete);
  }

  afterDelete = (response) => {
    this.onDeleteClose();
    if (response.status) {
      this.props.dispatch(showSuccess('Recurring charge removed successfully'));
      this.setState({
        refreshString: moment().format(), //refetch list items after import
      });
    }
  }

  getActions = () => ([
    { type: 'edit', onClickColumn: null },
    { type: 'remove', showIcon: true, onClick: this.onAskDelete },
  ]);

  render() {
    const { refreshString } = this.state;
    const filterFields = this.getFilterFields();
    const tableFields = this.getTableFields();
    const actions = this.getActions();
    const projectFields = this.getProjectFields();
    return (
      <EntityList
        collection="autorenew"
        api="get"
        itemType="auto_renew"
        itemsType="auto_renews"
        filterFields={filterFields}
        tableFields={tableFields}
        projectFields={projectFields}
        actions={actions}
        refreshString={refreshString}
      />
    );
  }

}

export default connect()(AutoRenewsList);
