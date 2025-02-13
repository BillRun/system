import React from 'react';
import EntityList from '../EntityList';
import { getFieldName } from '@/common/Util';

const projectFields = {
  key: 1,
  description: 1,
  type: 1,
};

const parseType = (item) => item.get('type', '') === 'percentage'
  ? getFieldName('type_percentage', 'discount')
  : getFieldName('type_monetary', 'discount')


const tableFields = [
  { id: 'description', sort: true },
  { id: 'key', sort: true },
  { id: 'type', sort: true, parser: parseType },
];

const actions = [
  { type: 'edit' },
];

const DiscountsList = () => (
  <EntityList
    entityKey="discount"
    tableFields={tableFields}
    projectFields={projectFields}
    actions={actions}
  />
);

export default DiscountsList;
