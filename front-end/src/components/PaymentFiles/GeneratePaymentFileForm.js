import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form } from 'react-bootstrap';
import { Map, List } from 'immutable';
import { EntityFields } from '@/components/Entity';
import { connect } from "react-redux";
import { validateFieldByType } from "@/actions/entityActions";

const GeneratePaymentFileForm = ({ item, errors, onChange }) => {
  const fieldsConfig = item.get('fields', Immutable.List());
  if (fieldsConfig.isEmpty()) {
    return 'No additional data required to generate file';
  }
  return (
    <Form horizontal>
      <EntityFields
        entityName="payments"
        entity={item.get('values', Immutable.Map())}
        errors={errors}
        fields={fieldsConfig}
        onChangeField={onChange}
      />
    </Form>
  );
}

GeneratePaymentFileForm.propTypes = {
  item: PropTypes.instanceOf(Map),
  fieldsConfig: PropTypes.instanceOf(List),
  onChange: PropTypes.func.isRequired,
};

GeneratePaymentFileForm.defaultProps = {
  item: Map(),
};

const mapStateToProps = null;

const mapDispatchToProps = (dispatch, { updateField, setError, item }) => ({
  onChange: (key, value) => {
    const path = Array.isArray(key) ? key : [key];
    const pathString = path.join(".");
    const fieldConfig = item
      .get('fields', Immutable.List())
      .find(field => field.get('field_name', '') === pathString, null, Map());
    const hasError = validateFieldByType(value, fieldConfig);
    if (hasError !== false) {
      setError(pathString, hasError);
    } else {
      setError(pathString);
    }
    updateField(['values', ...path], value)
  },
});

export default connect(mapStateToProps, mapDispatchToProps)(GeneratePaymentFileForm);