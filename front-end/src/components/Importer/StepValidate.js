import React, { useState } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { FormGroup, ControlLabel, Col, Panel, InputGroup } from 'react-bootstrap';
// import ReactJson from 'react-json-view';
import { sentenceCase } from 'change-case';
import { Actions } from '@/components/Elements';
import Field from '@/components/Field';

import {
  getFieldName,
  getFieldNameType,
} from '@/common/Util';


const StepValidate = ({ fields, rows, selectedMapper, defaultMappedName, saveMapper, removeMapper, entity }) => {
  const [mapperName, setMapperName] = useState(['', null].includes(selectedMapper) ? defaultMappedName : selectedMapper);

  const row = rows.get(0, Immutable.Map());
  const updaters = row.filter((value, fieldName) => ['__UPDATER__', 'effective_date'].includes(fieldName));
  const mapper = row.filter((value, fieldName) => !['__LINKER__', '__UPDATER__', '__MULTI_FIELD_ACTION__', '__CSVROW__', '__ERRORS__', 'effective_date'].includes(fieldName));
  const updateMapperName = (e) => {
    const { value } = e.target;
    setMapperName(value);
  }

  const onRemoveMapper = (label) => {
    setMapperName('');
    removeMapper(label);
  }

  const onSaveMapper = (label) => {
    const newLabel = ['', null].includes(label) ? defaultMappedName : label;
    saveMapper(newLabel);
  }

  const mapperActions = [{
    type: 'edit',
    helpText: 'Update mapper with new mapping or name',
    onClick: onSaveMapper,
    show: selectedMapper !== null,
    actionSize: 'xsmall',
    actionStyle: 'primary',
  }, {
    type: 'add',
    helpText: 'Save Mapper for latter use',
    onClick: onSaveMapper,
    show: selectedMapper === null,
    actionSize: 'xsmall',
    actionStyle: 'primary',
   }, {
     type: 'remove',
     helpText: 'Remove Mapper',
     onClick: onRemoveMapper,
     show: selectedMapper !== null,
     actionSize: 'xsmall',
     actionStyle: "danger",
 }];

  const renderUpdaters = (updater, key) => {
    const fieldKey = (key === 'effective_date') ? 'effective_date' : updater.get('field', '');
    const fieldValue = (key === 'effective_date') ? updater : updater.get('value', '');
    const curField = fields.find(field => field.value === fieldKey);
    return (
      <FormGroup key={key}>
        <Col sm={3} componentClass={ControlLabel}>
          { curField ? curField.label : fieldKey }
        </Col>
        <Col sm={9}>
          <Field value={fieldValue} disabled={true} />
        </Col>
      </FormGroup>
    );
  };

  const renderLinker = (linker, inedx) => {
    const curField = fields.find(field => field.value === linker.get('field', ''));
    return (
      <Panel header="Linker" className="mb0" key={inedx}>
        <FormGroup>
          <Col sm={3} componentClass={ControlLabel}>
            { curField ? curField.label : linker.get('field', '') }
          </Col>
          <Col sm={9}>
            <Field value={linker.get('value', '')} disabled={true} />
          </Col>
        </FormGroup>
      </Panel>
    );
  };

  const renderErrors = (errors, index) => (errors.isEmpty() ? null : (
    <Panel header="Errors" key={index}>
      <FormGroup>
        <Col sm={12}>
          { errors
            .sortBy((messages, index) => parseInt(index))
            .map((messages, index) => (
              <dl className="mb0" key={index}>
                <dt>row { index }</dt>
                { messages.map((message, messageIdx) => (
                  <dd key={`${index}-${messageIdx}`}>- {message}</dd>
                )) }
              </dl>
            ))
            .toList()
            .toArray()
          }
        </Col>
      </FormGroup>
    </Panel>
  ));

  const renderRow = (value, fieldName) => {
    const curField = fields.find(field => field.value === fieldName);
    const valueType = typeof value;
    const displayValue = ['number', 'string', 'boolean'].includes(valueType) ? value : 'complex value cannot be displayed in preview';
    return (
      <FormGroup key={`row_${fieldName}`}>
        <Col sm={3} componentClass={ControlLabel}>
          { curField
            ? curField.label
            : getFieldName(fieldName, getFieldNameType(entity), sentenceCase(fieldName))
          }
        </Col>
        <Col sm={9}>
          <Field value={displayValue} disabled={true} />
        </Col>
      </FormGroup>
    );
  };

  // { [].includes(entity) && renderRows(row
  //     .filter((value, fieldName) => !['__LINKER__', '__UPDATER__', '__MULTI_FIELD_ACTION__', '__CSVROW__', '__ERRORS__', 'effective_date'].includes(fieldName))
  //   )
  // }
  // const renderRows = data => (
  //   <Panel header={`Data for ${row.getIn(['__UPDATER__', 'value'], row.get('__CSVROW__', []).join(', '))}`} className="mb0">
  //     <FormGroup>
  //       <Col sm={12}>
  //         <ReactJson
  //           src={data.toJS()}
  //           name={false}
  //           displayDataTypes={false}
  //           displayObjectSize={false}
  //           enableClipboard={false}
  //         />
  //       </Col>
  //     </FormGroup>
  //   </Panel>
  // );
  return (
    <div className="StepValidate">
      <div>
        <h4 className="pull-left">Example Import</h4>
        <div className="pull-right">
          <InputGroup style={{ width: 300, marginTop: 7 }}>
            <InputGroup.Addon style={{ fontSize: 13, padding: '6px 9px' }}>Mapper</InputGroup.Addon>
            <input
              type="text"
              className="form-control"
              value={mapperName}
              onChange={updateMapperName}
              placeholder="Enter mapper name"
              style={{ height: 28, fontSize: 13 }}
            />
            <InputGroup.Button>
              <Actions actions={mapperActions} data={mapperName} type="group"/>
            </InputGroup.Button>
          </InputGroup>
        </div>
        <div className="clearfix" />
      </div>
      <hr className="mt0 mb0" />
      <div className="row-fields scrollbox">
        { rows
          .map(rowFields => rowFields
            .filter((value, fieldName) => fieldName === '__ERRORS__')
            .map(renderErrors)
            .toList()
            .toArray()
          )
        }
        { mapper.size > 0 && (
          <Panel header="Map">
            {mapper.map(renderRow).toList().toArray()}
          </Panel>
        )}
        { row
          .filter((value, fieldName) => fieldName === '__LINKER__')
          .map(renderLinker)
          .toList()
          .toArray()
        }
        { updaters.size > 0 && (
          <Panel header="Updater" className="mb0">
            { updaters
              .map(renderUpdaters)
              .toList()
              .toArray()
            }
          </Panel>
        )}
      </div>
    </div>
  );
};

StepValidate.propTypes = {
  fields: PropTypes.array,
  entity: PropTypes.string,
  selectedMapper: PropTypes.string,
  defaultMappedName: PropTypes.string,
  rows: PropTypes.instanceOf(Immutable.List),
  saveMapper: PropTypes.func,
  removeMapper: PropTypes.func,
};

StepValidate.defaultProps = {
  fields: [],
  entity: '',
  selectedMapper: '',
  defaultMappedName: '',
  rows: Immutable.List(),
  saveMapper: () => {},
  removeMapper: () => {},
};

export default StepValidate;
