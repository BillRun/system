import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import changeCase from 'change-case';
import EntityList from '../EntityList';
import { LoadingItemPlaceholder } from '@/components/Elements';
import {
  getSettings,
} from '@/actions/settingsActions';
import { isPlaysEnabledSelector } from '@/selectors/settingsSelector';


class ProductsList extends Component {

  static propTypes = {
    fields: PropTypes.instanceOf(Immutable.List),
    defaultListFields: PropTypes.arrayOf(PropTypes.string),
    isPlaysEnabled: PropTypes.bool,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    fields: null,
    isPlaysEnabled: false,
    defaultListFields: ['description', 'key', 'rates'],
  }

  static rowActions = [
    { type: 'edit' },
    { type: 'view' },
  ]

  // TODO: get values field.get('searchable', false) when it will be ready
  // depend on database index
  static filterFields = [
    { id: 'key', placeholder: 'Key' },
  ]

  componentWillMount() {
    this.props.dispatch(getSettings('rates.fields'));
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

  getProjectFields = () => {
    const { fields, defaultListFields } = this.props;
    return fields
      .filter(field => (field.get('show_in_list', false) || defaultListFields.includes(field.get('field_name', ''))))
      .reduce((acc, field) => acc.set(field.get('field_name'), 1), Immutable.Map({}))
      .toJS();
  };

  getFields = () => {
    const { fields, defaultListFields } = this.props;
    return fields
      .filter(this.filterPlayField)
      .filter(field => (field.get('show_in_list', false) || defaultListFields.includes(field.get('field_name', ''))))
      .map((field) => {
        const fieldname = field.get('field_name');
        switch (fieldname) {
          case 'rates':
            return { id: 'unit_type', parser: this.parserUsegt };
          case 'description':
            return { id: fieldname, title: 'Title', sort: true };
          case 'key':
            return { id: fieldname, title: 'Key', sort: true };
          default: {
            const title = field.get('title', field.get('field_name', ''));
            return { id: fieldname, title: changeCase.sentenceCase(title) };
          }
        }
      })
      .toArray();
  };

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
        filterFields={ProductsList.filterFields}
        tableFields={this.getFields()}
        projectFields={this.getProjectFields()}
        showRevisionBy="key"
        actions={ProductsList.rowActions}
      />
    );
  }

}

const mapStateToProps = (state, props) => ({
  fields: state.settings.getIn(['rates', 'fields']) || undefined,
  isPlaysEnabled: isPlaysEnabledSelector(state, props),
});

export default withRouter(connect(mapStateToProps)(ProductsList));
