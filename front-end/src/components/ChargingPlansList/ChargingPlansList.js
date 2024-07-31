import React from 'react';
import EntityList from '../EntityList';

const ChargingPlansList = () => {
  const filterFields = [
    { id: 'description', placeholder: 'Title' },
    { id: 'name', placeholder: 'Key' },
  ];

  const tableFields = [
    { id: 'description', title: 'Title', sort: true },
    { id: 'name', title: 'Key', sort: true },
    { id: 'operation', title: 'Operation', sort: true },
    { id: 'charging_value', title: 'Charging value', sort: true },
  ];

  const projectFields = {
    charging_value: 1,
    description: 1,
    operation: 1,
    name: 1,
  };

  const actions = [
    { type: 'edit' },
  ];

  return (
    <EntityList
      collection="prepaidgroups"
      itemType="charging_plan"
      itemsType="charging_plans"
      filterFields={filterFields}
      tableFields={tableFields}
      projectFields={projectFields}
      showRevisionBy="name"
      actions={actions}
    />
  );
};

export default ChargingPlansList;
