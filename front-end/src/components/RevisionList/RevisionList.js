import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import moment from 'moment';
import { lowerCase, sentenceCase } from 'change-case';
import { ConfirmModal, StateIcon, ZoneDate } from '@/components/Elements';
import CloseActionBox from '../Entity/CloseActionBox';
import MoveActionBox from '../Entity/MoveActionBox';
import ReopenActionBox from '../Entity/ReopenActionBox';
import List from '@/components/List';
import {
  getItemDateValue,
  getConfig,
  isItemClosed,
  getItemId,
  isItemFinite,
  toImmutableList,
} from '@/common/Util';
import { showSuccess } from '@/actions/alertsActions';
import { deleteEntity, moveEntity, reopenEntity } from '@/actions/entityActions';
import { getRevisions } from '@/actions/entityListActions';

class RevisionList extends Component {

  static propTypes = {
    items: PropTypes.instanceOf(Immutable.List),
    onSelectItem: PropTypes.func,
    onDeleteItem: PropTypes.func,
    onCloseItem: PropTypes.func,
    itemName: PropTypes.string.isRequired,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    onActionEdit: PropTypes.func,
    onActionClone: PropTypes.func,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    items: Immutable.List(),
    onSelectItem: () => {},
    onDeleteItem: () => {},
    onCloseItem: () => {},
  };

  state = {
    showConfirmRemove: false,
    itemToRemove: null,
    showMoveModal: false,
    itemToMove: null,
    itemToReopen: null,
  }

  isItemLast = item => item.getIn(['revision_info', 'is_last'], true);

  isItemEditable = item => ['future'].includes(item.getIn(['revision_info', 'status'], ''))
    || (this.isItemActive(item) && this.isItemLast(item));

  isItemMovable = item => item.getIn(['revision_info', 'movable_from'], true) || item.getIn(['revision_info', 'movable_to'], true);

  isItemReopenable = (item) => {
    const { items } = this.props;
    const index = items.indexOf(item);
    const nextRevision = index > 0 ? items.get(index - 1, Immutable.Map()) : Immutable.Map();
    const lastRevision = items.first();
    // can only reopen last expired entity, that the last revision of it is not unlimited
    return ['expired'].includes(item.getIn(['revision_info', 'status'], ''))
    && !['expired'].includes(nextRevision.getIn(['revision_info', 'status'], ''))
    && isItemFinite(lastRevision);
  }

  isItemActive = item => ['active'].includes(item.getIn(['revision_info', 'status'], ''));

  isItemExpired = item => ['expired'].includes(item.getIn(['revision_info', 'status'], ''));

  parseEditShow = item => this.isItemEditable(item);

  parseViewShow = item => !this.isItemEditable(item);

  parseMoveEnable = item => this.isItemMovable(item);

  parseReopenEnable = item => this.isItemReopenable(item);

  parserState = item => (<StateIcon status={item.getIn(['revision_info', 'status'], '')} />);

  parseFromDate = (item) => {
    const fromDate = getItemDateValue(item, 'from', null);
    if (moment.isMoment(fromDate)) {
      return <ZoneDate value={fromDate} format={getConfig('dateFormat', 'DD/MM/YYYY')} />;
    }
    return '-';
  };

  parseToDate = (item) => {
    const toDate = getItemDateValue(item, 'to', null);
    const statusWithTwoDate = this.isItemExpired(item)
      || (this.isItemActive(item) && !this.isItemLast(item));
    if (moment.isMoment(toDate) && (isItemClosed(item) || statusWithTwoDate)) {
      return <ZoneDate value={toDate.subtract(1,'seconds')} format={getConfig('dateFormat', 'DD/MM/YYYY')} />;
    }
    return '-';
  };

