import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import changeCase from 'change-case';
import EntityList from '../EntityList';
import { isPlaysEnabledSelector } from '@/selectors/settingsSelector';
import { getFieldName } from '@/common/Util';


const PlansList = (props) => {
  const parserTrial = (item) => {
    if (item.getIn(['price', 0, 'trial'])) {
      return `${item.getIn(['price', 0, 'to'])} ${item.getIn(['recurrence', 'periodicity'])}`;
    }
    return '';
  };

  const parserRecuringCharges = (item) => {
    const sub = item.getIn(['price', 0, 'trial']) ? 1 : 0;
    const cycles = item.get('price', Immutable.List()).size - sub;
    return `${cycles} cycles`;
  };

  const parserBillingFrequency = (item) => {
    if (item.hasIn(['recurrence', 'periodicity'])) {
      const periodicity = item.getIn(['recurrence', 'periodicity'], '');
      return (!periodicity) ? '' : `${changeCase.upperCaseFirst(periodicity)}ly`;
    }
    const frequency = item.getIn(['recurrence', 'frequency'], '')
    return getFieldName('recurrence.periodicity.1', '', frequency);
  };

  const parserChargingMode = item => (item.get('upfront') ? 'Upfront' : 'Arrears');

  const parsePlay = item => item.get('play', Immutable.List()).join(', ');

  const displayPlay = () => props.isPlaysEnabled;

  const tableFields = [
    { id: 'description', title: 'Title', sort: true },
    { id: 'name', title: 'Key', sort: true },
    { title: 'Trial', parser: parserTrial },
    { id: 'recurrence_charges', title: 'Recurring Charges', parser: parserRecuringCharges },
    { id: 'recurrence_frequency', title: 'Billing Frequency', parser: parserBillingFrequency },
    { id: 'charging_mode', title: 'Charging Mode', parser: parserChargingMode },
    { id: 'connection_type', display: false, showFilter: false },
    { id: 'play', title: 'Play', display: displayPlay(), parser: parsePlay },
  ];

  const filterFields = [
    { id: 'name', placeholder: 'Key' },
    { id: 'description', placeholder: 'Title' },
  ];

  const projectFields = {
    recurrence_frequency: 1,
    recurrence_charges: 1,
    connection_type: 1,
    charging_mode: 1,
    description: 1,
    recurrence: 1,
    upfront: 1,
    price: 1,
    play: 1,
    name: 1,
  };

  const baseFilter = {
    connection_type: { $regex: '^postpaid$' },
  };

  const actions = [
    { type: 'edit' },
  ];

  return (
    <EntityList
      itemType="plan"
      itemsType="plans"
      filterFields={filterFields}
      baseFilter={baseFilter}
      tableFields={tableFields}
      projectFields={projectFields}
      showRevisionBy="key"
      actions={actions}
    />
  );
};


PlansList.propTypes = {
  isPlaysEnabled: PropTypes.bool,
};

PlansList.defaultProps = {
  isPlaysEnabled: false,
};

const mapStateToProps = (state, props) => ({
  isPlaysEnabled: isPlaysEnabledSelector(state, props),
});

export default connect(mapStateToProps)(PlansList);
