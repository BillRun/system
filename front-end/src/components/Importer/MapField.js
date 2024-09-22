import React from 'react';
import PropTypes from 'prop-types';
import { FormGroup, ControlLabel, Col, Label, InputGroup, HelpBlock, DropdownButton, MenuItem } from 'react-bootstrap';
import Immutable from 'immutable';
import { sentenceCase } from 'change-case';
import Field from '@/components/Field';
import UsageTypesSelector from '../UsageTypes/UsageTypesSelector';
import { getConfig } from '@/common/Util';


const MapField = (props) => {
  const {
    mapFrom,
    mapTo,
    defaultValue,
    options,
    mapResult,
    operation,
    defaultValuesOperation,
    entity,
    multiFieldAction,
  } = props;

  const onChange = (value) => {
    if (value !== '') {
      props.onChange(['map', mapFrom.value], value);
      if (mapFrom.multiple && !multiFieldAction.has(mapFrom.value)) {
        props.onChange(['multiFieldAction', mapFrom.value], 'append');
      }
    } else {
      props.onDelete(['map', mapFrom.value]);
      if (mapFrom.multiple) {
        props.onDelete(['multiFieldAction', mapFrom.value]);
      }
    }
  };

  const onChangeUnit = (unit) => {
    props.onChange(['map', 'usage_type_unit'], unit);
  };

  const onChangeUsaget = (usageType) => {
    props.onChange(['map', 'usage_type_value'], usageType);
  };

  const onSelectMultiFieldAction = (action) => {
    props.onChange(['multiFieldAction', mapFrom.value], action);
    if (action === 'remove') {
      props.onDelete(['map', mapFrom.value]);
    }
  };

  const isInputGroup = (mapFrom.unique || (operation !== 'create' && mapFrom.multiple));

  const getDefaultValueLabel = () => {
    if (defaultValue === true) {
      return 'Yes';
    }
    if (defaultValue === false) {
      return 'No';
    }
    return defaultValue;
  }

  const renderSelectCsvFiled = () => {
    switch (mapFrom.value) {
      case 'usage_type': {
        return (
          <UsageTypesSelector
            usaget={mapResult.get('usage_type_value')}
            unit={mapResult.get('usage_type_unit')}
            onChangeUsaget={onChangeUsaget}
            onChangeUnit={onChangeUnit}
          />
        );
      }
      case 'tariff_category': {
        const seletOptions = (mapFrom.select_options && mapFrom.select_options.length) ?
          mapFrom.select_options.split(',').filter(option => option !== '').map(option => ({
            value: option,
            label: sentenceCase(option),
          }))
          : [];
        return (
          <Field
            fieldType="select"
            value={mapTo}
            onChange={onChange}
            options={seletOptions}
            placeholder={`Select ${mapFrom.label}`}
          />

        );
      }
      default: {
        const isDisabled = (mapFrom.multiple && multiFieldAction.get(mapFrom.value, '') === 'remove');
        return (
          <Field
            fieldType="select"
            allowCreate={true}
            options={options}
            value={mapTo}
            onChange={onChange}
            placeholder="Select CSV field or set default value..."
            addLabelText='Click to set default value "{label}" for all rows'
            disabled={isDisabled}
          />
        );
      }
    }
  };

  const renderMultiFieldActionIcon = (action) => {
    switch (action) {
      case 'append':
        return (<i className="fa fa-plus" />);
      case 'replace':
        return (<i className="fa fa-refresh" />);
      case 'remove':
        return (<i className="fa fa-trash-o danger-red" />);
        default:
          return '';
    }
  }

  const renderMultiFieldActionLabel = (action, withIcon = true) => {
    const icon = withIcon ? <span>{renderMultiFieldActionIcon(action)}&nbsp;</span> : '';
    switch (action) {
      case 'append':
        return (<span>{icon}Append</span>);
      case 'replace':
        return (<span>{icon}Replace</span>);
      case 'remove':
        return (<span>{icon}Remove</span>);
      default:
        return 'Select action...';
    }
  }

  const renderActionItems = () => {
    const actions = ['append', 'replace', 'remove'];
    const disabled = !mapResult.has(mapFrom.value) && multiFieldAction.get(mapFrom.value, false) !== 'remove';
    return actions.map((action) => {
      const isActive = multiFieldAction.get(mapFrom.value, false) === action;
      return (
        <MenuItem eventKey={action} active={isActive} key={`${mapFrom.value}-${action}-action`} disabled={disabled && action !== 'remove'}>
          {renderMultiFieldActionLabel(action)}
        </MenuItem>
      )
    });
  };

  const renderMultiFieldDropDown = () => {
    if (operation === 'create' || !mapFrom.multiple) {
      return null;
    }
    const fieldAction = multiFieldAction.get(mapFrom.value, false);
    const title = (<span className="control-label">{renderMultiFieldActionLabel(fieldAction)}</span>);
    return (
      <DropdownButton
        onSelect={onSelectMultiFieldAction}
        id={`${mapFrom.value}-actions`}
        componentClass={InputGroup.Button}
        title={title}
      >
        {renderActionItems()}
      </DropdownButton>
    );
  }

  return (
    <FormGroup>
      <Col sm={3} componentClass={ControlLabel}>
        {mapFrom.label}
        {mapFrom.mandatory && defaultValue === null && operation === 'create' && (
          <span className="danger-red"> *</span>
        )}
      </Col>
      <Col sm={9}>
        { isInputGroup
          ? (
            <InputGroup>
              {renderSelectCsvFiled()}
              {mapFrom.unique && (
                <InputGroup.Addon>
                  <Label bsStyle="info">Unique field</Label>
                </InputGroup.Addon>
              )}
              {renderMultiFieldDropDown()}
            </InputGroup>
          )
          : renderSelectCsvFiled()
        }
        {typeof mapFrom.help === 'string' && mapFrom.help !== '' && (
          <HelpBlock className="mb0 mt0">{mapFrom.help}</HelpBlock>
        )}
        {defaultValue !== null && defaultValuesOperation.get(operation).includes(entity) && (
          <HelpBlock className="mb0">
            Default value if no value is selected:&nbsp;
            <Label bsStyle="primary" style={{ padding: '1px 6px', fontWeight: 'bold' }} >
              { getDefaultValueLabel() }
            </Label>
          </HelpBlock>
        )}
      </Col>
    </FormGroup>
  );
};

MapField.defaultProps = {
  mapResult: Immutable.Map(),
  mapFrom: '',
  mapTo: '',
  defaultValue: null,
  options: [],
  entity: '',
  operation: '',
  defaultValuesOperation: getConfig(['import', 'default_values_allowed_actions'], Immutable.Map()),
  multiFieldAction: Immutable.Map(),
  onChange: () => {},
  onDelete: () => {},
};

MapField.propTypes = {
  mapResult: PropTypes.instanceOf(Immutable.Map),
  mapFrom: PropTypes.object,
  mapTo: PropTypes.oneOfType([
    PropTypes.number,
    PropTypes.string,
  ]),
  defaultValue: PropTypes.oneOfType([
    PropTypes.number,
    PropTypes.string,
    PropTypes.bool,
  ]),
  options: PropTypes.arrayOf(
    PropTypes.shape({
      value: PropTypes.string,
      label: PropTypes.string,
    }),
  ),
  entity: PropTypes.string,
  operation: PropTypes.string,
  defaultValuesOperation: PropTypes.instanceOf(Immutable.Map),
  multiFieldAction: PropTypes.instanceOf(Immutable.Map),
  onChange: PropTypes.func,
  onDelete: PropTypes.func,
};

export default MapField;
