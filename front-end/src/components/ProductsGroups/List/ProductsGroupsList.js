import React, { Component } from 'react';
import Immutable from 'immutable';
import EntityList from '../../EntityList';

class ProductsGroupsList extends Component {

  parseListField = fieldName => entity => entity
    .get(fieldName, Immutable.List())
    .join(', ');

  getFields = () => [
    { id: 'key', title: 'Key', sort: true },
    { id: 'description', title: 'Title', sort: true },
    { id: 'usage_types', title: 'Activity Types', parser: this.parseListField('usage_types') },
    { id: 'rates', title: 'Products', parser: this.parseListField('rates') },
  ];

  getProjectFields = () => ({
    key: 1,
    description: 1,
    property_type: 1,
    usage_types: 1,
    rates: 1,
  });

  getActions = () => [
    { type: 'edit' },
  ];

  render () {
    return (
      <EntityList
        entityKey="products_group"
        tableFields={this.getFields()}
        projectFields={this.getProjectFields()}
        actions={this.getActions()}
      />
    );
  }
}

export default ProductsGroupsList;
