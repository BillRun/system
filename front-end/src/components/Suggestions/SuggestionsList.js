import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import { Row, Col, Panel } from 'react-bootstrap';
import isNumber from 'is-number';
import moment from 'moment';
import getSymbolFromCurrency from 'currency-symbol-map';
import { Actions } from '@/components/Elements';
import List from '@/components/List';
import Field from '@/components/Field';
import { chargingDaySelector } from '@/selectors/settingsSelector';
import {
  getConfig,
} from '@/common/Util';
import {
  statusParser,
} from '@/common/Parsers';
import { idSelector } from '@/selectors/entitySelector';
import {
  getSuggestionsByAid,
  rejectSuggestion,
  rejectSuggestions,
  getCyclesDetails,
  rebalanceSuggestion,
  creditSuggestion,
} from '@/actions/suggestionsActions';
import { currencySelector } from '@/selectors/settingsSelector';
import { showConfirmModal } from '@/actions/guiStateActions/pageActions';


class SuggestionsList extends Component {

  static propTypes = {
    aid: PropTypes.number,
    items: PropTypes.instanceOf(Immutable.List),
    currency: PropTypes.string,
    chargingDay: PropTypes.number,
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    aid: null,
    items: Immutable.List(),
    currency: '',
    chargingDay: 1,
  }

  static dateFormat = getConfig('dateFormat', 'dd/MM/yyyy');

  state = {
    selectedIds: Immutable.List(),
    cycles: Immutable.Map(),
    filteredStatus: Immutable.List(['open']),
    rebalanceInProcess: false,
    creditInProcess: false,
  }

  componentDidMount() {
    const { items } = this.props;
    this.getSuggestions();
    this.setCycles(items);
  }

  componentDidUpdate(prevProps, prevState) {
    const { items } = this.props;
    if (!Immutable.is(items, prevProps.items) && !items.isEmpty()) {
      this.setCycles(items);
    }
  }

  setCycles = (items) => {
    const cycles = items.map(item => {
      const billrunKey = item.get('billrun_key', null);
      if (billrunKey !== null) {
        return billrunKey;
      }
      return item.get('estimated_billrun', '');
    })
    .filter(val => ![null, ''].includes(val))
    .toSet()
    .toList();
    this.props.dispatch(getCyclesDetails(cycles))
      .then((cycles) => {
        this.setState(() => ({ cycles }));
      });
  }

  onClickRefresh = () => {
    this.getSuggestions();
    this.setState(() => ({ selectedIds: Immutable.List() }));
  }

