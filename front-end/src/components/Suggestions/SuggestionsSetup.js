import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import getSymbolFromCurrency from 'currency-symbol-map';
import EntityList from '../EntityList';
import {
  getFieldName,
  getRateUsaget,
  getRateByKey,
  getRateUnit,
  getUnitLabel,
  getValueByUnit,
} from '@/common/Util';
import {
  statusParser,
  rateTitleParser,
} from '@/common/Parsers';
import { idSelector } from '@/selectors/entitySelector';
import { suggestionsRatesSelector } from '@/selectors/listSelectors';
import {
  currencySelector,
  usageTypesDataSelector,
  propertyTypeSelector,
} from '@/selectors/settingsSelector';
import { getSuggestionRates } from '@/actions/suggestionsActions'

class SuggestionsSetup extends Component {

  static propTypes = {
    itemId: PropTypes.string,
    currency: PropTypes.string,
    items: PropTypes.instanceOf(Immutable.List),
    suggestionsRates: PropTypes.instanceOf(Immutable.List),
    suggestionsRatesKeys: PropTypes.instanceOf(Immutable.List),
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
  };

  static defaultProps = {
    itemId: '',
    currency: '',
    items: Immutable.List(),
    suggestionsRates: Immutable.List(),
    suggestionsRatesKeys: Immutable.List(),
    usageTypesData: Immutable.List(),
    propertyTypes: Immutable.List(),
  }

  static projectFields = {
    aid: 1,
    sid: 1,
    key: 1,
    type: 1,
    usagev: 1,
    status: 1,
    amount: 1,
    lastname: 1,
    firstname: 1,
    description: 1,
  };

  static filterFields = [
    { id: 'key', placeholder: getFieldName('key', 'suggestions') },
    { id: 'billrun_key', placeholder: getFieldName('billrun_key', 'suggestions') },
    { id: 'status', placeholder: getFieldName('status', 'suggestions')},
    { id: 'sid', placeholder: getFieldName('sid', 'suggestions'), type: 'number' },
    { id: 'aid', placeholder: getFieldName('aid', 'suggestions'), type: 'number' },
  ];

  componentDidUpdate(prevProps, prevState) {
    const { items, suggestionsRatesKeys } = this.props;
    if (!Immutable.is(items, prevProps.items) && !items.isEmpty()) {
      const nextRatesKes = items.map(item => item.get('key', ''));
      const ratesToAdd = nextRatesKes.toSet().subtract(suggestionsRatesKeys.toSet());
      if (!ratesToAdd.isEmpty()) {
        this.props.dispatch(getSuggestionRates(ratesToAdd))
      }
    }
  }

  customerParser = (item) => `${item.getIn(['firstname'], '')} ${item.getIn(['lastname'], '')}`;

  amountParser = (item) => {
    const { currency } = this.props;
    const currencySymbol = getSymbolFromCurrency(currency);
    return `${item.getIn(['amount'], '')}${currencySymbol}`;
  }

  onShowDetails = (item) => {
    const aid = item.get('aid', '');
    this.props.router.push(`suggestions/${aid}`);
  }

  usagevParser = (item) => {
    const { usageTypesData, propertyTypes, suggestionsRates } = this.props;
    const rateKey = item.get('key', '');
    const rate = getRateByKey(suggestionsRates, rateKey);
    const usaget = getRateUsaget(rate);
    const unit = getRateUnit(rate, usaget);
    const value = item.get('usagev', '');
    const usagev = getValueByUnit(propertyTypes, usageTypesData, usaget, unit, value, false);
    const unitLabel = getUnitLabel(propertyTypes, usageTypesData, usaget, unit);
    return `${usagev} ${unitLabel}`;
  }

  tableFields = () => [
    { id: 'name', title: getFieldName('Name', 'suggestions'), parser: this.customerParser, sort: true },
    { id: 'aid', title: getFieldName('aid', 'suggestions'), sort: true },
    { id: 'sid', title: getFieldName('sid', 'suggestions'), sort: true },
    { id: 'key', title: getFieldName('key', 'suggestions'), parser: rateTitleParser, sort: true },
    { id: 'usagev', title: getFieldName('usagev', 'suggestions'), parser: this.usagevParser, sort: true },
    { id: 'status', title: getFieldName('status', 'suggestions'), parser: statusParser, sort: true },
    { id: 'amount', title: getFieldName('amount', 'suggestions'), parser: this.amountParser, sort: true },
    { id: 'type', title: getFieldName('type', 'suggestions'), sort: true },
  ];

  getRowActions = () => [
    { type: "view", onClick: this.onShowDetails, onClickColumn: "name" },
  ];

  getListActions = () => [{
    type: 'refresh',
  }];

  render() {
    return (
      <EntityList
        entityKey="suggestions"
        api="get"
        filterFields={SuggestionsSetup.filterFields}
        tableFields={this.tableFields()}
        actions={this.getRowActions()}
        listActions={this.getListActions()}
        projectFields={SuggestionsSetup.projectFields}
      />
    );
  }

}

const mapStateToProps = (state, props) => {
  const suggestionsRates = suggestionsRatesSelector(state, props);
  const suggestionsRatesKeys = Immutable.List.isList(suggestionsRates) ? suggestionsRates.map(rate => rate.get('key', '')) : Immutable.List();
  return ({
    items: state.entityList.items.get('suggestions'), 
    itemId: idSelector(state, props),
    currency: currencySelector(state, props),
    usageTypesData: usageTypesDataSelector(state, props),
    propertyTypes: propertyTypeSelector(state, props),
    suggestionsRates,
    suggestionsRatesKeys,
  });
}

export default withRouter(connect(mapStateToProps)(SuggestionsSetup));
