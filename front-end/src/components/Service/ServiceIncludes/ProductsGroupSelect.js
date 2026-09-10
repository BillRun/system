import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Button, InputGroup } from 'react-bootstrap';
import { InputGroupButton } from '@/common/BootstrapCompat';
import Field from '@/components/Field';
import ProductsGroupCreatePopup from './ProductsGroupCreatePopup';

/**
 * Searchable products groups drop-down with an option to create
 * a new products group on-the-fly.
 * For a usage based quota only products groups with a property type are available.
 */
export default class ProductsGroupSelect extends Component {

  static propTypes = {
    value: PropTypes.string,
    onChange: PropTypes.func.isRequired,
    productsGroups: PropTypes.instanceOf(Immutable.List),
    usedKeys: PropTypes.instanceOf(Immutable.List),
    usageBased: PropTypes.bool,
    allowCreate: PropTypes.bool,
    onGroupCreated: PropTypes.func,
    plays: PropTypes.string,
    disabled: PropTypes.bool,
    existingGroupsNames: PropTypes.instanceOf(Immutable.List),
  }

  static defaultProps = {
    value: '',
    productsGroups: Immutable.List(),
    usedKeys: Immutable.List(),
    usageBased: true,
    allowCreate: true,
    onGroupCreated: () => {},
    plays: '',
    disabled: false,
    existingGroupsNames: Immutable.List(),
  };

  state = {
    showCreatePopup: false,
  }

  onClickCreate = () => {
    this.setState({ showCreatePopup: true });
  }

  onCancelCreate = () => {
    this.setState({ showCreatePopup: false });
  }

  onGroupCreated = (productsGroup) => {
    this.setState({ showCreatePopup: false });
    this.props.onGroupCreated(productsGroup);
    this.props.onChange(productsGroup.get('key', ''));
  }

  getOptions = () => {
    const { productsGroups, usedKeys, usageBased, value } = this.props;
    return productsGroups
      .filter((productsGroup) => {
        const key = productsGroup.get('key', '');
        return key === value || !usedKeys.includes(key);
      })
      .filter((productsGroup) => {
        const propertyType = productsGroup.get('property_type', '') || '';
        return !usageBased || propertyType !== '';
      })
      .map((productsGroup) => {
        const key = productsGroup.get('key', '');
        const description = productsGroup.get('description', '');
        return {
          value: key,
          label: description !== '' ? `${key} (${description})` : key,
        };
      })
      .toArray();
  }

  renderSelect = () => {
    const { value, disabled } = this.props;
    return (
      <Field
        fieldType="select"
        value={value}
        options={this.getOptions()}
        onChange={this.props.onChange}
        disabled={disabled}
        placeholder="Select products group..."
        noResultsText="No products groups found."
        searchPromptText="Type products group key to search"
      />
    );
  }

  render() {
    const { usageBased, allowCreate, plays, disabled, existingGroupsNames } = this.props;
    const { showCreatePopup } = this.state;
    if (!allowCreate) {
      return this.renderSelect();
    }
    return (
      <span>
        <InputGroup>
          <InputGroupButton>
            <Button variant="primary" onClick={this.onClickCreate} disabled={disabled}>
              <i className="fa fa-plus" />&nbsp;
            </Button>
          </InputGroupButton>
          {this.renderSelect()}
        </InputGroup>
        <ProductsGroupCreatePopup
          show={showCreatePopup}
          monetaryBased={!usageBased}
          plays={plays}
          existingGroupsNames={existingGroupsNames}
          onCancel={this.onCancelCreate}
          onCreated={this.onGroupCreated}
        />
      </span>
    );
  }
}
