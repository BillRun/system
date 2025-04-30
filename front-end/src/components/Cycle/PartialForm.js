import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel, HelpBlock} from 'react-bootstrap';
import Field from '@/components/Field';


const PartialForm = (props) => {
  const { item = Immutable.Map(), updateField } = props;

  const includeError = null;
  const excludeError = null;

  const include = item.get('include', '');
  const exclude = item.get('exclude', '');

  const onChangeInclude = (e) => {
    const value = e.target.value;
    updateField('include', value);
  };
  const onChangeExclude = (e) => {
    const value = e.target.value;
    updateField('exclude', value);
  };

  const parseInputValue = (value) => value
    .trim()
    .split(/\r\n|\r|\n|,/)
    .map(v => v.trim())
    .filter(v => v.length);

  return (
    <Form horizontal>
      <FormGroup validationState={includeError === null ? null : 'error'}>
        <Col sm={3} componentClass={ControlLabel}>Include AIDs</Col>
        <Col sm={9}>
          <Field fieldType="textarea" onChange={onChangeInclude} value={include}/>
          {includeError !== null && <HelpBlock>{includeError}.</HelpBlock>}
          <code style={{wordWrap: 'break-word'}}>{JSON.stringify(parseInputValue(include))}</code>
        </Col>
      </FormGroup>
      <hr />
      <FormGroup validationState={excludeError === null ? null : 'error'}>
        <Col sm={3} componentClass={ControlLabel}>Exclude AIDs</Col>
        <Col sm={9}>
          <Field fieldType="textarea" onChange={onChangeExclude} value={exclude}/>
          {excludeError !== null && <HelpBlock>{excludeError}.</HelpBlock>}
          <code style={{wordWrap: 'break-word'}}>{JSON.stringify(parseInputValue(exclude))}</code>
        </Col>
      </FormGroup>
    </Form>
  );
}

PartialForm.propTypes = {
  onChange: PropTypes.func.isRequired,
};

PartialForm.defaultProps = {
};


export default PartialForm;