import React, { useState } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, Col } from 'react-bootstrap';
import { ControlLabel, FormGroup, HelpBlock, Label } from '@/common/BootstrapCompat';
import Field from '@/components/Field';
import { getDateToDisplay } from './CycleUtil';
import { parseIncludeExcludeIdsListValue } from '@/common/Util';

const PartialConfirmForm = ({ item = Immutable.Map(),updateField }) => {

  const [isPartial, togglePartial] = useState(false);
  const [isInclude, toggleInclude] = useState(true);
  const [includeDisplay, setInclude] = useState('');
  const [excludeDisplay, setExclude] = useState('');

  const includeError = null;
  const excludeError = null;

  const include = item.get('include', []);
  const exclude = item.get('exclude', []);
  const selectedCycle = item.get('selectedCycle', '');
  const invoicesNum = item.get('invoicesNum', 0);

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
    updateField('include', parseIncludeExcludeIdsListValue(value));
  };
  const onChangeExclude = (e) => {
    const value = e.target.value;
    setExclude(value);
    updateField('exclude', parseIncludeExcludeIdsListValue(value));
  };
  const onSetInclude = () => {
    toggleInclude(true);
    setExclude('');
    updateField('exclude', []);
  }
  const onSetExclude = () => {
    toggleInclude(false);
    setInclude('');
    updateField('include', []);
  }

  return (
    <Form>
      <FormGroup>
        <Col sm={5} as={ControlLabel}></Col>
        <Col sm={6}><Label variant="danger">This action is irreversible</Label></Col>
      </FormGroup>
      <FormGroup>
        <Col sm={5} as={ControlLabel}></Col>
        <Col sm={6} className='mt10'>
          <Field fieldType="checkbox" value={isPartial} onChange={onTogglePartial} label={
            <span>Partial Confirmation</span>
          } />
        </Col>
      </FormGroup>
      {!isPartial && (
        <FormGroup>
          <Col sm={5} as={ControlLabel} className="pt5"><strong>All the invoices for the cycle:</strong></Col>
          <Col sm={6}>{getDateToDisplay(selectedCycle, 'start_date')} - {getDateToDisplay(selectedCycle, 'end_date')}</Col>
        </FormGroup>
      )}
      {!isPartial && (
        <FormGroup>
          <Col sm={5} as={ControlLabel} className="pt5"><strong>Invoices will be confirmed after this action:</strong></Col>
          <Col sm={6}>{invoicesNum}</Col>
        </FormGroup>
      )}
      {isPartial && (
        <FormGroup validationState={excludeError === null ? null : 'error'}>
          <Col sm={5} as={ControlLabel}></Col>
          <Col sm={6} className='mt10'>
            <Field fieldType="radio" onChange={onSetInclude} name="type" value="monetary" label="Include" checked={isInclude} className="inline" />
            <Field fieldType="radio" onChange={onSetExclude} name="type" value="monetary" label="Exclude" checked={!isInclude} className="inline ml10" />
          </Col>
        </FormGroup>
      )}
      {isPartial && isInclude && (
        <FormGroup validationState={includeError === null ? null : 'error'}>
          <Col sm={5} as={ControlLabel}>
            Include Customer IDs
            <HelpBlock><small>Comma \ new line<br />separated numbers</small></HelpBlock>
          </Col>
          <Col sm={6}>
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
          <Col sm={5} as={ControlLabel}>
            Exclude Customer IDs
            <HelpBlock><small>Comma \ new line<br />separated numbers</small></HelpBlock>
          </Col>
          <Col sm={6}>
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

PartialConfirmForm.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
  updateField: PropTypes.func,
};

export default PartialConfirmForm;