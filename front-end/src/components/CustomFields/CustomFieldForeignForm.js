import React from 'react';
import PropTypes from 'prop-types';
import { Map, List } from 'immutable';
import { Form, Panel, Col, FormGroup } from 'react-bootstrap';
import CustomFieldForeignCondition from './CustomFieldForeignCondition';
import { EntityField } from '@/components/Entity';
import { CreateButton } from '@/components/Elements';


const CustomFieldForeignForm = ({
  item, mode,
  onChangeTitle, isErrorTitle,
  onChangeEntity, isErrorForeigEntity, foreignEntities,
  onChangeField, isErrorForeignField, foreignFields,
  onChangeTranslateType, isErrorForeignTranslateType, translateTypes,
  onChangeTranslateFormat, translateTypeFormats, foreignFieldsConditionsOperators,
  onAddCondition, onUpdateCondition, onRemoveCondition, isErrorConditions,
}) => (
  <Form horizontal>
    <EntityField
      field={Map({ title: 'Title', field_name: 'title', mandatory: true })}
      entity={item}
      onChange={onChangeTitle}
      error={isErrorTitle}
    />

    <EntityField
      field={Map({
        title: 'Entity',
        field_name: 'foreign.entity',
        mandatory: true,
        select_list: true,
        select_options: foreignEntities,
      })}
      entity={item}
      onChange={onChangeEntity}
      disabled={mode === 'edit'}
      error={isErrorForeigEntity}
    />

    <EntityField
      field={Map({
        title: 'Entity Field',
        field_name: 'foreign.field',
        mandatory: true,
        select_list: true,
        select_options: foreignFields,
      })}
      entity={item}
      onChange={onChangeField}
      disabled={item.getIn(['foreign', 'entity'], '') === '' || mode === 'edit'}
      error={isErrorForeignField}
    />

    <Panel header="Translate">
      <EntityField
        field={Map({
          title: 'Type',
          field_name: 'foreign.translate.type',
          select_list: true,
          select_options: translateTypes,
        })}
        entity={item}
        onChange={onChangeTranslateType}
        error={isErrorForeignTranslateType}
      />
      {translateTypeFormats.length !== 0 && (
        <EntityField
          field={Map({
            title: 'Format',
            field_name: 'foreign.translate.format',
            select_list: true,
            select_options: translateTypeFormats,
          })}
          entity={item}
          onChange={onChangeTranslateFormat}
        />
      )}
    </Panel>

    <Panel header="Conditions">
      <div className="fraud-event-conditions">
        {!item.get('conditions', List()).isEmpty() && (
        <Col sm={12} className="form-inner-edit-rows">
          <FormGroup className="form-inner-edit-row">
            <Col sm={4} xsHidden><label htmlFor="field_field">Field</label></Col>
            <Col sm={2} xsHidden><label htmlFor="operator_field">Operator</label></Col>
            <Col sm={4} xsHidden><label htmlFor="value_field">Value</label></Col>
          </FormGroup>
        </Col>
        )}
        <Col sm={12}>
          {item.get('conditions', List()).isEmpty() && (
            <small>No conditions found</small>
          )}
          {item.get('conditions', List()).map((condition, index) => (
            <CustomFieldForeignCondition
              key={index}
              condition={condition}
              index={index}
              conditionsFields={foreignFields}
              onUpdate={onUpdateCondition}
              onRemove={onRemoveCondition}
              conditionsOperatorsSelectOptions={foreignFieldsConditionsOperators}
              error={isErrorConditions.get(`${index}`, '')}
            />
          ))}
        </Col>
        <Col sm={12} className="pl0 pr0">
          <CreateButton
            onClick={onAddCondition}
            label="Add Condition"
            disabled={item.getIn(['foreign', 'entity'], '') === ''}
          />
        </Col>
      </div>
    </Panel>

  </Form>
);

CustomFieldForeignForm.propTypes = {
  item: PropTypes.instanceOf(Map),
  mode: PropTypes.string,
  foreignEntities: PropTypes.array,
  foreignFields: PropTypes.array,
  translateTypes: PropTypes.array,
  translateTypeFormats: PropTypes.array,
  foreignFieldsConditionsOperators: PropTypes.array,
  isErrorTitle: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.bool,
  ]),
  isErrorForeigEntity: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.bool,
  ]),
  isErrorForeignField: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.bool,
  ]),
  isErrorForeignTranslateType: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.bool,
  ]),
  isErrorConditions: PropTypes.instanceOf(Map),
  onChangeTitle: PropTypes.func.isRequired,
  onChangeEntity: PropTypes.func.isRequired,
  onChangeField: PropTypes.func.isRequired,
  onChangeTranslateType: PropTypes.func.isRequired,
  onChangeTranslateFormat: PropTypes.func.isRequired,
  onAddCondition: PropTypes.func.isRequired,
  onUpdateCondition: PropTypes.func.isRequired,
  onRemoveCondition: PropTypes.func.isRequired,
};


CustomFieldForeignForm.defaultProps = {
  item: Map(),
  mode: 'create',
  foreignEntities: [],
  foreignFields: [],
  translateTypes: [],
  translateTypeFormats: [],
  foreignFieldsConditionsOperators: [],
  isErrorTitle: false,
  isErrorForeigEntity: false,
  isErrorForeignField: false,
  isErrorForeignTranslateType: false,
  isErrorConditions: Map(),
};


export default CustomFieldForeignForm;
