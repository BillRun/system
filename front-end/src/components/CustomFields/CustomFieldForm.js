import React from 'react';
import PropTypes from 'prop-types';
import { Map, List } from 'immutable'; // eslint-disable-line no-unused-vars
import { Form, InputGroup, Col, FormGroup, ControlLabel, HelpBlock, Panel } from 'react-bootstrap';
import Field from '../Field';
import { EntityField } from '../Entity';


const CustomFieldForm = ({
  item, onChangeOptions, onChangePlay, onChangeType, onChangeTitle, onChangeFieldName,
  onChangeEntityField,
  disableUnique, disableMandatory, disableFieldType, disabledEditable, disabledDisplay,
  disabledShowInList, disableSearchable, disableMultiple, disableSelectList, disableSelectOptions,
  disableTitle, disableFieldName, disableHelp, disableDescription, disableDefaultValue,
  isErrorTitle, isErrorFieldName,
  fieldTypesOptions, playsOptions,
  fieldType, showPlays, plays,
  checkboxStyle, helpTextStyle,
}) => (
  <Form horizontal>
    <EntityField
      field={Map({ title: 'Key', field_name: 'field_name', mandatory: true })}
      entity={item}
      disabled={disableFieldName}
      onChange={onChangeFieldName}
      error={isErrorFieldName}
    />
    <EntityField
      field={Map({ title: 'Title', field_name: 'title', mandatory: true })}
      entity={item}
      disabled={disableTitle}
      onChange={onChangeTitle}
      error={isErrorTitle}
    />
    {!disableFieldType && (
      <FormGroup>
        <Col sm={3} lg={2} componentClass={ControlLabel}>Field Type</Col>
        <Col sm={8} lg={9}>
          <Field
            fieldType="select"
            options={fieldTypesOptions}
            onChange={onChangeType}
            value={fieldType}
            disabled={disableFieldType}
            clearable={false}
          />
        </Col>
      </FormGroup>
    )}
    {showPlays && (
      <FormGroup>
        <Col sm={3} lg={2} componentClass={ControlLabel}>Play</Col>
        <Col sm={8} lg={9}>
          <Field
            fieldType="select"
            options={playsOptions}
            onChange={onChangePlay}
            value={plays}
            multi={true}
            clearable={true}
          />
        </Col>
      </FormGroup>
    )}
    {!disableDescription && (
      <EntityField
        field={Map({ title: 'Description', field_name: 'description', description: 'Long text will appear in question mark after the field label' })}
        entity={item}
        onChange={onChangeEntityField}
        disabled={disableDescription}
      />
    )}
    {!disableHelp && (
      <EntityField
        field={Map({ title: 'Help Text', field_name: 'help', description: 'Short text will appear below the field' })}
        entity={item}
        onChange={onChangeEntityField}
        disabled={disableHelp}
      />
    )}

    <Panel header="Options">
      {!disableUnique && (
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>Unique</Col>
          <Col sm={8} lg={9} style={checkboxStyle}>
            <Field
              id="unique"
              onChange={onChangeOptions}
              value={item.get('unique', '')}
              fieldType="checkbox"
              disabled={disableUnique}
              className="inline mr10"
            />
          </Col>
        </FormGroup>
      )}
      {(!disableMandatory || (disableMandatory && item.get('unique', false))) && (
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>Mandatory</Col>
          <Col sm={8} lg={9} style={checkboxStyle}>
            <Field
              id="mandatory"
              onChange={onChangeOptions}
              value={item.get('mandatory', '')}
              fieldType="checkbox" disabled={disableMandatory}
              className="inline mr10"
            />
            { disableMandatory && item.get('unique', false) && (
              <small style={helpTextStyle}>Unique field must be mandatory</small>
            )}
          </Col>
        </FormGroup>
      )}
      {!disabledEditable && (
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>Editable</Col>
          <Col sm={8} lg={9} style={checkboxStyle}>
            <Field
              fieldType="checkbox"
              id="editable"
              onChange={onChangeOptions}
              value={item.get('editable', '')}
              disabled={disabledEditable}
              className="inline mr10"
            />
          </Col>
        </FormGroup>
      )}
      {!disabledDisplay && (
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>Display</Col>
          <Col sm={7} style={checkboxStyle}>
            <Field
              fieldType="checkbox"
              id="display"
              onChange={onChangeOptions}
              value={item.get('display', '')}
              disabled={disabledDisplay}
              className="inline mr10"
            />
          </Col>
        </FormGroup>
      )}
      {!disabledShowInList && (
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>Show in list</Col>
          <Col sm={8} lg={9} style={checkboxStyle}>
            <Field
              fieldType="checkbox"
              id="show_in_list"
              onChange={onChangeOptions}
              value={item.get('show_in_list', '')}
              disabled={disabledShowInList}
              className="inline mr10"
            />
          </Col>
        </FormGroup>
      )}
      {!disableSearchable && (
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>Searchable</Col>
          <Col sm={8} lg={9} style={checkboxStyle}>
            <Field
              fieldType="checkbox"
              id="searchable"
              className="inline mr10"
              onChange={onChangeOptions}
              value={item.get('searchable', '')}
              disabled={disableSearchable}
            />
          </Col>
        </FormGroup>
      )}
      {!disableMultiple && (
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>Multiple</Col>
          <Col sm={8} lg={9} style={checkboxStyle}>
            <Field
              id="multiple"
              onChange={onChangeOptions}
              value={item.get('multiple', '')}
              fieldType="checkbox"
              disabled={disableMultiple}
              className="inline mr10"
            />
          </Col>
        </FormGroup>
      )}
      { (!disableSelectList || !disableSelectOptions) && (
        <FormGroup>
          <Col sm={3} lg={2} componentClass={ControlLabel}>Select list</Col>
          <Col sm={8} lg={9}>
            <InputGroup>
              <InputGroup.Addon>
                <Field
                  id="select_list"
                  onChange={onChangeOptions}
                  value={item.get('select_list', '')}
                  fieldType="checkbox"
                  disabled={disableSelectList}
                />
              </InputGroup.Addon>
              <Field
                id="select_options"
                onChange={onChangeOptions}
                value={item.get('select_options', '')}
                disabled={disableSelectOptions}
              />
            </InputGroup>
            <HelpBlock style={{ marginLeft: 40 }}>
              Select Options <small>(comma-separated list)</small>
            </HelpBlock>
          </Col>
        </FormGroup>
      )}
    </Panel>
    {(!disableDefaultValue || (disableDefaultValue && item.get('unique', false))) && (
      <Panel header="Preview and Default Value">
        <EntityField
          field={item.set('field_name', 'default_value')}
          entity={item}
          onChange={onChangeEntityField}
          disabled={disableDefaultValue || item.get('unique', false)}
        />
        {item.get('unique', false) && (
          <HelpBlock className="text-center">
            Default value can&apos;t be set for unique field
          </HelpBlock>
        )}
      </Panel>
    )}
  </Form>
);


