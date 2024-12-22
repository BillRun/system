import React from 'react';
import EntityList from '../EntityList';

const PrepaidPlansList = () => {
  const filterFields = [
    { id: 'description', placeholder: 'Title' },
    { id: 'name', placeholder: 'Key' },
  ];

  const tableFields = [
    { id: 'description', title: 'Title', sort: true },
    { id: 'name', title: 'Key', sort: true },
    { id: 'code', title: 'Code', sort: true },
  ];

  const projectFields = {
    description: 1,
    name: 1,
    code: 1,
  };

  const baseFilter = {
    connection_type: { $regex: '^prepaid$' },
    type: { $regex: '^customer$' },
  };

  const actions = [
    { type: 'edit' },
  ];

  return (
    <EntityList
      collection="plans"
      itemType="prepaid_plan"
      itemsType="prepaid_plans"
      filterFields={filterFields}
      baseFilter={baseFilter}
      tableFields={tableFields}
      projectFields={projectFields}
      showRevisionBy="key"
      actions={actions}
    />
  );
};


export default PrepaidPlansList;
