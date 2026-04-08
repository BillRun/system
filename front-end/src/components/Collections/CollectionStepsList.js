import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { titleCase } from 'change-case';
import { Col } from 'react-bootstrap';
import { Actions, StateIcon } from '@/components/Elements';
import List from '@/components/List';
import { getConfig } from '@/common/Util';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';


class CollectionStepsList extends Component {

  static propTypes = {
    steps: PropTypes.instanceOf(Immutable.List),
    onChange: PropTypes.func.isRequired,
    onRemove: PropTypes.func.isRequired,
    onClickAdd: PropTypes.func.isRequired,
    onClickEdit: PropTypes.func.isRequired,
    onClickClone: PropTypes.func.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    steps: Immutable.List(),
  };

  onRemoveOk = (step) => {
    this.props.onRemove(step);
  }

  // onToggleOk = (step, action) => {
  //   const enable = (action === 'enable');
  //   this.props.onChange(step.set('active', enable));
  // }

  onClickRemove = (item) => {
    const confirm = {
      message: `Are you sure you want to remove "${item.get('name')}" step?`,
      onOk: () => this.onRemoveOk(item),
      type: 'delete',
      labelOk: 'Delete',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onClickToggle = (item, type) => {
    // because it type not save the state, no need confirmation
    const enable = (type === 'enable');
    this.props.onChange(item.set('active', enable));
    // const actionName = (type === 'disable') ? 'disable' : 'enable';
    // const confirm = {
    //   message: `Are you sure you want to ${actionName} "${item.get('name')}" step?`,
    //   onOk: () => this.onToggleOk(item, type),
    //   type: (type === 'enable') ? 'confirm' : 'delete',
    //   labelOk: titleCase(actionName),
    // };
    // this.props.dispatch(showConfirmModal(confirm));
  }

  parserStatus = item => (<StateIcon status={item.get('active', false) ? 'active' : 'expired'} />);

  parserType = item => (
    <span>
      <i className={`fa ${getConfig(['collections', 'step_types', item.get('type', ''), 'icon'], 'fa-circle-o')}`} />
      &nbsp;
      {getConfig(['collections', 'step_types', item.get('type', ''), 'label'], '')}
    </span>
  );

  parserTrigger = item => `Within ${item.get('do_after_days', '')} days`;

  parseShowEnable = item => !item.get('active', true);

  parseShowDisable = item => !(this.parseShowEnable(item));

  getListFields = () => [
    { id: 'active', title: 'Status', parser: this.parserStatus, cssClass: 'state' },
    { id: 'do_after_days', title: 'Trigger after', parser: this.parserTrigger },
    { id: 'name', title: 'Step Name' },
    { id: 'type', title: 'Type', parser: this.parserType },
  ]

  getListActions = () => [
    { type: 'edit', showIcon: true, helpText: 'Edit', onClick: this.props.onClickEdit, actionStyle: 'link', actionSize: 'xsmall' },
    { type: 'enable', showIcon: true, helpText: 'Enable', onClick: this.onClickToggle, show: this.parseShowEnable, actionStyle: 'link', actionSize: 'xsmall' },
    { type: 'disable', showIcon: true, helpText: 'Disable', onClick: this.onClickToggle, show: this.parseShowDisable, actionStyle: 'link', actionSize: 'xsmall' },
    { type: 'clone', showIcon: true, helpText: 'Clone', onClick: this.props.onClickClone, actionStyle: 'link', actionSize: 'xsmall' },
    { type: 'remove', showIcon: true, helpText: 'Remove', onClick: this.onClickRemove, actionStyle: 'link', actionSize: 'xsmall' },
  ];

  renderPanelHeader = () => {
    const actions = getConfig(['collections', 'step_types'], Immutable.Map())
      .filter(type => type.get('enable', false))
      .map((details, type) => ({
        type: 'add',
        actionStyle: 'primary',
        actionSize: 'xsmall',
        label: `Add new ${getConfig(['collections', 'step_types', type, 'label'], '')} step`,
        onClick: this.props.onClickAdd(type),
      }))
      .toList()
      .toArray();

    return (
      <Actions actions={actions} />
    );
  }
  render() {
    const { steps } = this.props;
    const orderedSteps = steps.sortBy(step => step.get('do_after_days', 0));
    const fields = this.getListFields();
    const actions = this.getListActions();
    return (
      <div>
        <Col sm={12}>
          <List
            items={orderedSteps}
            fields={fields}
            actions={actions}
          />
        </Col>
        <Col sm={12}>
          { this.renderPanelHeader()}
        </Col>
      </div>
    );
  }
}

export default connect(null)(CollectionStepsList);
