import React from 'react';
import Immutable from 'immutable';
import { Form } from 'react-bootstrap';
import { EntityFields } from '@/components/Entity';

const PaymentFileDetails = ({ item }) => {
  const fieldsConfig = item.get('fields', Immutable.List());
  if (fieldsConfig.isEmpty()) {
    return 'No additional data required to generate file';
  }
  return (
    <Form horizontal>
      <EntityFields
        entityName="payments"
        entity={item.get('values', Immutable.Map())}
        fields={fieldsConfig}
        editable={false}
      />
    </Form>
  );
};

export default PaymentFileDetails;
