import React, { memo, useMemo, useCallback } from 'react';
import PropTypes from 'prop-types';
import { titleCase } from 'change-case';
import { List, Map } from 'immutable';
import { Panel, Col, FormGroup } from 'react-bootstrap';
import PriorityCondition from './PriorityCondition';
import { CreateButton, Actions } from '@/components/Elements';

const createBtnStyle = { marginTop: 5 };

const Priority = ({
  priority,
  index,
  type,
  paramsKeyOptions,
  lineKeyOptions,
  conditionFieldsOptions,
  valueWhenOptions,
  onUpdate,
  onRemove,
  onAdd,
}) => {

  const priorityPath = useMemo(() => [index],[index]);

  const conditionPath = useMemo(() => [...priorityPath, 'filters'], [priorityPath]);

  const priorityConditions = useMemo(() => priority.get('filters', List()), [priority]);

  const updateCondition = useCallback((path, value) => {
    if (value === null) {
      onUpdate(conditionPath, priorityConditions.deleteIn(path));
    } else {
      onUpdate([...conditionPath, ...path], value);
    }
  }, [onUpdate, conditionPath, priorityConditions]);

  const removeCondition = useCallback((idx) => {
    onRemove([...conditionPath, idx]);
  }, [onRemove, conditionPath]);

  const actions = useMemo(() => [{
    type: 'remove',
    onClick: onRemove,
    helpText: `Remove Priority ${index + 2}`,
    actionClass: 'pr0 pl0 pt0',
  }], [onRemove, index]);

  const header = (
    <div>
      {`Priority ${index + 2}`}
      <div className="pull-right">
        <Actions actions={actions} data={priorityPath} />
      </div>
    </div>
  );
  return (
    <Panel header={header} collapsible={true} className="collapsible" defaultExpanded={true}>
      <div className="priority-conditions">
        <Col sm={12} className="form-inner-edit-rows">
          {priorityConditions.isEmpty() && (
            <small>No conditions found</small>
          )}
          {!priorityConditions.isEmpty() && (
            <FormGroup className="form-inner-edit-row">
              <Col sm={4} xsHidden><label className="ml5 mb0">CDR Field</label></Col>
              <Col sm={3} xsHidden><label className="mb0">Operator</label></Col>
              <Col sm={4} xsHidden><label className="mb0">{titleCase(type)} Parameter</label></Col>
              <Col sm={1} xsHidden></Col>
            </FormGroup>
          )}
          { priorityConditions.map((condition, idx) => (
            <PriorityCondition
              key={idx}
              index={idx}
              priorityIndex={index}
              allowRemove={priorityConditions.size > 1}
              type={type}
              lineKeyOptions={lineKeyOptions}
              paramsKeyOptions={paramsKeyOptions}
              conditionFieldsOptions={conditionFieldsOptions}
              valueWhenOptions={valueWhenOptions}
              condition={condition}
              onUpdate={updateCondition}
              onRemove={removeCondition}
            />
          )) }
        </Col>
        <Col sm={12} className="ml5 pl0">
          <CreateButton
            onClick={onAdd}
            data={conditionPath}
            label="Add Condition"
            buttonStyle={createBtnStyle}
          />
        </Col>
      </div>
    </Panel>
  )
}

Priority.defaultProps = {
  priority: Map(),
  lineKeyOptions: [],
  paramsKeyOptions: [],
  conditionFieldsOptions: [],
  valueWhenOptions: [],
  index: 0,
};

Priority.propTypes = {
  priority: PropTypes.instanceOf(Map),
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
  index: PropTypes.number,
  type: PropTypes.string.isRequired,
  onRemove: PropTypes.func.isRequired,
  onUpdate: PropTypes.func.isRequired,
  onAdd: PropTypes.func.isRequired,
};

export default memo(Priority);
