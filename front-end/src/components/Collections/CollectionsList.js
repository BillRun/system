import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { titleCase } from 'change-case';
import { Panel, Col } from 'react-bootstrap';
import { ActionButtons, Actions, StateIcon } from '@/components/Elements';
import List from '@/components/List';
import { getConfig } from '@/common/Util';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';
import {
  removeCollectionStep,
  getCollectionSteps,
  saveCollectionSteps,
  updateCollectionStep,
} from '@/actions/collectionsActions';
import { collectionStepsSelectorForList } from '@/selectors/settingsSelector';


class CollectionsList extends Component {

  static propTypes = {
    items: PropTypes.instanceOf(Immutable.List),
    onAddStep: PropTypes.func.isRequired,
    onClickEdit: PropTypes.func.isRequired,
    onClickClone: PropTypes.func.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    items: Immutable.List(),
  };

  componentWillMount() {
    this.props.dispatch(getCollectionSteps());
  }

  onSaveCollectionsSteps = () => {
    this.props.dispatch(saveCollectionSteps()).then(this.afterSaveCollectionsSteps);
  }

  afterSaveCollectionsSteps = () => {
    this.props.dispatch(getCollectionSteps());
  }

  onRemoveOk = (item) => {
    this.props.dispatch(removeCollectionStep(item));
  }

  onToggleOk = (item, action) => {
    const enable = (action === 'enable');
    this.props.dispatch(updateCollectionStep(item, ['active'], enable));
  }

  onClickRemove = (item) => {
    const confirm = {
      message: `Are you sure you want to delete "${item.get('name')}" step?`,
      onOk: () => this.onRemoveOk(item),
      type: 'delete',
      labelOk: 'Delete',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onClickToggle = (item, type) => {
    const actionName = (type === 'disable') ? 'disable' : 'enable';
    const confirm = {
      message: `Are you sure you want to ${actionName} "${item.get('name')}" step?`,
      onOk: () => this.onToggleOk(item, type),
      type: (type === 'enable') ? 'confirm' : 'delete',
      labelOk: titleCase(actionName),
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  parserStatus = item => (<StateIcon status={item.get('active', false) ? 'active' : 'expired'} />);

  parserType = item => (
    <span>
      <i className={`fa ${getConfig(['collections', 'step_types', item.get('type', ''), 'icon'], 'fa-circle-o')}`} />
      &nbsp;
      {getConfig(['collections', 'step_types', item.get('type', ''), 'label'], '')}
    </span>
  );

  parserTriger = item => `Within ${item.get('do_after_days', '')} days`;

  parseShowEnable = item => !item.get('active', true);

  parseShowDisable = item => !(this.parseShowEnable(item));

  getListFields = () => [
    { id: 'active', title: 'Status', parser: this.parserStatus, cssClass: 'state' },
    { id: 'do_after_days', title: 'Trigger after', parser: this.parserTriger },
    { id: 'name', title: 'Step Name' },
    { id: 'type', title: 'Type', parser: this.parserType },
  ]

  getListActions = () => [
    { type: 'edit', showIcon: true, helpText: 'Edit', onClick: this.props.onClickEdit },
    { type: 'enable', showIcon: true, helpText: 'Enable', onClick: this.onClickToggle, show: this.parseShowEnable },
    { type: 'disable', showIcon: true, helpText: 'Disable', onClick: this.onClickToggle, show: this.parseShowDisable },
    { type: 'clone', showIcon: true, helpText: 'Clone', onClick: this.props.onClickClone },
    { type: 'remove', showIcon: true, helpText: 'Remove', onClick: this.onClickRemove },
  ];

  renderPanelHeader = () => {
    const actions = getConfig(['collections', 'step_types'], Immutable.Map())
      .filter(type => type.get('enable', false))
      .map((details, type) => ({
        type: 'add',
        actionStyle: 'primary',
        actionSize: 'xsmall',
        label: `Add new ${getConfig(['collections', 'step_types', type, 'label'], '')} step`,
        onClick: this.props.onAddStep(type),
      }))
      .toList()
      .toArray();

    return (
      <div>
        &nbsp;
        <div className="pull-right"><Actions actions={actions} /></div>
      </div>
    );
  }
  render() {
    const { items } = this.props;
    const fields = this.getListFields();
    const actions = this.getListActions();
    return (
      <div>
        <Col sm={12}>
          <Panel header={this.renderPanelHeader()}>
            <List
              items={items}
              fields={fields}
              actions={actions}
            />
          </Panel>
        </Col>
        <Col sm={12}>
          <ActionButtons onClickSave={this.onSaveCollectionsSteps} hideCancel={true} />
        </Col>
      </div>
    );
  }
}


const mapStateToProps = (state, props) => ({
  items: collectionStepsSelectorForList(state, props),
});

export default connect(mapStateToProps)(CollectionsList);
