import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import EntityList from '../EntityList';
import { StateIcon } from '@/components/Elements';
import {
  deleteExportGenerator,
  updateExportGeneratorStatus,
} from '@/actions/exportGeneratorActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import moment from 'moment';

class ExportGeneratorsList extends Component {
  static propTypes = {
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  state = {
    refreshString: '',
  }

  onClickRemove = (item) => {
    const confirm = {
      message: `Are you sure you want to delete export generator "${item.get('name')}" ?`,
      onOk: () => {
        this.props.dispatch(deleteExportGenerator(item.getIn(['_id', '$id'])))
          .then(() => {
            this.setState({ refreshString: moment().format() });
          });
      },
      type: 'delete',
      labelOk: 'Delete',
    };
    this.props.dispatch(showConfirmModal(confirm));
  };

  onClickDisable = (item) => {
    const id = item.getIn(['_id', '$id']);
    this.props.dispatch(updateExportGeneratorStatus(id, false))
      .then(() => {
        this.setState({ refreshString: moment().format() });
      });
  };

  onClickEnable = (item) => {
    const id = item.getIn(['_id', '$id']);
    this.props.dispatch(updateExportGeneratorStatus(id, true))
      .then(() => {
        this.setState({ refreshString: moment().format() });
      });
  };

  onClickEdit = (item) => {
    this.props.router.push(`/export_generator/${item.get('name')}`);
  };

  onClickNew = () => {
    this.props.router.push('/export_generator');
  };

  parserStatus = (item) => {
    return (<StateIcon status={item.get('enabled', true) ? 'active' : 'expired'} />);
  };

  render() {
    const { refreshString } = this.state;

    const filterFields = [
      { id: 'name', placeholder: 'Search by Generator Name', type: 'string' },
    ];


    const tableFields = [
      { id: 'active', title: 'Status', parser: this.parserStatus, cssClass: 'state' },
      { id: 'name', title: 'Name', sort: true },
    ];

    const rowActions = [
      { type: 'edit', onClick: this.onClickEdit },
      { type: 'enable', helpText: 'Enable', onClick: this.onClickEnable, show: item => !item.get('enabled', true) },
      { type: 'disable', helpText: 'Disable', onClick: this.onClickDisable, show: item => item.get('enabled', true) },
      { type: 'remove', onClick: this.onClickRemove },
    ];

    const listActions = [
      { type: 'add', onClick: this.onClickNew },
      { type: 'refresh' },
    ];

    return (
      <div className="ExportGenerators">
        <EntityList
          collection="export_generators"
          itemsType="export generators"
          itemType="export_generator"
          tableFields={tableFields}
          actions={rowActions}
          listActions={listActions}
          refreshString={refreshString}
          filterFields={filterFields}
        />
      </div>
    );
  }
}

export default withRouter(connect()(ExportGeneratorsList));
