import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import pluralize from 'pluralize';
import { titleCase } from 'change-case';
import EntityList from '../EntityList';
import {
  getConfig,
  convertServiceBalancePeriodToObject,
} from '@/common/Util';
import { isPlaysEnabledSelector } from '@/selectors/settingsSelector';


const ServicesList = (props) => {
  const parserPrice = item => item.getIn(['price', 0, 'price'], '');

  const parserQuantitative = item => (item.get('quantitative', false) ? 'Yes' : 'No');

  const parserPeriod = (item) => {
    const period = convertServiceBalancePeriodToObject(item);
    if (period.type === 'default') {
      const unlimited = getConfig('serviceCycleUnlimitedValue', 'UNLIMITED');
      const cycle = item.getIn(['price', 0, 'to'], '');
      return cycle === unlimited ? 'Infinite' : `${cycle} ${titleCase(pluralize('cycle', Number(cycle)))}`;
    }
    return `${period.value} ${titleCase(pluralize(period.unit, Number(period.value)))}`;
  };

  const parsePlay = item => item.get('play', Immutable.List()).join(', ');

  const displayPlay = () => props.isPlaysEnabled;

  const filterFields = [
    { id: 'description', placeholder: 'Title' },
    { id: 'name', placeholder: 'Key' },
  ];

  const tableFields = [
    { id: 'description', title: 'Title', sort: true },
    { id: 'name', title: 'Key', sort: true },
    { title: 'Price', parser: parserPrice, sort: true },
    { title: 'Quantitative', parser: parserQuantitative, sort: true },
    { title: 'Period', parser: parserPeriod, sort: true },
    { id: 'play', title: 'Play', display: displayPlay(), parser: parsePlay },
  ];

  const projectFields = {
    description: 1,
    price: 1,
    name: 1,
    balance_period: 1,
    quantitative: 1,
    play: 1,
  };

  const actions = [
    { type: 'edit' },
  ];

  return (
    <EntityList
      itemsType="services"
      itemType="service"
      filterFields={filterFields}
      tableFields={tableFields}
      projectFields={projectFields}
      showRevisionBy="name"
      actions={actions}
    />
  );
};

ServicesList.propTypes = {
  isPlaysEnabled: PropTypes.bool,
};

ServicesList.defaultProps = {
  isPlaysEnabled: false,
};

const mapStateToProps = (state, props) => ({
  isPlaysEnabled: isPlaysEnabledSelector(state, props),
});

export default connect(mapStateToProps)(ServicesList);