CustomFieldForm.propTypes = {
  item: PropTypes.instanceOf(Map),
  fieldTypeLabel: PropTypes.string,

  isErrorTitle: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.bool,
  ]),
  isErrorFieldName: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.bool,
  ]),

  disableUnique: PropTypes.bool,
  disableMandatory: PropTypes.bool,
  disableFieldType: PropTypes.bool,
  disabledEditable: PropTypes.bool,
  disabledDisplay: PropTypes.bool,
  disabledShowInList: PropTypes.bool,
  disableSearchable: PropTypes.bool,
  disableMultiple: PropTypes.bool,
  disableSelectList: PropTypes.bool,
  disableSelectOptions: PropTypes.bool,
  disableTitle: PropTypes.bool,
  disableFieldName: PropTypes.bool,
  disableHelp: PropTypes.bool,
  disableDescription: PropTypes.bool,
  disableDefaultValue: PropTypes.bool,

  fieldTypesOptions: PropTypes.array,
  fieldType: PropTypes.string,
  showPlays: PropTypes.bool,
  plays: PropTypes.string,
  playsOptions: PropTypes.array,
  checkboxStyle: PropTypes.object,
  helpTextStyle: PropTypes.object,

  onChangeOptions: PropTypes.func.isRequired,
  onChangePlay: PropTypes.func.isRequired,
  onChangeType: PropTypes.func.isRequired,
  onChangeTitle: PropTypes.func.isRequired,
  onChangeFieldName: PropTypes.func.isRequired,
  onChangeEntityField: PropTypes.func.isRequired,
};


CustomFieldForm.defaultProps = {
  item: Map(),
  fieldTypeLabel: '',
  isErrorTitle: false,
  isErrorFieldName: false,
  disableUnique: false,
  disableMandatory: false,
  disableFieldType: false,
  disabledEditable: false,
  disabledDisplay: false,
  disabledShowInList: false,
  disableSearchable: false,
  disableMultiple: false,
  disableSelectList: false,
  disableSelectOptions: false,
  disableTitle: false,
  disableFieldName: false,
  disableHelp: false,
  disableDescription: false,
  disableDefaultValue: false,
  fieldTypesOptions: [],
  fieldType: '',
  showPlays: false,
  plays: '',
  playsOptions: [],
  checkboxStyle: {},
  helpTextStyle: {},
};


export default CustomFieldForm;
