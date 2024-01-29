import React, { useCallback } from 'react'
import PropTypes from 'prop-types'
import { List, Map } from 'immutable';
import { Form, Panel } from 'react-bootstrap';
import uuid from 'uuid';
import { CreateButton } from '@/components/Elements';
import Field from '@/components/Field';
import Priority from './Priority';

const createBtnStyle = {};
const demmyTitleStyle = {marginLeft: 21};

const newDefaultCondition = Map();
const newDefaultPriority = Map({
  filters: List([newDefaultCondition]),
  cache_db_queries: true
});


const Priorities = ({
  type,
  category,
  priorities,
  lineKeyOptions,
  paramsKeyOptions,
  conditionFieldsOptions,
  valueWhenOptions,
  onAdd,
  onUpdate,
  onRemove,
}) => {
  const addCondition = useCallback((path) => {
    onAdd([category, 'priorities', ...path], newDefaultCondition);
  }, [onAdd, category]);
  const addPriority = useCallback(() => {
    const newPriority = newDefaultPriority.set('uiFlag', Map({id: uuid.v4()}))
    onAdd([category, 'priorities'], newPriority);
  }, [onAdd, category]);

  const updateDefaultFallback = useCallback((e) => {
    const { value } = e.target;
    onUpdate([category, 'default_fallback'], value)
  }, [onUpdate, category]);

  const update = useCallback((path, value) => {
    onUpdate([category, 'priorities', ...path], value);
  }, [onUpdate, category]);

  const remove = useCallback((path) => {
    onRemove([category, 'priorities', ...path]);
  }, [onRemove, category]);

  return (
    <Form horizontal>
      <Panel header={<div style={demmyTitleStyle}>Priority 1</div>}>
        <Field
          fieldType="checkbox"
          value={true}
          label="Use tax rate forced by product/service/plan"
          disabled={true}
        />
      </Panel>
      { priorities.get('priorities', List()).map((priority, index) => (
        <Priority
          key={priority.getIn(['uiFlag', 'id'], index)}
          index={index}
          category={category}
          type={type}
          priority={priority}
          count={priorities.size}
          lineKeyOptions={lineKeyOptions}
          paramsKeyOptions={paramsKeyOptions}
          conditionFieldsOptions={conditionFieldsOptions}
          valueWhenOptions={valueWhenOptions}
          onAdd={addCondition}
          onUpdate={update}
          onRemove={remove}
        />
      ))}

      <Panel header={<div style={demmyTitleStyle}>Priority {priorities.get('priorities', List()).size + 2}</div>}>
        <Field
          fieldType="checkbox"
          value={true}
          label="Use tax rate referenced by product/service/plan"
          disabled={true}
        />
      </Panel>
      <Panel header={<div style={demmyTitleStyle}>Priority {priorities.get('priorities', List()).size + 3}</div>}>
        <Field
          fieldType="checkbox"
          value={priorities.get('default_fallback', false)}
          onChange={updateDefaultFallback}
          label="Use default tax rate"
        />
      </Panel>
      <CreateButton
        label="Add Priority"
        onClick={addPriority}
        buttonStyle={createBtnStyle}
      />
    </Form>
  );
}


Priorities.defaultProps = {
  priorities: Map(),
  lineKeyOptions: [],
  paramsKeyOptions: [],
  conditionFieldsOptions: [],
  valueWhenOptions: [],
};


Priorities.propTypes = {
  type: PropTypes.string.isRequired,
  category: PropTypes.string.isRequired,
  priorities: PropTypes.instanceOf(Map),
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
  onAdd: PropTypes.func.isRequired,
  onUpdate: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
};


export default Priorities;
