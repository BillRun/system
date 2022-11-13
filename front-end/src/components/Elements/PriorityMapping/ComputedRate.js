import React, { useState } from 'react';
import PropTypes from 'prop-types';
import { Map } from 'immutable';
import { Form, FormGroup, Col, ControlLabel } from 'react-bootstrap';
import Help from '@/components/Help';
import Field from '@/components/Field';
import { MappingRulesDescription } from '@/language/FieldDescriptions';
import { getConfig, parseConfigSelectOptions } from '@/common/Util';
import { ModalWrapper } from '@/components/Elements';


const ComputedRate = ({
  item,
  conditionFieldsOptions,
  valueWhenOptions,
  onSaveComputedLineKey,
  onHideComputedLineKey,
}) => {
  const [ localItem, updateLocalItem] = useState(item);
  const isSingleValueOperator = ['$exists', '$existsFalse', '$isTrue', '$isFalse'].includes(localItem.get('operator', ''));

  if (!localItem) {
    return null;
  }

  const onChangeHardCodedValue = path => (e) => {
    const { value } = e.target;
    updateLocalItem(localItem.setIn(path, value));
  }

  const onChangeComputedMustMet = (e) => {
    const { value } = e.target;
    const newLocalItem = localItem.withMutations((computedLineKeyWithMutations) => {
      computedLineKeyWithMutations.set('must_met', value);
      if (value) {
        computedLineKeyWithMutations.setIn(['projection', 'on_false'], Map());
      }
    });
    updateLocalItem(newLocalItem);
  }

  const onChangeComputedLineKey = path => (value) => {
    const newLocalItem = localItem.withMutations((computedLineKeyWithMutations) => {
      computedLineKeyWithMutations.setIn(path, value);
      const key = path[1];
      if (path[0] === 'operator') {
        const changeFromRegex = localItem.get('operator', '') === '$regex' && value !== '$regex';
        const changeToRegex = localItem.get('operator', '') !== '$regex' && value === '$regex';
        if (changeFromRegex || changeToRegex || isSingleValueOperator) {
          computedLineKeyWithMutations.deleteIn(['line_keys', 1]);
          computedLineKeyWithMutations.deleteIn(['line_keys', 0, 'regex']);
        }
      }
      if (value === 'hard_coded') {
        computedLineKeyWithMutations.deleteIn(['projection', key, 'value']);
      } else if (value === 'condition_result') {
        computedLineKeyWithMutations.deleteIn(['projection', key, 'value']);
        computedLineKeyWithMutations.deleteIn(['projection', key, 'regex']);
      }
    });
    updateLocalItem(newLocalItem);
  }

  const onChangeComputedLineKeyType = (e) => {
    const { value } = e.target;
    const newLocalItem = localItem.withMutations((localItemWithMutations) => {
      localItemWithMutations.set('type', value);
      if (value === 'regex') {
        localItemWithMutations.deleteIn(['line_keys', 1]);
        localItemWithMutations.delete('operator');
        localItemWithMutations.delete('must_met');
        localItemWithMutations.delete('projection');
      }
    });
    updateLocalItem(newLocalItem);
  }

  const onChangeComputedLineKeyHardCodedKey = (e) => {
    const { value } = e.target;
    updateLocalItem(localItem.setIn(['line_keys', 1, 'key'], value));
  }

  const onSave = () => {
    onSaveComputedLineKey(localItem);
  }

  const rateConditionsSelectOptions = getConfig(['rates', 'conditions'], Map())
    .map(parseConfigSelectOptions)
    .toArray();

  const isTypeRegex = localItem.get('type', 'regex') === 'regex';

  return (
    <ModalWrapper
      title='Computed Rate Key'
      show={true}
      onOk={onSave}
      onHide={onHideComputedLineKey}
      labelOk="OK"
      modalSize="large"
    >
      <Form horizontal>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={2}>
            Computation Type
          </Col>
          <Col sm={10}>
            <span>
              <span className="inline mr10">
              <Field
                fieldType="radio"
                name="computed-type"
                id="computed-type-regex"
                value="regex"
                checked={isTypeRegex}
                onChange={onChangeComputedLineKeyType}
                label="Regex"
              />
            </span>
            <span className="inline">
              <Field
                fieldType="radio"
                name="computed-type"
                id="computed-type-condition"
                value="condition"
                checked={!isTypeRegex}
                onChange={onChangeComputedLineKeyType}
                label="Condition"
              />
            </span>
          </span>
          </Col>
        </FormGroup>
        <div className="separator" />
        <FormGroup key="computed-field-1">
          <Col sm={2} componentClass={ControlLabel}>{isTypeRegex ? 'Field' : 'First Field' }</Col>
          <Col sm={5}>
            <Field
              fieldType="select"
              onChange={onChangeComputedLineKey(['line_keys', 0, 'key'])}
              value={localItem.getIn(['line_keys', 0, 'key'], '')}
              options={conditionFieldsOptions}
              allowCreate={true}
            />
          </Col>
          <Col sm={5}>
            <Field
              value={localItem.getIn(['line_keys', 0, 'regex'], '')}
              disabledValue=''
              onChange={onChangeComputedLineKey(['line_keys', 0, 'regex'])}
              disabled={localItem.getIn(['line_keys', 0, 'key'], '') === '' || isSingleValueOperator}
              label={<span>Regex<Help contents={MappingRulesDescription.regexHelper} /></span>}
              fieldType="toggeledInput"
            />
          </Col>
        </FormGroup>
        { !isTypeRegex && (
          <>
            <FormGroup key="computed-operator">
              <Col sm={2} componentClass={ControlLabel}>Operator</Col>
              <Col sm={5}>
                <Field
                  fieldType="select"
                  onChange={onChangeComputedLineKey(['operator'])}
                  value={localItem.get('operator', '')}
                  options={rateConditionsSelectOptions}
                />
              </Col>
            </FormGroup>
            <FormGroup key="computed-field-2">
              <Col sm={2} componentClass={ControlLabel}>Second Field</Col>
              <Col sm={5}>
                { localItem.get('operator', '') === '$regex' && (
                  <Field
                    value={localItem.getIn(['line_keys', 1, 'key'], '')}
                    onChange={onChangeComputedLineKeyHardCodedKey}
                  />
                )}
                { localItem.get('operator', '') !== '$regex' && (
                  <Field
                    fieldType="select"
                    onChange={onChangeComputedLineKey(['line_keys', 1, 'key'])}
                    value={localItem.getIn(['line_keys', 1, 'key'], '')}
                    options={conditionFieldsOptions}
                    disabled={isSingleValueOperator}
                  />
                )}
              </Col>
              <Col sm={5}>
                <Field
                  value={localItem.getIn(['line_keys', 1, 'regex'], '')}
                  disabledValue={''}
                  onChange={onChangeComputedLineKey(['line_keys', 1, 'regex'])}
                  disabled={localItem.getIn(['line_keys', 1, 'key'], '') === '' || localItem.get('operator', '') === '$regex'}
                  label={<span>Regex<Help contents={MappingRulesDescription.regexHelper} /></span>}
                  fieldType="toggeledInput"
                />
              </Col>
            </FormGroup>
            <FormGroup key="computed-must-met">
              <Col componentClass={ControlLabel} sm={2}>
                Must met?
                <Help contents={MappingRulesDescription.mustMetHelper} />
              </Col>
              <Col sm={5}>
                <Field
                  fieldType="checkbox"
                  id="computed-must-met"
                  value={localItem.get('must_met', false)}
                  onChange={onChangeComputedMustMet}
                  className="input-min-line-height"
                />
              </Col>
            </FormGroup>
            <FormGroup key="computed-cond-project-true">
              <Col sm={2} componentClass={ControlLabel}>Value when True</Col>
              <Col sm={5}>
                <Field
                  fieldType="select"
                  onChange={onChangeComputedLineKey(['projection', 'on_true', 'key'])}
                  value={localItem.getIn(['projection', 'on_true', 'key'], 'condition_result')}
                  options={valueWhenOptions}
                />
              </Col>
              {['hard_coded'].includes(localItem.getIn(['projection', 'on_true', 'key'], '')) && (
                <Col sm={5}>
                  <Field
                    value={localItem.getIn(['projection', 'on_true', 'value'], '')}
                    onChange={onChangeHardCodedValue(['projection', 'on_true', 'value'])}
                  />
                </Col>
              )}
              {!['', 'hard_coded', 'condition_result'].includes(localItem.getIn(['projection', 'on_true', 'key'], '')) && (
                <Col sm={5}>
                  <Field
                    value={localItem.getIn(['projection', 'on_true', 'regex'], '')}
                    disabledValue={''}
                    onChange={onChangeComputedLineKey(['projection', 'on_true', 'regex'])}
                    disabled={localItem.getIn(['projection', 'on_true', 'key'], '') === ''}
                    label="Regex"
                    fieldType="toggeledInput"
                  />
                </Col>
              )}
            </FormGroup>
            <FormGroup key="computed-cond-project-false">
              <Col sm={2} componentClass={ControlLabel}>Value when False</Col>
              <Col sm={5}>
                <Field
                  fieldType="select"
                  onChange={onChangeComputedLineKey(['projection', 'on_false', 'key'])}
                  value={localItem.getIn(['projection', 'on_false', 'key'], 'condition_result')}
                  options={valueWhenOptions}
                  disabled={localItem.get('must_met', false)}
                />
              </Col>
              {['hard_coded'].includes(localItem.getIn(['projection', 'on_false', 'key'], '')) && (
                <Col sm={5}>
                  <Field
                    value={localItem.getIn(['projection', 'on_false', 'value'], '')}
                    onChange={onChangeHardCodedValue(['projection', 'on_false', 'value'])}
                  />
                </Col>
              )}
              {!['', 'hard_coded', 'condition_result'].includes(localItem.getIn(['projection', 'on_false', 'key'], '')) && (
                <Col sm={5}>
                  <Field
                    value={localItem.getIn(['projection', 'on_false', 'regex'], '')}
                    disabledValue={''}
                    onChange={onChangeComputedLineKey(['projection', 'on_false', 'regex'])}
                    disabled={localItem.getIn(['projection', 'on_false', 'key'], '') === ''}
                    label="Regex"
                    fieldType="toggeledInput"
                  />
                </Col>
              )}
            </FormGroup>
          </>
        )}
      </Form>
    </ModalWrapper>
  );
};


ComputedRate.propTypes = {
  item: PropTypes.instanceOf(Map),
  conditionFieldsOptions: PropTypes.array,
  valueWhenOptions: PropTypes.array,
  onSaveComputedLineKey: PropTypes.func.isRequired,
  onHideComputedLineKey: PropTypes.func.isRequired,
}

ComputedRate.defaultProps = {
  item: Map(),
  conditionFieldsOptions: [],
  valueWhenOptions: [],
};

export default ComputedRate;
