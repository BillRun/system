import React, { Component } from 'react';
import EntityList from '../../EntityList';
import { getConfig } from '@/common/Util';

export default class ExchangeRatesList extends Component {

  getFields = () => ([
    { id: 'base_currency', title: 'Base Currency', sort: true },
    { id: 'target_currency', title: 'Target Currency', sort: true },
    { id: 'rate', title: 'Rate', sort: true },
  ]);

  getProjectFields = () => ({
    base_currency: 1,
    target_currency: 1,
    rate: 1,
  });

  getActions = () => [
    { type: 'edit' },
  ];

  render() {
    return (
      <EntityList
        itemsType={getConfig(['systemItems', 'exchangerate', 'itemsType'], 'exchangerates')}
        itemType={getConfig(['systemItems', 'exchangerate', 'itemType'], 'exchangerate')}
        tableFields={this.getFields()}
        projectFields={this.getProjectFields()}
        showRevisionBy="target_currency"
        actions={this.getActions()}
      />
    );
  }
}
