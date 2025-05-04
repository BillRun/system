import React, { useState } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel, HelpBlock} from 'react-bootstrap';
import isNumber from 'is-number';
import Field from '@/components/Field';


const PartialForm = ({
  item = Immutable.Map(),
  updateField,
}) => {
  const [isPartial, togglePartial] = useState(false);
  const [includeDisplay, setInclude] = useState('');
  const [excludeDisplay, setExclude] = useState('');

  const includeError = null;
  const excludeError = null;

  const include = item.get('include', '');
  const exclude = item.get('exclude', '');

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
    setExclude( value);
    updateField('exclude', parseInputValue(value));
  };

  const parseInputValue = (value) => value
    .trim()
    .split(/\r\n|\r|\n|,/)
    .map(v => v.trim())
    .filter(v => v.length)
    .map(v => isNumber(v) ? parseFloat(v) : v);

  return (
    <Form horizontal>
      <FormGroup>
        <Col sm={3} componentClass={ControlLabel}></Col>
        <Col sm={8} className='mt10'>
          <Field fieldType="checkbox" value={isPartial} onChange={onTogglePartial} label="Partial | Cycle will run only on selected AIDs" />
        </Col>
      </FormGroup>

      {isPartial && (<>
        <FormGroup validationState={includeError === null ? null : 'error'}>
          <Col sm={3} componentClass={ControlLabel}>Include AIDs</Col>
          <Col sm={8}>
            <Field fieldType="textarea" onChange={onChangeInclude} value={includeDisplay} editable={isPartial} />
            {includeError !== null && <HelpBlock>{includeError}.</HelpBlock>}
            <code style={{wordWrap: 'break-word'}}>{JSON.stringify(include)}</code>
          </Col>
        </FormGroup>

        <FormGroup>
          <Col sm={3}></Col>
          <Col sm={8}><hr /></Col>
        </FormGroup>

        <FormGroup validationState={excludeError === null ? null : 'error'}>
          <Col sm={3} componentClass={ControlLabel}>Exclude AIDs</Col>
          <Col sm={8}>
            <Field fieldType="textarea" onChange={onChangeExclude} value={excludeDisplay}/>
            {excludeError !== null && <HelpBlock>{excludeError}.</HelpBlock>}
            <code style={{wordWrap: 'break-word'}}>{JSON.stringify(exclude)}</code>
          </Col>
        </FormGroup>
      </>)}
    </Form>
  );
}

PartialForm.propTypes = {
  onChange: PropTypes.func.isRequired,
};

PartialForm.defaultProps = {
};


export default PartialForm;