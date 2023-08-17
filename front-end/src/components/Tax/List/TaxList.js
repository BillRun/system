import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import changeCase from 'change-case';
import { LoadingItemPlaceholder } from '@/components/Elements';
import EntityList from '../../EntityList';
import Immutable from 'immutable';
import {
  getConfig,
} from '@/common/Util';
import {
  getSettings,
} from '@/actions/settingsActions';
import { isPlaysEnabledSelector } from '@/selectors/settingsSelector';

class TaxList extends Component {

  static propTypes = {
    defaultListFields: PropTypes.arrayOf(PropTypes.string),
    fields: PropTypes.instanceOf(Immutable.List),
    isPlaysEnabled: PropTypes.bool,
  };

  static defaultProps = {
    defaultListFields: ['key', 'description', 'rate'],
    fields: null,
    isPlaysEnabled: false,
  };

  componentWillMount() {
    this.props.dispatch(getSettings('taxes.fields'));
  }

  getFilterFields = () => [
    { id: 'description', placeholder: 'Title' },
    { id: 'key', placeholder: 'Key' },
  ];

  filterPlayField = (field) => {
    const { isPlaysEnabled } = this.props;
    if (field.get('field_name', '') !== 'play') {
      return true;
    }
    return isPlaysEnabled;
  }

  getFields = () => {
    const { fields, defaultListFields } = this.props;
    return fields
      .filter(this.filterPlayField)
      .filter(field => (field.get('show_in_list', false) || defaultListFields.includes(field.get('field_name', ''))))
      .map((field) => {
        const fieldname = field.get('field_name');
        switch (fieldname) {
          case 'description':
            return { id: fieldname, title: 'Title', sort: true };
          case 'rate':
            return { id: fieldname, title: 'Rate', sort: true, type: 'percentage' };
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

  getProjectFields = () => {
    const { fields, defaultListFields } = this.props;
    return fields
      .filter(field => (field.get('show_in_list', false) || defaultListFields.includes(field.get('field_name', ''))))
      .reduce((acc, field) => acc.set(field.get('field_name'), 1), Immutable.Map({}))
      .toJS();
  };

  getActions = () => [
    { type: 'edit' },
  ];

  render () {
    const { fields } = this.props;
    if (fields === null) {
      return (<LoadingItemPlaceholder />);
    }
    return (
      <EntityList
        itemsType={getConfig(['systemItems', 'tax', 'itemsType'], '')}
        itemType={getConfig(['systemItems', 'tax', 'itemType'], '')}
        filterFields={this.getFilterFields()}
        tableFields={this.getFields()}
        projectFields={this.getProjectFields()}
        showRevisionBy="key"
        actions={this.getActions()}
      />
    );
  }
}



const mapStateToProps = (state, props) => ({
  fields: state.settings.getIn(['taxes', 'fields']) || undefined,
  isPlaysEnabled: isPlaysEnabledSelector(state, props),
});

export default connect(mapStateToProps)(TaxList);
