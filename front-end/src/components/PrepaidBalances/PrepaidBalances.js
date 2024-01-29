import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import ChangeCase from 'change-case';
import { Col, Row } from 'react-bootstrap';
import Filter from '../Filter';
import List from '../List';
import Pager from '../Pager';
import { prepaidBalancesListQuery } from '../../common/ApiQueries';
import { getList } from '@/actions/listActions';


class PrepaidBalances extends Component {

  static propTypes = {
    items: PropTypes.instanceOf(Immutable.List).isRequired,
    aid: PropTypes.number.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    items: Immutable.List(),
  };

  state = {
    size: 10,
    page: 0,
    sort: Immutable.Map({ sid: 1 }),
    filter: {},
  };


  onFilter = (filter) => {
    this.setState({ filter, page: 0 }, this.fetchItems);
  }

  onSort = (newSort) => {
    const sort = Immutable.Map(newSort);
    this.setState({ sort }, this.fetchItems);
  }

  fetchItems = () => {
    this.props.dispatch(getList('prepaid_balances', this.buildQuery()));
  }

  handlePageClick = (page) => {
    this.setState({ page }, this.fetchItems);
  }

  buildQuery = () => {
    const { size, page, sort, filter } = this.state;
    return prepaidBalancesListQuery(filter, page, sort, size);
  }

  parserUsageTypeName = ent => ChangeCase.titleCase(ent.get('charging_by_usaget', ''));

  parserUsageTypeValue = (ent) => {
    const usaget = ent.get('charging_by_usaget');
    const chargingBy = ent.get('charging_by');
    const balanceKey = (usaget === 'total_cost' ? ['cost'] : ['totals', usaget, chargingBy]);
    return ent.getIn(['balance', ...balanceKey], 0).toFixed(2);
  }

  getTableFields = () => ([
    { id: 'aid', placeholder: 'Customer', type: 'number', sort: true, showFilter: false, display: false },
    { id: 'sid', placeholder: 'Subscriber', type: 'number', sort: true },
    { id: 'pp_includes_name', placeholder: 'Bucket', sort: true },
    { id: 'charging_by_usaget', placeholder: 'Usage Type Name', sort: true, showFilter: false, parser: this.parserUsageTypeName },
    { id: 'usaget_type_value', placeholder: 'Usage Type Value', sort: true, showFilter: false, parser: this.parserUsageTypeValue },
    { id: 'from', placeholder: 'From', showFilter: false, type: 'datetime' },
    { id: 'to', placeholder: 'To', showFilter: false, type: 'datetime' },
  ]);

  render() {
    const { aid, items } = this.props;
    const { sort } = this.state;
    const baseFilter = { aid, connection_type: 'prepaid' };
    const fields = this.getTableFields();
    return (
      <div className="Prepaid-Balances">
        <Row>
          <Col lg={12}>
            <Filter fields={fields} onFilter={this.onFilter} base={baseFilter} />
            <List items={items} fields={fields} onSort={this.onSort} sort={sort} />
          </Col>
        </Row>
        <Pager onClick={this.handlePageClick} size={this.state.size} count={items.size} />
      </div>
    );
  }
}


const mapStateToProps = state => ({
  items: state.list.get('prepaid_balances'),
});

export default connect(mapStateToProps)(PrepaidBalances);