  onClickReject = (suggestion) => {
    const confirm = {
      message: `Are you sure you want to reject suggestion "${suggestion.get('description', '')}" ?`,
      onOk: () => this.props.dispatch(rejectSuggestion(suggestion)),
      type: 'delete',
      labelOk: 'Reject',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onClickMassReject = () => {
    const { selectedIds, } = this.state;
    const { items } = this.props;
    const selectedItems = items.filter(item => selectedIds.includes(item.getIn(['_id', '$id'], 'undefined')));

    const list = selectedItems
      .map(selectedItem => selectedItem.get('description', ''))
      .map((label, idx) => (<li key={idx}>{label}</li>));

    const onOk = () => {
      this.setState(() => ({ selectedIds: Immutable.List() }));
      this.props.dispatch(rejectSuggestions(selectedItems));
    }

    const confirm = {
      message: `Are you sure you want to reject suggestions ?`,
      children: (<ul>{list}</ul>),
      onOk,
      type: 'delete',
      labelOk: 'Reject',
    };
    this.props.dispatch(showConfirmModal(confirm));
  }

  onBack = () => {
    this.props.router.push('suggestions');
  }

  onClickRebalance = (suggestion) => {
    this.setState(() => ({ rebalanceInProcess: true }));
    this.props.dispatch(rebalanceSuggestion(suggestion))
      .then(() => {
        this.getSuggestions();
        this.setState(() => ({ rebalanceInProcess: false }));
      });
  }

  onClickCredit = (suggestion) => {
    this.setState(() => ({ creditInProcess: true }));
    this.props.dispatch(creditSuggestion(suggestion))
      .then(() => {
        this.getSuggestions();
        this.setState(() => ({ creditInProcess: false }));
      });
  }

  onSelectChecked = (e) => {
    const { value, id } = e.target;
    const { selectedIds } = this.state;
    const newIds = (value) ? selectedIds.push(id) : selectedIds.filter((selectedId) => selectedId !== id);
    this.setState(() => ({ selectedIds: newIds }));
  }

  onFilterStatus = (e) => {
    const { filteredStatus } = this.state;
    const { value, id } = e.target;
    const newSates = (value) ? filteredStatus.push(id) : filteredStatus.filter(state => state !== id);
    this.setState(() => ({ filteredStatus: newSates, selectedIds: Immutable.List() }));
  }

  getSuggestions = () => {
    const { aid } = this.props;
    if (aid !== null) {
      this.setState(() => ({ creditInProcess: true }));
      return this.props.dispatch(getSuggestionsByAid(aid))
        .then(() => {
          this.setState(() => ({ creditInProcess: false }));
        });
    }
  }

  cycleParser = (item) => {
    const { chargingDay } = this.props;
    let billrunKey = item.get('billrun_key', null);
    if (billrunKey !== null && billrunKey !== '') {
      const cycleDay = `${chargingDay}`.padStart(2, '0');
      const cycleDate = `${billrunKey}${cycleDay}`;
      const cycleEndDate = moment(cycleDate, 'YYYYMMDD').subtract(1, 'days').format(SuggestionsList.dateFormat);
      return `cycle ends ${cycleEndDate}`;
    }
    const from = item.get('from', null);
    const to = item.get('to', null);
    if (from !== null && to !== null) {
      return `${moment(from).format(SuggestionsList.dateFormat)} - ${moment(to).format(SuggestionsList.dateFormat)}`;
    }
    return '-';
  }

  isOpen = (item) => item.get('status', '') === 'open';

  isRejectable = () => {
    const { selectedIds } = this.state;
    return !selectedIds.isEmpty();
  };

  isRebalanceable = (item) => {
    const { cycles } = this.state;
    if (!this.isOpen(item)) {
      return false;
    }
    const billrunKey = item.get('billrun_key', null);
    if (billrunKey) {
      return ['current', 'to_run'].includes(cycles.get(billrunKey, ''));
    }
    const estimatedBillrun = item.get('estimated_billrun', null);
    if (estimatedBillrun) {
      return ['current', 'to_run', 'confirmed'].includes(cycles.get(estimatedBillrun, ''));
    }
    return false;
  };

  selectParser = (item) => {
    const { selectedIds } = this.state;
    if (!this.isOpen(item)) {
      return null;
    }
    const itemId = item.getIn(['_id', '$id'], 'undefined');
    const isSelected = selectedIds.includes(itemId);
    return (
      <Field
        id={itemId}
        value={isSelected}
        onChange={this.onSelectChecked}
        fieldType="checkbox"
      />
    );
  }

  amountParser = (item) => {
    const { currency } = this.props;
    const amount = item.get('amount', '');
    if (isNumber(amount)) {
      return `${amount}${getSymbolFromCurrency(currency)}`
    }
    return '';
  }

  getTableItems = () => {
    const { items } = this.props;
    const { filteredStatus } = this.state;
    return items
      .filter(item => filteredStatus.includes(item.get('status', '')))
      .sort((a, b) => {
        if (a.get('status', '') === 'open') {
          return -1;
        }
        if (b.get('status', '') === 'open') {
          return 1;
        }
        if (a.get('status', '') === 'reject' && b.get('status', '') === 'accept') {
          return 1;
        }
        if (a.get('status', '') === 'accept' && b.get('status', '') === 'reject') {
          return -1;
        }
        return 0
      });
  }

  getTableFields = () => ([
    { id: '', placeholder: '', parser: this.selectParser, cssClass: 'text-center' },
    { id: 'description', placeholder: 'Name' },
    { id: 'sid', placeholder: 'Subscriber ID' },
    { id: 'amount', placeholder: 'Amount', parser: this.amountParser },
    { id: 'status', placeholder: 'Status', parser: statusParser},
    { id: 'billrun_key', placeholder: 'Cycle', parser: this.cycleParser},
  ]);

  getRowActions = () => {
    const { rebalanceInProcess, creditInProcess } = this.state;
    return ([{
      type: '',
      showIcon: false,
      label: 'Rebalance',
      onClick: this.onClickRebalance,
      show: this.isRebalanceable,
      enable: !rebalanceInProcess,
      actionStyle: 'primary',
      actionSize: 'xsmall',
    }, {
      type: '',
      showIcon: false,
      label: 'Reject',
      onClick: this.onClickReject,
      show: this.isOpen,
      actionStyle: 'danger',
      actionSize: 'xsmall',
    }, {
      type: '',
      showIcon: false,
      label: 'Credit',
      onClick: this.onClickCredit,
      enable: !creditInProcess,
      show: true,
      actionStyle: 'primary',
      actionSize: 'xsmall',
    }]);
  }

  getPageActions = () => [{
    type: 'back',
    label: 'Back To List',
    onClick: this.onBack,
    actionStyle: 'primary',
    
  }];

  getListActions = () => [{
    type: 'refresh',
    label: 'Refresh',
    actionStyle: 'primary',
    showIcon: true,
    onClick: this.onClickRefresh,
    actionSize: 'xsmall',
  }, {
    type: '',
    showIcon: false,
    label: 'Reject',
    enable: this.isRejectable,
    onClick: this.onClickMassReject,
    actionStyle: 'danger',
    actionSize: 'xsmall',
  }, {
    type: '',
    showIcon: false,
    label: 'Create an Immediate Invoice',
    onClick: this.onClickRemove,
    actionStyle: 'primary',
    actionSize: 'xsmall',
    show: false,
  }];

  renderPanelHeader = () => {
    const { filteredStatus, selectedIds } = this.state;
    return (
      <Row>
        <Col sm={6}>
          <Field
            id="open"
            fieldType="checkbox"
            value={filteredStatus.includes("open")}
            label="Open"
            onChange={this.onFilterStatus}
            className="inline mr5"
          />
          <Field
            id="accept"
            fieldType="checkbox"
            value={filteredStatus.includes("accept")}
            label="Accepted"
            onChange={this.onFilterStatus}
            className="inline mr5"
          />
          <Field
            id="reject"
            fieldType="checkbox"
            value={filteredStatus.includes("reject")}
            label="Rejected"
            onChange={this.onFilterStatus}
            className="inline mr5"
          />
        </Col>
        <Col sm={6} className="text-right">
          <Actions actions={this.getListActions()} data={selectedIds}/>
        </Col>
      </Row>
    );
  }

  render() {
    const tableItems = this.getTableItems();
    const actions = this.getRowActions();
    const fields = this.getTableFields();
    return (
      <Row>
        <Col lg={12} >
          <Panel header={this.renderPanelHeader()}>
            <List
              items={tableItems}
              fields={fields}
              actions={actions}
            />
          </Panel>
          <Actions actions={this.getPageActions()} />
        </Col>
      </Row>
    );
  }

}

const mapStateToProps = (state, props) => {
  const itemId = idSelector(state, props);
  return ({
    aid: isNumber(itemId) ? parseFloat(itemId) : undefined,
    items: state.list.get(`suggestions_${itemId}`),
    currency: currencySelector(state, props),
    chargingDay: chargingDaySelector(state, props),
  });
}

export default withRouter(connect(mapStateToProps)(SuggestionsList));
