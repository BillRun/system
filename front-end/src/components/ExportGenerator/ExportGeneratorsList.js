import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import { Panel, Col } from "react-bootstrap";
import List from '../List';
import { Actions, StateIcon } from '@/components/Elements';
import {
  fetchExportGenerators,
  deleteExportGenerator,
  updateExportGeneratorStatus,
} from '@/actions/exportGeneratorActions';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { exportGeneratorsSelector } from '@/selectors/settingsSelector';


class ExportGeneratorsList extends Component {

  static propTypes = {
    exportGenerators: PropTypes.instanceOf(Immutable.List),
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    exportGenerators: Immutable.List(),
  };

  componentDidMount() {
    this.props.dispatch(fetchExportGenerators());
  }

  onClickRemove = (item) => {
    const confirm = {
      message: `Are you sure you want to delete export generator "${item.get('name')}" ?`,
      onOk: () => this.onRemoveOk(item),
      type: 'delete',
      labelOk: 'Delete',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onRemoveOk = (item) => {
    const name = item.get('name', '');
    if (name !== '') {
      this.props.dispatch(deleteExportGenerator(item.get('name', '')));
    }
  }

  onClickEnable = (item) => {
    const name = item.get('name', '');
    if (name !== '') {
      this.props.dispatch(updateExportGeneratorStatus(name, true));
    }
  }

  onClickDisable = (item) => {
    const confirm = {
      message: `Are you sure you want to disable export generator "${item.get('name')}" ?`,
      onOk: () => this.onDisableOk(item),
      type: 'confirm',
      labelOk: 'Yes',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onDisableOk = (item) => {
    const name = item.get('name', '');
    if (name !== '') {
      this.props.dispatch(updateExportGeneratorStatus(name, false));
    }
  }

  onClickEdit = (item) => {
    this.props.router.push(`/export_generator/${item.get('name')}`);
  }

  onClickNew = () => {
    this.props.router.push('/export_generator');
  }

  parseShowEnable = item => !item.get('enabled', true);

  parseShowDisable = item => !(this.parseShowEnable(item));

  parserStatus = item => (<StateIcon status={item.get('enabled', true) ? 'active' : 'expired'} />);

  getListActions = () => [{
    type: 'add',
    actionStyle: 'primary',
    actionSize: 'xsmall',
    label: 'Add new',
    onClick: this.onClickNew,
  }];
  
  getListFields = () => [
    { id: 'active', title: 'Status', parser: this.parserStatus, cssClass: 'state' },
    { id: 'name', title: 'Name' },
  ];

  getRowActions = () => [
    { type: 'edit', showIcon: true, helpText: 'Edit', onClick: this.onClickEdit, show: true, onClickColumn: 'name' },
    { type: 'enable', showIcon: true, helpText: 'Enable', onClick: this.onClickEnable, show: this.parseShowEnable },
    { type: 'disable', showIcon: true, helpText: 'Disable', onClick: this.onClickDisable, show: this.parseShowDisable },
    { type: 'remove', showIcon: true, helpText: 'Remove', onClick: this.onClickRemove, show: true },
  ];

  renderPanelHeader = () => (
    <div>
      &nbsp;
      <div className="pull-right">
        <Actions actions={this.getListActions()} />
      </div>
    </div>
  );

  render() {
    const { exportGenerators } = this.props;
    const fields = this.getListFields();
    const actions = this.getRowActions();
    return (
      <div className="ExportGenerators">
        <Col sm={12}>
          <Panel header={this.renderPanelHeader()}>
            <List
              items={exportGenerators}
              fields={fields}
              actions={actions}
            />
          </Panel>
        </Col>
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  exportGenerators: exportGeneratorsSelector(state, props),
});

export default withRouter(connect(mapStateToProps)(ExportGeneratorsList));
