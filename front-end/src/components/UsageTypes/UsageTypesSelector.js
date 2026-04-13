import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { connect } from 'react-redux';
import { Button, Col, InputGroup } from 'react-bootstrap';
import Field from '@/components/Field';
import { getSettings, updateSetting, saveSettings } from '@/actions/settingsActions';
import { usageTypesDataSelector, propertyTypeSelector } from '@/selectors/settingsSelector';
import UsageTypeForm from '../UsageTypes/UsageTypeForm';

class UsageTypesSelector extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    usaget: PropTypes.string,
    unit: PropTypes.string,
    onChangeUsaget: PropTypes.func,
    onChangeUnit: PropTypes.func,
    editable: PropTypes.bool,
    enabled: PropTypes.bool,
    showUnits: PropTypes.bool,
    showAddButton: PropTypes.bool,
    usagetFilter: PropTypes.func,
    unitFilter: PropTypes.func,
    showSelectTypes: PropTypes.bool,
    showDisplayUnits: PropTypes.bool,
  };

  static defaultProps = {
    usageTypesData: Immutable.List(),
    propertyTypes: Immutable.List(),
    usaget: '',
    unit: '',
    onChangeUsaget: () => {},
    onChangeUnit: () => {},
    editable: true,
    enabled: true,
    showUnits: true,
    showAddButton: true,
    usagetFilter: () => true,
    unitFilter: () => true,
    showSelectTypes: true,
    showDisplayUnits: false,
  };

  state = {
    currentItem: null,
  }

  componentWillMount() {
    const { usageTypesData, propertyTypes } = this.props;
    const settingToFetch = [];
    if (usageTypesData.isEmpty()) {
      settingToFetch.push('usage_types');
    }
    if (propertyTypes.isEmpty()) {
      settingToFetch.push('property_types');
    }
    if (settingToFetch.length > 0) {
      this.props.dispatch(getSettings(['usage_types', 'property_types']));
    }
  }

  componentWillReceiveProps(nextProps) {
    if (this.props.usaget !== nextProps.usaget) {
      this.onChangeUnit(nextProps.usaget)('');
    }
  }

  onChangeUsaget = (usaget) => {
    this.props.onChangeUsaget(usaget);
  }

  onChangeUnit = usaget => (unit) => {
    this.props.onChangeUnit(unit, usaget);
  }

  onCancelNewUsageType = () => {
    this.setState({ currentItem: null });
  }

  onSaveNewUsageType = () => {
    const { usageTypesData } = this.props;
    const { currentItem } = this.state;
    this.setState({ currentItem: null });
    this.props.dispatch(updateSetting('usage_types', usageTypesData.size, currentItem));
    this.props.dispatch(saveSettings('usage_types'));
    this.props.onChangeUsaget(currentItem.get('usage_type', ''));
  }

  onClickNewUsageType = () => {
    this.setState({ currentItem: Immutable.Map() });
  }

  onUpdateUsageType = (fieldNames, fieldValues) => {
    const { currentItem } = this.state;
    const keys = Array.isArray(fieldNames) ? fieldNames : [fieldNames];
    const values = Array.isArray(fieldValues) ? fieldValues : [fieldValues];
    this.setState({
      currentItem: currentItem.withMutations((itemWithMutations) => {
        keys.forEach((key, index) => itemWithMutations.set(key, values[index]));
      }),
    });
  }

  getAvailableUsageTypes = () => {
    const { usageTypesData, usagetFilter } = this.props;
    return usageTypesData
      .filter(usagetFilter)
      .map(usaget => ({
        value: usaget.get('usage_type', ''),
        label: usaget.get('label', ''),
      }))
      .toJS();
  }

  getAvailableUnits = () => {
    const { propertyTypes, usageTypesData, usaget, unitFilter, showDisplayUnits } = this.props;
    const selectedUsaget = usageTypesData.find(usageType => usageType.get('usage_type', '') === usaget) || Immutable.Map();
    const uom = (propertyTypes.find(prop => prop.get('type', '') === selectedUsaget.get('property_type', '')) || Immutable.Map()).get('uom', Immutable.List());
    return uom
      .filter(unit => unit.get('unit', false) !== false || (showDisplayUnits && unit.get('convertFunction', false) !== false))
      .filter(unitFilter)
      .map(unit => ({ value: unit.get('name', ''), label: unit.get('label', '') }))
      .toArray();
  }

  renderUsageTypeSelect = () => {
    const { enabled, editable, usaget, showSelectTypes } = this.props;
    return showSelectTypes && (
      <Field
        fieldType="select"
        options={this.getAvailableUsageTypes()}
        value={usaget}
        disabled={!enabled}
        editable={editable}
        onChange={this.onChangeUsaget}
      />
    );
  }

  renderAddUsageTypeButton = () => (
    <Button className="btn-primary" onClick={this.onClickNewUsageType} disabled={!this.props.enabled}>
      <i className="fa fa-plus" />&nbsp;
    </Button>
  );

  renderUnitSelect = () => {
    const { enabled, editable, unit, usaget } = this.props;
    return (
      <Field
        fieldType="select"
        onChange={this.onChangeUnit(usaget)}
        value={unit}
        options={this.getAvailableUnits()}
        disabled={!enabled || !usaget}
        editable={editable}
      />);
  }

  renderNewUsageTypeForm = () => {
    const { propertyTypes } = this.props;
    const { currentItem } = this.state;
    return currentItem && (
      <UsageTypeForm
        item={currentItem}
        propertyTypes={propertyTypes}
        onUpdateItem={this.onUpdateUsageType}
        onSave={this.onSaveNewUsageType}
        onCancel={this.onCancelNewUsageType}
      />
    );
  };

  render() {
    const { showUnits, showAddButton, showSelectTypes, editable } = this.props;
    if (showSelectTypes) {
      return (
        <span>
          <Col sm={7} className="pr0 pl0">
              { showAddButton && editable
                ? (
                  <InputGroup>
                    <InputGroup.Button>
                      {this.renderAddUsageTypeButton()}
                    </InputGroup.Button>
                    {this.renderUsageTypeSelect()}
                  </InputGroup>
                )
                : this.renderUsageTypeSelect()
              }
          </Col>
          <Col sm={5} className="pr0">
            {showUnits && this.renderUnitSelect()}
          </Col>
          {this.renderNewUsageTypeForm()}
        </span>
      );
    }
    return (
      <span>
        {showUnits && this.renderUnitSelect()}
        {this.renderNewUsageTypeForm()}
      </span>
    );
  }
}

const mapStateToProps = (state, props) => ({
  usageTypesData: usageTypesDataSelector(state, props),
  propertyTypes: propertyTypeSelector(state, props),
});

export default connect(mapStateToProps)(UsageTypesSelector);
