import React from 'react';
import EntityList from '../EntityList';
import { getFieldName } from '@/common/Util';


const filterFields = [
  { id: 'description', placeholder: 'Title' },
  { id: 'key', placeholder: 'Key' },
];

const parseType = (item) => item.get('type', '') === 'percentage'
  ? getFieldName('type_percentage', 'charge')
  : getFieldName('type_monetary', 'charge');

const tableFields = [
  { id: 'description', title: 'Title', sort: true },
  { id: 'key', title: 'Key', sort: true },
  { id: 'type', title: 'Type', sort: true, parser: parseType },
];

const projectFields = {
  key: 1,
  description: 1,
  type: 1,
};

const actions = [
  { type: 'edit' },
];


const ChargesList = () => (
  <EntityList
    entityKey="charge"
    filterFields={filterFields}
    tableFields={tableFields}
    projectFields={projectFields}
    actions={actions}
  />
);

export default ChargesList;
