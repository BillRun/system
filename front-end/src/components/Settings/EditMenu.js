import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Grid, Col } from 'react-bootstrap';
import SortableMenuItem from './EditMenu/SortableMenuItem';
import SortableMenuList from './EditMenu/SortableMenuList';


export default class EditMenu extends Component {

  static propTypes = {
    onChange: PropTypes.func.isRequired,
    onChangeMenuOrder: PropTypes.func.isRequired,
    data: PropTypes.instanceOf(Immutable.Iterable),
    disallowEditShow: PropTypes.instanceOf(Immutable.Iterable),
  };

  static defaultProps = {
    disallowEditShow: Immutable.List(['settings', 'settings_general', 'input_processors', 'custom_fields']),
    data: Immutable.Map(),
  };

  shouldComponentUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
    return !Immutable.is(this.props.data, nextProps.data);
  }

  onChangeField = (menuId, key, value) => {
    this.props.onChange('menu', ['main', menuId, key], value);
  }

  onChangeOrder = (newOrder) => {
    this.props.onChangeMenuOrder(['main'], newOrder);
  }

  onDragEnd = ({ oldIndex, newIndex, collection }) => {
    const { data } = this.props;
    const path = (collection === '') ? [] : collection.split('-');
    let changes = Immutable.Map();
    data.getIn(path).forEach((menuItem) => {
      const order = menuItem.get('order');
      const menuId = menuItem.get('id');
      if (order >= Math.min(newIndex, oldIndex) && order <= Math.max(newIndex, oldIndex)) {
        if (order === oldIndex) {
          changes = changes.set(menuId, newIndex);
        } else if (order > oldIndex) {
          changes = changes.set(menuId, order - 1);
        } else {
          changes = changes.set(menuId, order + 1);
        }
      }
    });
    this.onChangeOrder(changes);
  };

  renderMenu = (item, index, path) => {
    const { disallowEditShow } = this.props;
    const collection = path.join('-');
    const MenuItemData = Immutable.Record({
      item,
      newPath: [...path, index],
      onChangeField: this.onChangeField,
      renderTree: this.renderTree,
      subMenus: item.get('subMenus', Immutable.List()),
      editShow: true,
    });
    return (
      <SortableMenuItem
        collection={collection}
        index={index}
        key={item.get('id')}
        data={new MenuItemData({ editShow: !disallowEditShow.includes(item.get('id')) })}
      />
    );
  }

  renderTree = (tree, path, depth) => {
    const MenuListData = Immutable.Record({
      path,
      items: tree,
      renderMenu: this.renderMenu,
    });
    return (
      <SortableMenuList
        lockAxis="y"
        helperClass="draggable-row"
        key={depth}
        onSortEnd={this.onDragEnd}
        useDragHandle={true}
        data={new MenuListData()}
      />
    );
  }

  render() {
    const { data } = this.props;
    return (
      <div>
        <Col md={6} className="text-left">Main Menu</Col>
        <Col md={4} className="text-right">Roles</Col>
        <Col md={2} className="text-right">Show/Hide</Col>
        <Grid bsClass="wrapper" style={{ paddingTop: 35 }}>
          { this.renderTree(data, [], 'root') }
        </Grid>
      </div>
    );
  }
}
