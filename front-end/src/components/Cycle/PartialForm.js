import React, { useState } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel, HelpBlock, Label } from 'react-bootstrap';
import isNumber from 'is-number';
import Field from '@/components/Field';


const PartialForm = ({
  item = Immutable.Map(),
  updateField,
}) => {
  const [isPartial, togglePartial] = useState(false);
  const [isInclude, toggleInclude] = useState(true);
  const [includeDisplay, setInclude] = useState('');
  const [excludeDisplay, setExclude] = useState('');

  const includeError = null;
  const excludeError = null;

  const include = item.get('include', []);
  const exclude = item.get('exclude', []);
  const isRerun = item.get('isRerun', false);

  const onTogglePartial = () => {
    togglePartial(!isPartial);
    if (isPartial) {
      updateField('include', []);
      setInclude('');
      updateField('include', []);
      setExclude('');
    } 
  }

  const onChangeInclude = (e) => {
    const value = e.target.value;
    setInclude(value);
    updateField('include', parseInputValue(value));
  };
  const onChangeExclude = (e) => {
    const value = e.target.value;
    setExclude(value);
    updateField('exclude', parseInputValue(value));
  };
  const onSetInclude = () => {
    toggleInclude(true);
    setExclude('');
    updateField('exclude', parseInputValue(''));
  }
  const onSetExclude = () => {
    toggleInclude(false);
    setInclude('');
    updateField('include', parseInputValue(''));
  }

  const parseInputValue = (value) => value
    .trim()
    .split(/\r\n|\r|\n|,/)
    .map(v => v.trim())
    .filter(v => v.length)
    .map(v => isNumber(v) ? parseFloat(v) : v);

  return (
    <Form horizontal>
      {isRerun && (
        <FormGroup className="mb0">
          <Col sm={3} componentClass={ControlLabel}></Col>
          <Col sm={8}>
            <Label bsStyle="danger">Cycle data will be reset (except for confirmed invoices)</Label>
          </Col>
        </FormGroup>
      )}
      <FormGroup>
        <Col sm={3} componentClass={ControlLabel}></Col>
        <Col sm={8} className='mt10'>
          <Field fieldType="checkbox" value={isPartial} onChange={onTogglePartial} label={
            <span>Partial <small>Cycle will run only on selected AIDs</small></span>
          } />
        </Col>
      </FormGroup>

      {isPartial && (
        <FormGroup>
          <Col sm={3} componentClass={ControlLabel}></Col>
          <Col sm={8} className='mt10'>
            <Field fieldType="radio" onChange={onSetInclude} name="type" value="monetary" label="Include" checked={isInclude} className="inline" />
            <Field fieldType="radio" onChange={onSetExclude} name="type" value="monetary" label="Exclude" checked={!isInclude} className="inline ml10" />
          </Col>
        </FormGroup>
      )}
      {isPartial && isInclude && (
        <FormGroup validationState={includeError === null ? null : 'error'}>
          <Col sm={3} componentClass={ControlLabel}>Include AIDs</Col>
          <Col sm={8}>
            <Field fieldType="textarea" onChange={onChangeInclude} value={includeDisplay} editable={isPartial} />
            {includeError !== null && <HelpBlock>{includeError}.</HelpBlock>}
            {include.length > 0 && (
              <Field fieldType="json" className="included-excluded-items" value={include} editable={false} />
            )}
          </Col>
        </FormGroup>
      )}
      {isPartial && !isInclude && (
        <FormGroup validationState={excludeError === null ? null : 'error'}>
          <Col sm={3} componentClass={ControlLabel}>Exclude AIDs</Col>
          <Col sm={8}>
            <Field fieldType="textarea" onChange={onChangeExclude} value={excludeDisplay}/>
            {excludeError !== null && <HelpBlock>{excludeError}.</HelpBlock>}
            {exclude.length > 0 && (
              <Field fieldType="json" className="included-excluded-items" value={exclude} editable={false} />
            )}
          </Col>
        </FormGroup>
      )}
    </Form>
  );
}

PartialForm.propTypes = {
};

PartialForm.defaultProps = {
};


export default PartialForm;