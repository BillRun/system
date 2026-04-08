import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import withRouter from '@/common/withRouter';
import Immutable from 'immutable';
import changeCase from 'change-case';
import Field from '@/components/Field';
import EntityList from '../EntityList';
import { LoadingItemPlaceholder } from '@/components/Elements';
import { usageTypesDataSelector } from '@/selectors/settingsSelector';
import {
  getSettings,
} from '@/actions/settingsActions';
import { isPlaysEnabledSelector } from '@/selectors/settingsSelector';
import { getConfig, getFieldName } from '@/common/Util';


class ProductsList extends Component {

  static propTypes = {
    fields: PropTypes.instanceOf(Immutable.List),
    isPlaysEnabled: PropTypes.bool,
    usageTypesOptions: PropTypes.array,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    fields: null,
    isPlaysEnabled: false,
    usageTypesOptions: [],
  }

  static defaultListFields = getConfig(['systemItems', 'product', 'defaultListFields'], Immutable.List());

  static rowActions = [
    { type: 'edit' },
    { type: 'view' },
  ]

  state = {
    selectedType: '',
  }

  
  onSelectFilterField = (value) => {
    const newVal= (value) ? value : '';
    this.setState(() => ({ selectedType: newVal }));
  }

  onClearFilters = () => {
    this.setState(() => ({ selectedType: '' }));
  }

  parserUsegt = (item) => {
    const usegt = item.get('rates', Immutable.Map()).keySeq().first();
    return (typeof usegt !== 'undefined') ? usegt : '';
  };

  filterPlayField = (field) => {
    const { isPlaysEnabled } = this.props;
    if (field.get('field_name', '') !== 'play') {
      return true;
    }
    return isPlaysEnabled;
  }

  isVisibleListField = (field) => {
    const fieldName = field.get('field_name', '');
    if (fieldName === 'play') {
      return false;
    }
    return field.get('show_in_list', false) || ProductsList.defaultListFields.includes(fieldName);
  }

  getProjectFields = () => {
    const { fields } = this.props;
    return fields
      .filter(this.isVisibleListField)
      .reduce((acc, field) => acc.set(field.get('field_name'), 1), Immutable.Map({}))
      .toJS();
  };

  getCustomFilters = () => [{
    id: 'usage_type',
    renderFunction: this.renderUsageTypeFilter,
  }];

  getFields = () => {
    const { fields } = this.props;
    return fields
      .filter(this.isVisibleListField)
      .map((field) => {
        const fieldname = field.get('field_name');
        const fieldTitle = field.get('title', getFieldName(fieldname, 'subscription', changeCase.sentenceCase(fieldname)));
        switch (fieldname) {
          case 'rates':
            return { id: 'unit_type', title: fieldTitle, parser: this.parserUsegt };
          case 'description':
            return { id: fieldname, title: fieldTitle, sort: true };
          case 'key':
            return { id: fieldname, title: fieldTitle, sort: true };
          default: 
            return { id: fieldname, title: fieldTitle };
        }
      })
      .toArray();
  };

  getBaseFilter = () => {
    const { selectedType } = this.state;
    if (selectedType) {
      return {[`rates.${selectedType}`]: { '$exists': true }};
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

  
  componentDidMount() {
    this.props.dispatch(getSettings('rates.fields'));
  }

  render() {
    const { fields } = this.props;
    if (fields === null) {
      return (<LoadingItemPlaceholder />);
    }
    return (
      <EntityList
        collection="rates"
        itemType="product"
        itemsType="products"
        tableFields={this.getFields()}
        projectFields={this.getProjectFields()}
        showRevisionBy="key"
        actions={ProductsList.rowActions}
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
    fields: state.settings.getIn(['rates', 'fields']) || undefined,
    isPlaysEnabled: isPlaysEnabledSelector(state, props),
    usageTypesOptions,
  });
}

export default withRouter(connect(mapStateToProps)(ProductsList));
