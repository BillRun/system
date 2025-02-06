import React, { memo, useCallback, useMemo, useState } from 'react'
import PropTypes from 'prop-types';
import { Map, List } from 'immutable';
import { titleCase } from 'change-case';
import { FormGroup, Col } from 'react-bootstrap';
import Field from '@/components/Field';
import { Actions } from '@/components/Elements';
import { getConfig, parseConfigSelectOptions } from '@/common/Util';
import ComputedRate from './ComputedRate';


const PriorityCondition = ({
  condition, index, priorityIndex, type, onUpdate, onRemove, allowRemove,
  lineKeyOptions, paramsKeyOptions, paramsTypeOptions,
  conditionFieldsOptions, valueWhenOptions,
}) => {
  const [computedLineKey, setComputedLineKey] = useState(null);

  const computedLineKeyObject = useMemo(() => Map({
    type: condition.getIn(['computed', 'type'], 'regex'),
    line_keys: condition.getIn(['computed', 'line_keys'], List()),
    operator: condition.getIn(['computed', 'operator'], ''),
    must_met: condition.getIn(['computed', 'must_met'], false),
    projection: Map({
      on_true: Map({
        key: condition.getIn(['computed', 'projection', 'on_true', 'key'], 'condition_result'),
        regex: condition.getIn(['computed', 'projection', 'on_true', 'regex'], ''),
        value: condition.getIn(['computed', 'projection', 'on_true', 'value'], ''),
      }),
      on_false: Map({
        key: condition.getIn(['computed', 'projection', 'on_false', 'key'], 'condition_result'),
        regex: condition.getIn(['computed', 'projection', 'on_false', 'regex'], ''),
        value: condition.getIn(['computed', 'projection', 'on_false', 'value'], ''),
      }),
    }),
  }), [condition]);

  const onChangeLineKey = useCallback((value) => {
    if (value === 'computed') {
      setComputedLineKey(computedLineKeyObject);
    } else {
      if (condition.has('computed')) {
        // remove computed preperty from condition
        onUpdate([index, 'computed'], null);
      }
    }
    onUpdate([index, 'line_key'], value);
  }, [onUpdate, index, computedLineKeyObject, condition]);

  const onChangeType = useCallback((value) => {
    onUpdate([index, 'type'], value);
  }, [onUpdate, index]);

  const onChangeParamKey = useCallback((value) => {
    onUpdate([index, 'entity_key'], value);
  }, [onUpdate, index]);

  const onSaveComputed = useCallback((computed) => {
    onUpdate([index, 'line_key'], 'computed');
    onUpdate([index, 'computed'], computed);
    setComputedLineKey(null);
  }, [onUpdate, index]);

  const conditionActions = useMemo(() => [
    { type: 'remove', onClick: onRemove, show: allowRemove, helpText: `Remove Condition ${index + 1} of Priority ${priorityIndex + 1 }`, actionStyle: 'default' },
  ], [onRemove, allowRemove, priorityIndex, index]);

  const computedLineActions = useMemo(() => {
    const onShowComputedLineKey = () => {
      setComputedLineKey(computedLineKeyObject);
    }
    const op = condition.getIn(['computed', 'operator'], '');
    const opLabel = getConfig(['rates', 'conditions'], Map())
      .find(cond => cond.get('id', '') === op, null, Map())
      .get('title', '');
    const firstFieldValue = condition.getIn(['computed', 'line_keys', 0, 'key'], '');
    const firstField = conditionFieldsOptions.find(
      conditionFieldsOption => conditionFieldsOption.value === firstFieldValue
    );
    const secondFieldValue = condition.getIn(['computed', 'line_keys', 1, 'key'], '');
    const secondField = conditionFieldsOptions.find(
      conditionFieldsOption => conditionFieldsOption.value === secondFieldValue
    );
    const editLabel = `${firstField ? firstField.label : firstFieldValue} ${opLabel} ${secondField ? secondField.label :secondFieldValue }`;
    const showEdit = condition.get('line_key', '') === 'computed';
    return ([{
      type: 'edit', onClick: onShowComputedLineKey, label: editLabel, show: showEdit, actionClass: 'pr0 pl0 mt5',
    }])
  }, [condition, conditionFieldsOptions, computedLineKeyObject]);

  const paramsTypeSelectOptions = useMemo(() => paramsTypeOptions
    .map(parseConfigSelectOptions)
    .toArray()
  , [paramsTypeOptions]);

  const onHideComputed = () => {
    setComputedLineKey(null);
  }

  return (
    <FormGroup className="form-inner-edit-row">
      <Col smHidden mdHidden lgHidden>
        <label>CDR Field</label>
      </Col>
      <Col sm={4}>
        <Field
          fieldType="select"
          options={lineKeyOptions}
          onChange={onChangeLineKey}
          value={condition.get('line_key', '')}
        />
        <Actions actions={computedLineActions} data={index} />
      </Col>

      <Col smHidden mdHidden lgHidden>
        <label>Operator</label>
      </Col>
      <Col sm={3}>
        <Field
          fieldType="select"
          options={paramsTypeSelectOptions}
          onChange={onChangeType}
          value={condition.get('type', '')}
          disabled={condition.get('line_key', '') === ''}
        />
      </Col>

      <Col smHidden mdHidden lgHidden>
        <label>{titleCase(type)} Parameter</label>
      </Col>
      <Col sm={4}>
        <Field
          fieldType="select"
          options={paramsKeyOptions}
          onChange={onChangeParamKey}
          value={condition.get('entity_key', '')}
          disabled={condition.get('type', '') === ''}
        />
      </Col>
      <Col sm={1}>
        <Actions actions={conditionActions} data={index}/>
      </Col>
      <Col sm={12} smHidden mdHidden lgHidden>
        <hr className="mt10 mb10"/>
      </Col>
      {computedLineKey && (
        <ComputedRate
          onSaveComputedLineKey={onSaveComputed}
          onHideComputedLineKey={onHideComputed}
          item={computedLineKey}
          conditionFieldsOptions={conditionFieldsOptions}
          valueWhenOptions={valueWhenOptions}
        />
      )}
    </FormGroup>
  )
}

PriorityCondition.defaultProps = {
  condition: Map(),
  index: 0,
  allowRemove: true,
  priorityIndex: 0,
  lineKeyOptions: [],
  paramsKeyOptions: [],
  conditionFieldsOptions: [],
  valueWhenOptions: [],
  paramsTypeOptions: getConfig(['rates', 'paramsConditions'], List())
};

PriorityCondition.propTypes = {
  condition: PropTypes.instanceOf(Map),
  type: PropTypes.string.isRequired,
  index: PropTypes.number,
  priorityIndex: PropTypes.number,
  allowRemove: PropTypes.bool,
  lineKeyOptions: PropTypes.arrayOf(
    PropTypes.shape({
      value: PropTypes.string,
      label: PropTypes.string,
    }),
  ),
  paramsKeyOptions: PropTypes.arrayOf(
    PropTypes.shape({
      value: PropTypes.string,
      label: PropTypes.string,
    }),
  ),
  conditionFieldsOptions: PropTypes.arrayOf(
    PropTypes.shape({
      value: PropTypes.string,
      label: PropTypes.string,
    }),
  ),
  valueWhenOptions: PropTypes.arrayOf(
    PropTypes.shape({
      value: PropTypes.string,
      label: PropTypes.string,
    }),
  ),
  paramsTypeOptions: PropTypes.instanceOf(List),
  onUpdate: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
};

export default memo(PriorityCondition);
