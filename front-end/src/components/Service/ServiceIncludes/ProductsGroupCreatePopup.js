import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import moment from 'moment';
import { Modal, Button } from 'react-bootstrap';
import ProductsGroupDetails from '../../ProductsGroups/Entity/ProductsGroupDetails';
import { showDanger } from '@/actions/alertsActions';
import {
  updateProductsGroup,
  deleteProductsGroupValue,
  clearProductsGroup,
  saveProductsGroup,
} from '@/actions/productsGroupsActions';
import { itemSelector } from '@/selectors/entitySelector';
import { usageTypesDataSelector } from '@/selectors/settingsSelector';
import { getConfig, getUsagePropertyType } from '@/common/Util';

/**
 * Popup to create a products group on-the-fly from the "Service Includes" screen.
 * The form itself is the products group entity form, so that both screens stay in
 * step; for a monetary based quota it disables the activity types and does not
 * filter the products by them.
 */
class ProductsGroupCreatePopup extends Component {

  static propTypes = {
    show: PropTypes.bool,
    monetaryBased: PropTypes.bool,
    plays: PropTypes.string,
    item: PropTypes.instanceOf(Immutable.Map),
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    onCancel: PropTypes.func.isRequired,
    onCreated: PropTypes.func.isRequired,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    show: false,
    monetaryBased: false,
    plays: '',
    item: Immutable.Map(),
    usageTypesData: Immutable.List(),
  };

  state = {
    progress: false,
  }

  componentDidUpdate(prevProps) {
    if (!prevProps.show && this.props.show) {
      this.initItem();
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearProductsGroup());
  }

  initItem = () => {
    this.props.dispatch(clearProductsGroup());
    this.props.dispatch(updateProductsGroup(['from'], moment().toISOString()));
  }

  onFieldUpdate = (path, value) => {
    this.props.dispatch(updateProductsGroup(path, value));
  }

  onFieldRemove = (path) => {
    this.props.dispatch(deleteProductsGroupValue(path));
  }

  getUsageTypesToSave = () => {
    const { item, monetaryBased } = this.props;
    // a monetary based group has no activity types, and so no property type
    return monetaryBased
      ? Immutable.List()
      : item.get('usage_types', Immutable.List()).filter(usageType => usageType !== '');
  }

  getValidationError = (usageTypes) => {
    const { item, monetaryBased } = this.props;
    if (item.get('description', '') === '') {
      return 'Title is required';
    }
    const key = item.get('key', '');
    if (key === '' || !getConfig('keyUppercaseRegex', /./).test(key)) {
      return 'A valid key is required';
    }
    if (!monetaryBased && usageTypes.isEmpty()) {
      return 'Please select at least one activity type';
    }
    if (item.get('rates', Immutable.List()).isEmpty()) {
      return 'Please select at least one product';
    }
    return '';
  }

  handleCancel = () => {
    this.props.dispatch(clearProductsGroup());
    this.props.onCancel();
  }

  handleSave = () => {
    const { item, monetaryBased, usageTypesData } = this.props;
    const usageTypes = this.getUsageTypesToSave();
    const error = this.getValidationError(usageTypes);
    if (error !== '') {
      this.props.dispatch(showDanger(error));
      return;
    }
    const itemToSave = item.set('usage_types', usageTypes);
    this.setState({ progress: true });
    this.props.dispatch(saveProductsGroup(itemToSave, 'create')).then((response) => {
      this.setState({ progress: false });
      if (!response.status) {
        return;
      }
      // the property type is derived by the server from the first activity type
      const createdGroup = Immutable.Map({
        key: itemToSave.get('key', ''),
        description: itemToSave.get('description', ''),
        property_type: monetaryBased ? null : getUsagePropertyType(usageTypesData, usageTypes.get(0, '')),
        usage_types: usageTypes,
        rates: itemToSave.get('rates', Immutable.List()),
      });
      this.props.dispatch(clearProductsGroup());
      this.props.onCreated(createdGroup);
    });
  }

  render() {
    const { show, monetaryBased, plays, item } = this.props;
    const { progress } = this.state;
    return (
      <Modal show={show} keyboard={false} size="lg">
        <div className="modal-header">
          <button type="button" className="close" onClick={this.handleCancel} aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h4 className="modal-title">Create New Products Group</h4>
        </div>
        <Modal.Body>
          <ProductsGroupDetails
            item={item}
            mode="create"
            monetaryBased={monetaryBased}
            plays={plays}
            onFieldUpdate={this.onFieldUpdate}
            onFieldRemove={this.onFieldRemove}
          />
        </Modal.Body>
        <Modal.Footer>
          <Button size="sm" variant="outline-secondary" onClick={this.handleCancel} style={{ marginRight: 9, minWidth: 90 }}>Cancel</Button>
          <Button size="sm" variant="primary" onClick={this.handleSave} disabled={progress} style={{ minWidth: 90 }}>Save</Button>
        </Modal.Footer>
      </Modal>
    );
  }
}

const mapStateToProps = (state, props) => ({
  item: itemSelector(state, props, 'products_group'),
  usageTypesData: usageTypesDataSelector(state, props),
});

export default connect(mapStateToProps)(ProductsGroupCreatePopup);