  onClickEdit = (item) => {
    const { itemName } = this.props;
    this.props.onSelectItem();
    if (!this.props.onActionEdit) {
      const itemId = getItemId(item, '');
      const itemType = getConfig(['systemItems', itemName, 'itemType'], '');
      const itemsType = getConfig(['systemItems', itemName, 'itemsType'], '');
      this.props.router.push(`${itemsType}/${itemType}/${itemId}`);
    } else {
      this.props.onActionEdit(item, itemName);
    }
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

  onClickClone = (item) => {
    const { itemName } = this.props;
    this.props.onSelectItem();
    if (!this.props.onActionClone) {
      const itemId = getItemId(item, '');
      const itemType = getConfig(['systemItems', itemName, 'itemType'], '');
      const itemsType = getConfig(['systemItems', itemName, 'itemsType'], '');
      this.props.router.push({
        pathname: `${itemsType}/${itemType}/${itemId}`,
        query: {
          action: 'clone',
        },
      });
    } else {
      this.props.onActionClone(item, itemName, 'clone');
    }
  }

  onClickRemoveOk = () => {
    const { itemName } = this.props;
    const { itemToRemove } = this.state;
    const collection = getConfig(['systemItems', itemName, 'collection'], '');
    this.props.dispatch(deleteEntity(collection, itemToRemove)).then(this.afterRemove);
  }

  onClickMove = (item) => {
    this.setState({
      showMoveModal: true,
      itemToMove: item,
    });
  }

  onClickMoveClose = () => {
    this.setState({
      showMoveModal: false,
      itemToMove: null,
    });
  }

  onClickMoveOk = (item, type) => {
    const { itemName } = this.props;
    const collection = getConfig(['systemItems', itemName, 'collection'], '');
    this.props.dispatch(moveEntity(collection, item, type)).then(this.afterMove);
  }

  afterMove = (response) => {
    if (response.status) {
      this.props.dispatch(showSuccess('Revision was moved'));
      this.props.onCloseItem();
    }
  }

  afterRemove = (response) => {
    const { itemToRemove } = this.state;
    const { itemName } = this.props;
    if (response.status) {
      this.props.dispatch(showSuccess('Revision was removed'));
      const collection = getConfig(['systemItems', itemName, 'collection'], '');
      const uniqueFields = toImmutableList(getConfig(['systemItems', itemName, 'uniqueField'], Immutable.List()));
      const keys = uniqueFields.map(uniqueField => itemToRemove.get(uniqueField, ''));
      const removedRevisionId = getItemId(itemToRemove);
      this.props.dispatch(getRevisions(collection, uniqueFields, keys)); // refetch revision list because item was (changed in / added to) list
      this.onClickRemoveClose();
      this.props.onDeleteItem(removedRevisionId);
    }
  }

  onClickReopen = (item) => {
    this.setState({
      showReopenModal: true,
      itemToReopen: item,
    });
  }

  onClickReopenClose = () => {
    this.setState({
      showReopenModal: false,
      itemToReopen: null,
    });
  }

  onClickReopenOk = (item, fromDate) => {
    const { itemName } = this.props;
    const collection = getConfig(['systemItems', itemName, 'collection'], '');
    this.props.dispatch(reopenEntity(collection, item, fromDate)).then(this.afterReopen);
  }

  afterReopen = (response) => {
    if (response.status) {
      this.props.dispatch(showSuccess('Revision was reopened'));
      this.onClickReopenClose();
      this.props.onCloseItem();
    }
  }

  getActionHelpText = (item, type) => {
    const { itemName } = this.props;
    switch (lowerCase(type)) {
      case 'clone':
        return `Clone as new ${getConfig(['systemItems', itemName, 'itemName'], 'item')}`;
      case 'remove':
        return 'Remove revision';
      case 'edit':
        return 'Edit revision';
      case 'view':
        return 'View revision details';
      case 'move':
        return 'Move revision in time';
      case 'reopen':
        return 'Reopen revision';
      default:
        return sentenceCase(type);
    }
  }

  getListFields = () => [
    { id: 'state', parser: this.parserState, cssClass: 'state' },
    { id: 'from', title: 'Start date', parser: this.parseFromDate, cssClass: 'short-date' },
    { id: 'to', title: 'To date', parser: this.parseToDate },
  ]

  getListActions = () => [
    { type: 'view', helpText: this.getActionHelpText, onClick: this.onClickEdit, show: this.parseViewShow, onClickColumn: 'from' },
    { type: 'edit', helpText: this.getActionHelpText, onClick: this.onClickEdit, show: this.parseEditShow, onClickColumn: 'from' },
    { type: 'clone', helpText: this.getActionHelpText, onClick: this.onClickClone },
    { type: 'move', helpText: this.getActionHelpText, onClick: this.onClickMove, enable: this.parseMoveEnable },
    { type: 'reopen', helpText: this.getActionHelpText, onClick: this.onClickReopen, enable: this.parseReopenEnable },
    { type: 'remove', helpText: this.getActionHelpText, onClick: this.onClickRemove },
  ]

  renderMoveModal = () => {
    const { items, itemName } = this.props;
    const { showMoveModal, itemToMove } = this.state;
    if (showMoveModal) {
      return (
        <MoveActionBox
          itemId={getItemId(itemToMove)}
          itemName={itemName}
          revisions={items}
          onMoveItem={this.onClickMoveOk}
          onCancelMoveItem={this.onClickMoveClose}
        />
      );
    }
    return null;
  }

  renderReopenModal = () => {
    const { items, itemName } = this.props;
    const { showReopenModal, itemToReopen } = this.state;
    if (showReopenModal) {
      return (
        <ReopenActionBox
          item={itemToReopen}
          itemName={itemName}
          revisions={items}
          onReopenItem={this.onClickReopenOk}
          onCancelReopenItem={this.onClickReopenClose}
        />
      );
    }
    return null;
  }

  render() {
    const { items, itemName } = this.props;
    const { showConfirmRemove } = this.state;
    const fields = this.getListFields();
    const actions = this.getListActions();
    const activeItem = items.find(this.isItemActive);
    const removeConfirmMessage = 'Are you sure you want to remove this revision?';
    return (
      <div>
        <List items={items} fields={fields} edit={false} actions={actions} className="scrollbox screen-height-70" />
        { activeItem &&
          <CloseActionBox
            itemName={itemName}
            item={activeItem}
            onCloseItem={this.props.onCloseItem}
          />
        }
        <ConfirmModal onOk={this.onClickRemoveOk} onCancel={this.onClickRemoveClose} show={showConfirmRemove} message={removeConfirmMessage} labelOk="Yes" />
        { this.renderMoveModal() }
        { this.renderReopenModal() }
      </div>
    );
  }
}

export default withRouter(connect()(RevisionList));
