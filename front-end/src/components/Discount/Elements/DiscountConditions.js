import React, { useCallback, useMemo, memo } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Panel, HelpBlock } from 'react-bootstrap';
import { CreateButton, Actions } from '@/components/Elements';
import DiscountCondition from './DiscountCondition';
import { getConfig } from '@/common/Util';
import {
  discountSubscriberFieldsSelector,
  discountAccountFieldsSelector,
  discountSubscriberServicesFieldsSelector,
} from '@/selectors/discountSelectors';
import { showConfirmModal, setFormModalError } from '@/actions/guiStateActions/pageActions';


const createBtnStyle = { marginTop: 0 };
const defaultNewConditionsGroup = Immutable.Map();
const defaultNewServiceConditionsGroup = Immutable.Map({
  fields: Immutable.List([
    defaultNewConditionsGroup
  ])
});
const defaultNewCondition = Immutable.Map({
  field: '',
  op: '',
  value: '',
});

const DiscountConditions = ({
  discount,
  editable,
  errors,
  conditionsOperators,
  valueOptions,
  onChangeConditionField,
  onChangeConditionOp,
  onChangeConditionValue,
  addCondition,
  removeCondition,
  conditionsPath,
  accountConditionsPath,
  subscriberConditionsPath,
  servicesConditionsPath,
  servicesAnyConditionsPath,
  subscriberConditionFields,
  accountConditionFields,
  subscriberServicesConditionFields,
  dispatch,
}) => {

  const addNewConditionWithCheckError = useCallback((conditionsPath, newConditions) => {
    const [params, conditions, index, ...otherPath] = conditionsPath; // eslint-disable-line no-unused-vars
    const count = discount.getIn(conditionsPath, Immutable.List()).size;
    if (count > 0) {
      const lastCondition = discount.getIn(conditionsPath, Immutable.List()).last(Immutable.Map());
      if (lastCondition.isEmpty()
        || lastCondition.some(value => ([], '').includes(value))
        || (lastCondition.has('fields') && (
            lastCondition.get('fields', Immutable.List()).isEmpty()
            || lastCondition.get('fields', Immutable.List()).last(Immutable.Map()).isEmpty()
            || lastCondition.get('fields', Immutable.List()).last(Immutable.Map()).some(value => ([], '').includes(value))
          ))
      ) {
        dispatch(setFormModalError([...conditionsPath, count-1].join('.'), 'Conditions can not be empty'));
        return false;
      }
    }
    addCondition(conditionsPath, newConditions);
    const errorStringPath = [params, conditions, index].join('.');
    if (errors.has(errorStringPath)) {
      dispatch(setFormModalError(errorStringPath));
    }
  }, [addCondition, discount, errors, dispatch]);

  const addConditions = useCallback((newConditions) => {
    addNewConditionWithCheckError(conditionsPath, newConditions);
  }, [addNewConditionWithCheckError, conditionsPath]);

  const addAccountConditions = useCallback((idx) => {
    addNewConditionWithCheckError([...conditionsPath, idx, ...accountConditionsPath], defaultNewCondition);
  }, [addNewConditionWithCheckError, conditionsPath, accountConditionsPath]);

  const addSubscriberConditions = useCallback((idx) => {
    addNewConditionWithCheckError([...conditionsPath, idx, ...subscriberConditionsPath], defaultNewCondition);
  }, [addNewConditionWithCheckError, conditionsPath, subscriberConditionsPath]);

  const addServiceConditionsGroup = useCallback((idx) => {
    const path = [...conditionsPath, idx, ...servicesConditionsPath];
    addNewConditionWithCheckError(path, defaultNewServiceConditionsGroup);
  }, [addNewConditionWithCheckError, conditionsPath, servicesConditionsPath]);

  const changeConditionFieldWithClearError = useCallback((path, index, value) => {
    onChangeConditionField(path, index, value);
    dispatch(setFormModalError([...path, index].join('.')));
  }, [onChangeConditionField, dispatch]);

  const changeConditionOpWithClearError = useCallback((path, index, value) => {
    onChangeConditionOp(path, index, value);
    dispatch(setFormModalError([...path, index].join('.')));
  }, [onChangeConditionOp, dispatch]);

  const changeConditionValueWithClearError = useCallback((path, index, value) => {
    onChangeConditionValue(path, index, value);
    dispatch(setFormModalError([...path, index].join('.')));
  }, [onChangeConditionValue, dispatch]);

  const changeConditionFieldWithClearErrorWithSetCleanError = useCallback((path, index, value) => {
    changeConditionFieldWithClearError(path, index, value);
    const setPath = [...path].splice(0, path.length - servicesAnyConditionsPath.length);
    dispatch(setFormModalError(setPath.join('.')));
  }, [changeConditionFieldWithClearError, servicesAnyConditionsPath, dispatch]);

  const changeConditionOpWithClearErrorWithSetCleanError = useCallback((path, index, value) => {
    changeConditionOpWithClearError(path, index, value);
    const setPath = [...path].splice(0, path.length - servicesAnyConditionsPath.length);
    dispatch(setFormModalError(setPath.join('.')));
  }, [changeConditionOpWithClearError, servicesAnyConditionsPath, dispatch]);

  const changeConditionValueWithClearErrorWithSetCleanError = useCallback((path, index, value) => {
    changeConditionValueWithClearError(path, index, value);
    const setPath = [...path].splice(0, path.length - servicesAnyConditionsPath.length);
    dispatch(setFormModalError(setPath.join('.')));
  }, [changeConditionValueWithClearError, servicesAnyConditionsPath, dispatch]);

  const removeConditionWithClearError = useCallback((path, index) => {
    removeCondition(path, index);
    const stringPath = [...path, index].join('.');
    errors
      .filter((error, path) => path.startsWith(stringPath))
      .forEach((error, path) => { dispatch(setFormModalError(path)); })
  }, [removeCondition, errors, dispatch]);

  const removeServiceConditionsGroup = useCallback(({idx, anyIdx}) => {
    const path = [...conditionsPath, idx, ...servicesConditionsPath];
    const allEmpty = discount.getIn(path, Immutable.List())
      .every(anyCondition => anyCondition.getIn(servicesAnyConditionsPath, Immutable.List()).isEmpty());
    const onOk = () => {
      removeConditionWithClearError(path, anyIdx);
      dispatch(setFormModalError());
    }
    if (allEmpty) {
      onOk();
    } else {
      const confirm = {
        message: `Are you sure you want to remove service conditions set ${anyIdx + 1} from condition set ${idx+1} ?`,
        onOk,
        labelOk: 'Delete',
        type: 'delete',
      };
      dispatch(showConfirmModal(confirm));
    }
  }, [
    discount,
    removeConditionWithClearError,
    conditionsPath,
    servicesConditionsPath,
    servicesAnyConditionsPath,
    dispatch,
  ]);

  const removeServiceCondition = useCallback((path, idx) => {
    if (discount.getIn(path, Immutable.List()).size === 1) {
      const tmpPath = [...path];
      tmpPath.pop(); // remove 'field'
      const index = tmpPath.pop();
      removeConditionWithClearError(tmpPath, index);
    } else {
      removeConditionWithClearError(path, idx);
    }
  }, [removeConditionWithClearError, discount]);

  const removeConditionsGroup = useCallback((idx) => {
    const path = [...conditionsPath, idx];
    const isAccountEmpty = discount.getIn([...path, ...accountConditionsPath], Immutable.List()).isEmpty();
    const isSubscriberEmpty = discount.getIn([...path, ...subscriberConditionsPath], Immutable.List()).isEmpty();
    const isServicesEmpty = discount.getIn([...path, ...servicesConditionsPath], Immutable.List()).isEmpty();
    if (isAccountEmpty && isSubscriberEmpty && isServicesEmpty){
      removeConditionWithClearError(conditionsPath, idx);
    } else {
      const confirm = {
        message: `Are you sure you want to remove conditions set ${idx+1} ?`,
        onOk: () => removeConditionWithClearError(conditionsPath, idx),
        labelOk: 'Delete',
        type: 'delete',
      };
      dispatch(showConfirmModal(confirm));
    }
  }, [
    discount,
    removeConditionWithClearError,
    conditionsPath,
    accountConditionsPath,
    subscriberConditionsPath,
    servicesConditionsPath,
    dispatch,
  ]);

  const conditionAddActions = useMemo(() => [{
    type: 'add',
    label: 'Customer',
    showIcon: false,
    onClick: addAccountConditions,
  }, {
    type: 'add',
    label: 'Subscriber',
    showIcon: false,
    onClick: addSubscriberConditions,
  }, {
    type: 'add',
    label: 'Service',
    showIcon: false,
    onClick: addServiceConditionsGroup,
  }], [addAccountConditions, addSubscriberConditions, addServiceConditionsGroup]);

  const conditionAddActionsBtns = useMemo(() => [{
    type: 'add',
    label: 'Add Customer condition(s)',
    actionSize: 'xsmall',
    actionStyle: 'primary',
    onClick: addAccountConditions,
  }, {
    type: 'add',
    label: 'Add Subscriber condition(s)',
    actionSize: 'xsmall',
    actionStyle: 'primary',
    onClick: addSubscriberConditions,
  }, {
    type: 'add',
    label: 'Add Service condition(s) set',
    actionSize: 'xsmall',
    actionStyle: 'primary',
    onClick: addServiceConditionsGroup,
  }], [addAccountConditions, addSubscriberConditions, addServiceConditionsGroup]);

  const removeGroupHelpText = idx => `Remove conditions set ${idx + 1}`;

  const conditionActions = useMemo(() => [{
    type: 'remove',
    helpText: removeGroupHelpText,
    showIcon: true,
    actionStyle: 'danger',
    actionSize: 'xsmall',
    onClick: removeConditionsGroup,
  }], [removeConditionsGroup]);

  const removeServiceGroupHelpText = ({idx, anyIdx}) => `Remove Service set ${anyIdx + 1} from condition set ${idx + 1}`;

  const conditionServiceGroupActions = useMemo(() => [{
    type: 'remove',
    helpText: removeServiceGroupHelpText,
    showIcon: true,
    actionStyle: 'danger',
    actionSize: 'xsmall',
    onClick: removeServiceConditionsGroup,
  }], [removeServiceConditionsGroup]);

  const conditionServicesActions = useMemo(() => [{
    type: 'add',
    label: 'Add Service condition(s) set',
    showIcon: true,
    actionStyle: 'primary',
    actionSize: 'xsmall',
    onClick: addServiceConditionsGroup,
  }], [addServiceConditionsGroup]);

  const isConditoinsExists = useMemo(() => !discount.getIn(conditionsPath, Immutable.List())
    .every(groupConditions => !groupConditions.getIn(accountConditionsPath, Immutable.List()).isEmpty()
      && !groupConditions.getIn(subscriberConditionsPath, Immutable.List()).isEmpty()
      && !groupConditions.getIn(servicesConditionsPath, Immutable.List()).isEmpty()
  ), [discount, conditionsPath, accountConditionsPath, subscriberConditionsPath, servicesConditionsPath]);

  const getConditionHeader = useCallback((idx) => (
    <div>
      {`Condition Set ${idx+1}`}
      <div className="pull-right">
        <Actions actions={conditionAddActions} data={idx} type='dropdown' doropDownLabel="Add condition(s)" />
        <div className="inline ml5">
          <Actions actions={conditionActions} data={idx} />
        </div>
      </div>
    </div>
  ), [conditionAddActions, conditionActions]);

  const getConditionServicesHeader = useCallback((idx) => (
    <div>
      Services
      <div className="pull-right">
        <Actions actions={conditionServicesActions} data={idx} />
      </div>
    </div>
  ), [conditionServicesActions]);

  const getConditionServiceGroupHeader = useCallback(({idx, anyIdx}) => (
    <div>
      {`Service ${anyIdx+1}`}
      <div className="pull-right">
        <Actions actions={conditionServiceGroupActions} data={{idx, anyIdx}} />
      </div>
    </div>
  ), [conditionServiceGroupActions]);

  const addNewConditionsBtn = useMemo(() => (
    <CreateButton
      onClick={addConditions}
      data={defaultNewConditionsGroup}
      label="Add conditions set"
      buttonStyle={createBtnStyle}
    />
  ), [addConditions]);

  const conditionsHeaderDescription = useMemo(() => (isConditoinsExists
    ? 'At least one set of conditions must be fulfilled'
    : 'No conditions'
  ), [isConditoinsExists]);

  const conditionsHeader = useMemo(() => (
    <div>
      <h4 className="inline mt0 mb0">Conditions<small> | {conditionsHeaderDescription}</small></h4>
      <div className="pull-right">{addNewConditionsBtn}</div>
    </div>
  ), [addNewConditionsBtn, conditionsHeaderDescription]);
  return (
    <Panel header={conditionsHeader}>
      {discount.getIn(conditionsPath, Immutable.List()).map((conditions, idx) => (
        <Panel header={getConditionHeader(idx)} key={idx} bsStyle={errors.has([...conditionsPath, idx].join('.')) ? "danger" : undefined }>
          { conditions.getIn(accountConditionsPath, Immutable.List()).isEmpty()
          && conditions.getIn(subscriberConditionsPath, Immutable.List()).isEmpty()
          && conditions.getIn(servicesConditionsPath, Immutable.List()).isEmpty()
          && (
            <div className="text-center">
              <Actions actions={conditionAddActionsBtns} data={idx} />
              {errors.get([...conditionsPath, idx].join('.'), '') !== '' && (
                <HelpBlock className="danger-red"><small>{errors.get([...conditionsPath, idx].join('.'), '')}</small></HelpBlock>
              )}
            </div>
          )}
          {!conditions.getIn(accountConditionsPath, Immutable.List()).isEmpty() && (
            <Panel header="Customer">
              <DiscountCondition
                path={[...conditionsPath, idx, ...accountConditionsPath]}
                conditions={conditions.getIn(accountConditionsPath, Immutable.List())}
                editable={editable}
                fields={accountConditionFields}
                operators={conditionsOperators}
                valueOptions={valueOptions}
                onChangeField={changeConditionFieldWithClearError}
                onChangeOp={changeConditionOpWithClearError}
                onChangeValue={changeConditionValueWithClearError}
                onAdd={addNewConditionWithCheckError}
                onRemove={removeConditionWithClearError}
                errors={errors}
              />
            </Panel>
          )}
          {!conditions.getIn(subscriberConditionsPath, Immutable.List()).isEmpty() && (
            <Panel header="Subscriber">
              <DiscountCondition
                path={[...conditionsPath, idx, ...subscriberConditionsPath]}
                conditions={conditions.getIn(subscriberConditionsPath, Immutable.List())}
                editable={editable}
                fields={subscriberConditionFields}
                operators={conditionsOperators}
                valueOptions={valueOptions}
                onChangeField={changeConditionFieldWithClearError}
                onChangeOp={changeConditionOpWithClearError}
                onChangeValue={changeConditionValueWithClearError}
                onAdd={addNewConditionWithCheckError}
                onRemove={removeConditionWithClearError}
                errors={errors}
              />
            </Panel>
          )}
          {!conditions.getIn(servicesConditionsPath, Immutable.List()).isEmpty() && (
            <Panel header={getConditionServicesHeader(idx)}>
              {conditions.getIn(servicesConditionsPath, Immutable.List()).map((anyConditions, anyIdx) => (
                <Panel
                  header={getConditionServiceGroupHeader({idx, anyIdx})}
                  key={`service_condition_${idx}_any_${anyIdx}`}
                  bsStyle={errors.has([...conditionsPath, idx, ...servicesConditionsPath, anyIdx].join('.')) ? "danger" : undefined }
                >
                  {!anyConditions.getIn(servicesAnyConditionsPath, Immutable.List()).isEmpty() && (
                    <DiscountCondition
                      path={[...conditionsPath, idx, ...servicesConditionsPath, anyIdx, ...servicesAnyConditionsPath]}
                      conditions={anyConditions.getIn(servicesAnyConditionsPath, Immutable.List())}
                      editable={editable}
                      fields={subscriberServicesConditionFields}
                      operators={conditionsOperators}
                      onChangeField={changeConditionFieldWithClearErrorWithSetCleanError}
                      onChangeOp={changeConditionOpWithClearErrorWithSetCleanError}
                      onChangeValue={changeConditionValueWithClearErrorWithSetCleanError}
                      onAdd={addNewConditionWithCheckError}
                      onRemove={removeServiceCondition}
                      errors={errors}
                    />
                  )}
                </Panel>
              ))}
            </Panel>
          )}
        </Panel>
      ))}
      {!discount.getIn(conditionsPath, Immutable.List()).isEmpty() && (addNewConditionsBtn)}
    </Panel>
  )
};

