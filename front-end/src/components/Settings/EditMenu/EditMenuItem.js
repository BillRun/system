import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Col, Form, FormGroup, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';
import { ModalWrapper } from '@/components/Elements'


class EditMenuItem extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    editShow: PropTypes.bool,
    onChangeField: PropTypes.func,
  };

  static defaultProps = {
    editShow: true,
    item: Immutable.Map(),
    onChangeField: () => {},
  };

  constructor(props) {
    super(props);

    const MenuItem = Immutable.Record({
      title: props.item.get('title', ''),
      roles: props.item.get('roles', Immutable.List()),
    });

    this.state = {
      editMode: false,
      mouseOver: false,
      menuItem: new MenuItem(),
    };
  }

  shouldComponentUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
    return !Immutable.is(this.props.item, nextProps.item) || this.state !== nextState;
  }

  onChangeTitle = (e) => {
    const { value } = e.target;
    const { menuItem } = this.state;
    this.setState({ menuItem: menuItem.set('title', value) });
  }

  onChangeRoles = (roles) => {
    const { menuItem } = this.state;
    const rolesList = (roles.length) ? roles.split(',') : [];
    this.setState({ menuItem: menuItem.set('roles', Immutable.List(rolesList)) });
  }

  onChangeShowHide = (e) => {
    const { value } = e.target;
    const { item } = this.props;
    this.props.onChangeField(item.get('id'), 'show', value);
  }

  onCancelAdvencedEdit = () => {
    const { menuItem } = this.state;
    this.setState({ menuItem: menuItem.remove('title').remove('roles') });
    this.closeEdit();
  }

  onSaveAdvencedEdit = () => {
    const { props: { item }, state: { menuItem } } = this;
    const itemId = item.get('id');
    menuItem.toSeq().forEach((value, key) => {
      if (item.get(key, '') !== value) {
        this.props.onChangeField(itemId, key, value);
      }
    });
    this.closeEdit();
  }

  onMouseEnter = (e) => { // eslint-disable-line no-unused-vars
    this.setState({ mouseOver: true });
  }

  onMouseLeave = (e) => { // eslint-disable-line no-unused-vars
    this.setState({ mouseOver: false });
  }

  toggleEdit = (e) => {
    e.preventDefault();
    const { editMode } = this.state;
    this.setState({ editMode: !editMode });
  }

  closeEdit = () => {
    this.setState({ editMode: false });
  }

  renderEditModal = () => {
    const { props: { item }, state: { editMode, menuItem: { title, roles } } } = this;
    const currentTitle = item.get('title', '');
    const availableRoles = ['admin', 'read', 'write'].map(role => ({
      value: role,
      label: role,
    }));

    return (
      <ModalWrapper show={editMode} onOk={this.onSaveAdvencedEdit} onCancel={this.onCancelAdvencedEdit} title={`Edit ${currentTitle} Details`}>
        <Form horizontal>
          <FormGroup>
            <Col sm={2} componentClass={ControlLabel}>Label</Col>
            <Col sm={10}>
              <Field autoFocus onChange={this.onChangeTitle} value={title} />
            </Col>
          </FormGroup>

          <FormGroup >
            <Col sm={2} componentClass={ControlLabel}>Roles</Col>
            <Col sm={10}>
              <Field
                fieldType="select"
                multi={true}
                value={roles.join(',')}
                options={availableRoles}
                onChange={this.onChangeRoles}
              />
            </Col>
          </FormGroup>
        </Form>
      </ModalWrapper>
    );
  }

  renderRole = () => {
    const { menuItem: { roles } } = this.state;
    if (roles.size === 0) {
      return (<small>Visible to all roles</small>);
    }
    return (<small>{roles.join(',')}</small>);
  }

  renderTitle = () => {
    const { props: { item }, state: { menuItem: { title } } } = this;
    const icon = item.get('icon', '');
    const menuIcon = icon.length ? (<i className={`fa ${icon} fa-fw`} />) : (null);
    return (<span>{menuIcon} {title}</span>);
  }

  renderMouseOver = () => <span>&nbsp;<i className="fa fa-pencil-square-o fa-fw" /></span>;

  render() {
    const { props: { item, editShow }, state: { mouseOver, editMode } } = this;
    const show = item.get('show', false);
    const checkboxStyle = { marginTop: 10 };
    return (
      <Col md={12} onMouseEnter={this.onMouseEnter} onMouseLeave={this.onMouseLeave}>

        <Col md={6} style={{ color: '#008cba', cursor: 'pointer' }} onClick={this.toggleEdit}>
          { this.renderTitle() }
          { mouseOver && !editMode && this.renderMouseOver() }
        </Col>

        <Col md={4} className="text-right" style={{ cursor: 'pointer' }} onClick={this.toggleEdit}>
          { this.renderRole() }
          { mouseOver && !editMode && this.renderMouseOver() }
        </Col>

        <Col md={2} className="text-right" style={checkboxStyle}>
          <Field onChange={this.onChangeShowHide} value={show} fieldType="checkbox" disabled={!editShow} />
        </Col>

        {this.renderEditModal()}
      </Col>
    );
  }
}

export default EditMenuItem;
