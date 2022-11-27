import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import List from '@/components/List';
import { CreateButton } from '@/components/Elements';
import { getSettings, updateSetting, saveSettings } from '@/actions/settingsActions';
import { usageTypesDataSelector, propertyTypeSelector } from '@/selectors/settingsSelector';
import UsageTypeForm from '../UsageTypes/UsageTypeForm';

class UsageTypes extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
  };

  static defaultProps = {
    usageTypesData: Immutable.List(),
    propertyTypes: Immutable.List(),
  };

  state = {
    currentItem: null,
    createNew: false,
    index: -1,
  }

  componentWillMount() {
    this.props.dispatch(getSettings(['usage_types', 'property_types']));
  }

  getItemIndex = (item) => {
    const { usageTypesData } = this.props;
    return usageTypesData.indexOf(item);
  }

  onClickEdit = (item) => {
    this.setState({
      currentItem: item,
      createNew: false,
      index: this.getItemIndex(item),
    });
  };

  onCancel = () => {
    this.setState({
      currentItem: null,
      createNew: false,
    });
  }

  handleSave = () => {
    const { index, currentItem } = this.state;
    this.setState({
      currentItem: null,
      createNew: false,
    });
    this.props.dispatch(updateSetting('usage_types', index, currentItem));
    this.props.dispatch(saveSettings('usage_types'));
    this.props.dispatch(getSettings('usage_types'));
  }

  onUpdateItem = (fieldNames, fieldValues) => {
    const { currentItem } = this.state;
    const keys = Array.isArray(fieldNames) ? fieldNames : [fieldNames];
    const values = Array.isArray(fieldValues) ? fieldValues : [fieldValues];
    this.setState({
      currentItem: currentItem.withMutations((itemWithMutations) => {
        keys.forEach((key, index) => itemWithMutations.set(key, values[index]));
      }),
    });
  };

  onClickNew = () => {
    const { usageTypesData } = this.props;
    this.setState({
      currentItem: Immutable.Map(),
      createNew: true,
      index: usageTypesData.size,
    });
  }

  renderList = () => {
    const { usageTypesData } = this.props;
    const fields = this.getListFields();
    const actions = this.getListActions();
    return (
      <List items={usageTypesData} fields={fields} actions={actions} />
    );
  }

  getListFields = () => [
    { id: 'usage_type', title: 'Usage Type' },
    { id: 'label', title: 'Label' },
    { id: 'property_type', title: 'Property Type' },
    { id: 'invoice_uom', title: 'Invoice Unit of Measure' },
    { id: 'input_uom', title: 'Default Unit of Measure' },
  ]

  getListActions = () => [
    { type: 'edit', showIcon: true, helpText: 'Edit', onClick: this.onClickEdit },
  ]

  render() {
    const { propertyTypes } = this.props;
    const { currentItem, createNew } = this.state;

    return (
      <div>
        { this.renderList() }
        <CreateButton onClick={this.onClickNew} label="Add New" />
        {
          currentItem !== null &&
          <UsageTypeForm
            item={currentItem}
            propertyTypes={propertyTypes}
            onUpdateItem={this.onUpdateItem}
            onSave={this.handleSave}
            onCancel={this.onCancel}
            selectUoms
            editBase={createNew}
          />
        }
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  usageTypesData: usageTypesDataSelector(state, props),
  propertyTypes: propertyTypeSelector(state, props),
});

export default connect(mapStateToProps)(UsageTypes);
