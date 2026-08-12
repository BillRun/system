import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import Field from '@/components/Field';
import EntityList from '../../EntityList';
import { getSettings } from '@/actions/settingsActions';
import { usageTypesDataSelector } from '@/selectors/settingsSelector';

class ProductsGroupsList extends Component {

  static propTypes = {
    usageTypesOptions: PropTypes.array,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    usageTypesOptions: [],
  };

  static filterFields = [
    { id: 'key', placeholder: 'Key' },
    { id: 'description', placeholder: 'Title' },
  ];

  state = {
    selectedType: '',
  }

  componentDidMount() {
    const { usageTypesOptions } = this.props;
    if (usageTypesOptions.length === 0) {
      this.props.dispatch(getSettings('usage_types'));
    }
  }

  onSelectFilterField = (value) => {
    const newVal = (value) ? value : '';
    this.setState(() => ({ selectedType: newVal }));
  }

  onClearFilters = () => {
    this.setState(() => ({ selectedType: '' }));
  }

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

  getCustomFilters = () => [{
    id: 'usage_type',
    renderFunction: this.renderUsageTypeFilter,
  }];

  getBaseFilter = () => {
    const { selectedType } = this.state;
    if (selectedType) {
      return { usage_types: selectedType };
    }
    return {};
  }

  renderUsageTypeFilter = () => {
    const { selectedType } = this.state;
    const { usageTypesOptions } = this.props;
    return (
      <Field
        fieldType="select"
        options={usageTypesOptions}
        value={selectedType}
        onChange={this.onSelectFilterField}
        placeholder="Select Activity Type..."
      />
    );
  }

  render () {
    return (
      <EntityList
        entityKey="products_group"
        filterFields={ProductsGroupsList.filterFields}
        tableFields={this.getFields()}
        projectFields={this.getProjectFields()}
        actions={this.getActions()}
        customFilters={this.getCustomFilters()}
        baseFilter={this.getBaseFilter()}
        onClearFilters={this.onClearFilters}
      />
    );
  }
}

const mapStateToProps = (state, props) => {
  const usageTypesData = usageTypesDataSelector(state, props);
  const usageTypesOptions = usageTypesData.map(usaget => ({
    value: usaget.get('usage_type', ''),
    label: usaget.get('label', ''),
  })).toJS();
  return ({
    usageTypesOptions,
  });
};

export default connect(mapStateToProps)(ProductsGroupsList);
