import React, { useCallback, memo } from "react";
import PropTypes from "prop-types";
import { connect } from "react-redux";
import Immutable from "immutable";
import { Conditions } from "@/components/Elements";
import { getConfig } from "@/common/Util";
import { discountAccountFieldsSelector } from "@/selectors/discountSelectors";
import { setFormModalError } from "@/actions/guiStateActions/pageActions";


const CollectionConditions = ({
  conditions,
  path,
  editable,
  errors,
  onChange,
  conditionsOperators,
  accountConditionFields,
  valueOptions,
  dispatch
}) => {

  const addNewConditionWithCheckError = useCallback(
    (newCondition) => {
      const existingConditions = conditions.getIn(path, Immutable.List());
      const count = existingConditions.size;
      if (count > 0) {
        const lastCondition = existingConditions.last(Immutable.Map());
        if (
          lastCondition.isEmpty() ||
          lastCondition.some((value) => ([], "").includes(value)) ||
          (lastCondition.has("fields") &&
            (lastCondition.get("fields", Immutable.List()).isEmpty() ||
              lastCondition.get("fields", Immutable.List()).last(Immutable.Map()).isEmpty() ||
              lastCondition
                .get("fields", Immutable.List())
                .last(Immutable.Map())
                .some((value) => ([], "").includes(value))))
        ) {
          dispatch(setFormModalError([...path, count - 1].join("."), "Conditions can not be empty"));
          return false;
        }
      }
      const newConditions = existingConditions.push(newCondition);
      onChange(path, newConditions);
      const errorStringPath = [...path, count].join(".") // TODO check
      if (errors.has(errorStringPath)) {
        dispatch(setFormModalError(errorStringPath));
      }
    },
    [onChange, path, conditions, errors, dispatch]
  );

  const changeConditionFieldWithClearError = useCallback(
    (index, value) => {
      onChange([...path, index, 'field'], value);
      dispatch(setFormModalError([...path, index].join(".")));
    },
    [onChange, path, dispatch]
  );

  const changeConditionOpWithClearError = useCallback(
    (index, value) => {
      onChange([...path, index, 'op'], value);
      dispatch(setFormModalError([...path, index].join(".")));
    },
    [onChange, path, dispatch]
  );

  const changeConditionValueWithClearError = useCallback(
    (index, value) => {
      onChange([...path, index, 'value'], value);
      dispatch(setFormModalError([...path, index].join(".")));
    },
    [onChange, path, dispatch]
  );

  const removeConditionWithClearError = useCallback(
    (index) => {
      const newConditions = conditions
        .getIn(path, Immutable.List())
        .delete(index);
      onChange(path, newConditions);
      const stringPath = [...path, index].join(".");
      errors
        .filter((error, path) => path.startsWith(stringPath))
        .forEach((error, path) => {
          dispatch(setFormModalError(path));
        });
    },
    [onChange, conditions, path, errors, dispatch]
  );

  return (
    <Conditions
      conditions={conditions.getIn(path, Immutable.List())}
      editable={editable}
      fields={accountConditionFields}
      operators={conditionsOperators}
      customValueOptions={valueOptions}
      errors={errors}
      onChangeField={changeConditionFieldWithClearError}
      onChangeOperator={changeConditionOpWithClearError}
      onChangeValue={changeConditionValueWithClearError}
      onAdd={addNewConditionWithCheckError}
      onRemove={removeConditionWithClearError}
    />
  );
};


CollectionConditions.propTypes = {
  conditions: PropTypes.instanceOf(Immutable.List),
  conditionsOperators: PropTypes.instanceOf(Immutable.List),
  valueOptions: PropTypes.instanceOf(Immutable.List),
  editable: PropTypes.bool,
  path: PropTypes.array,
  accountConditionFields: PropTypes.instanceOf(Immutable.List),
  onChange: PropTypes.func.isRequired,
  dispatch: PropTypes.func.isRequired,
};

CollectionConditions.defaultProps = {
  conditions: Immutable.List(),
  errors: Immutable.Map(),
  editable: true,
  path: [0, "account", "fields"],
  conditionsOperators: getConfig(["discount", "conditions", "operators"], Immutable.List()),
  valueOptions: getConfig(["discount", "conditions", "valueOptions"], Immutable.List()),
  accountConditionFields: Immutable.List(),
};

const mapStateToProps = (state, props) => ({
  accountConditionFields: discountAccountFieldsSelector(state, props, "account"),
});

export default connect(mapStateToProps)(memo(CollectionConditions));
