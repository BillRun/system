import React, { memo, useMemo, useCallback, useState } from 'react';
import PropTypes from 'prop-types';
import { titleCase } from 'change-case';
import { List, Map } from 'immutable';
import { Col, Collapse } from 'react-bootstrap';
import { FormGroup } from '@/common/BootstrapCompat';
import PriorityCondition from './PriorityCondition';
import { CreateButton, Actions } from '@/components/Elements';

const createBtnStyle = { marginTop: 5 };

const Priority = ({
  priority = Map(),
  index = 0,
  type,
  paramsKeyOptions = [],
  lineKeyOptions = [],
  conditionFieldsOptions = [],
  valueWhenOptions = [],
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

  const [isExpanded, setIsExpanded] = useState(true);
  const onToggle = useCallback((e) => {
    e.preventDefault();
    setIsExpanded(prev => !prev);
  }, []);

  return (
    <div className="panel panel-default collapsible">
      <div className="panel-heading">
        <div className="pull-right">
          <Actions actions={actions} data={priorityPath} />
        </div>
        <h3 className="panel-title">
          <a
            href="#"
            role="button"
            className={isExpanded ? '' : 'collapsed'}
            onClick={onToggle}
          >
            {`Priority ${index + 2}`}
          </a>
        </h3>
      </div>
      <Collapse in={isExpanded}>
        <div>
          <div className="panel-body">
            <div className="priority-conditions">
              <Col sm={12} className="form-inner-edit-rows">
                {priorityConditions.isEmpty() && (
                  <small>No conditions found</small>
                )}
                {!priorityConditions.isEmpty() && (
                  <FormGroup className="form-inner-edit-row">
                    <Col sm={4} className="hidden-xs"><label className="ml5 mb0">CDR Field</label></Col>
                    <Col sm={3} className="hidden-xs"><label className="mb0">Operator</label></Col>
                    <Col sm={4} className="hidden-xs"><label className="mb0">{titleCase(type)} Parameter</label></Col>
                    <Col sm={1} className="hidden-xs" />
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
          </div>
        </div>
      </Collapse>
    </div>
  )
}

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