DiscountConditions.propTypes = {
  discount: PropTypes.instanceOf(Immutable.Map),
  conditionsOperators: PropTypes.instanceOf(Immutable.List),
  valueOptions: PropTypes.instanceOf(Immutable.List),
  editable: PropTypes.bool,
  conditionsPath: PropTypes.array,
  accountConditionsPath: PropTypes.array,
  subscriberConditionFields: PropTypes.instanceOf(Immutable.List),
  accountConditionFields: PropTypes.instanceOf(Immutable.List),
  subscriberServicesConditionFields: PropTypes.instanceOf(Immutable.List),
  subscriberConditionsPath: PropTypes.array,
  servicesConditionsPath: PropTypes.array,
  onChangeConditionField: PropTypes.func.isRequired,
  onChangeConditionOp: PropTypes.func.isRequired,
  onChangeConditionValue: PropTypes.func.isRequired,
  addCondition: PropTypes.func.isRequired,
  removeCondition: PropTypes.func.isRequired,
  dispatch: PropTypes.func.isRequired,
};

DiscountConditions.defaultProps = {
  discount: Immutable.Map(),
  errors: Immutable.Map(),
  editable: true,
  conditionsPath: ['params', 'conditions'],
  accountConditionsPath: ['account', 'fields'],
  subscriberConditionsPath: ['subscriber', 0, 'fields'],
  servicesConditionsPath: ['subscriber', 0, 'service', 'any'],
  servicesAnyConditionsPath: ['fields'],
  conditionsOperators: getConfig(['discount', 'conditions', 'operators'], Immutable.List()),
  valueOptions: getConfig(['discount', 'conditions', 'valueOptions'], Immutable.List()),
  subscriberConditionFields: Immutable.List(),
  accountConditionFields: Immutable.List(),
  subscriberServicesConditionFields: Immutable.List(),
};

const mapStateToProps = (state, props) => ({
  subscriberConditionFields: discountSubscriberFieldsSelector(state, props, 'subscriber'),
  accountConditionFields: discountAccountFieldsSelector(state, props, 'account'),
  subscriberServicesConditionFields: discountSubscriberServicesFieldsSelector(state, props, 'subscriber_services'),
});

export default connect(mapStateToProps)(memo(DiscountConditions));
