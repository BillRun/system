import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import ChangeCase from 'change-case';
import { Col, Row } from 'react-bootstrap';
import Filter from '../Filter';
import List from '../List';
import Pager from '../Pager';
import { getSettings } from '@/actions/settingsActions';
import { getList } from '@/actions/listActions';
import { postpaidBalancesListQuery } from '../../common/ApiQueries';
import { usageTypeSelector } from '@/selectors/settingsSelector';


class PostpaidBalances extends Component {

  static propTypes = {
    items: PropTypes.instanceOf(Immutable.List).isRequired,
    usageTypes: PropTypes.instanceOf(Immutable.List).isRequired,
    aid: PropTypes.number.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    items: Immutable.List(),
    usageTypes: Immutable.List(),
  };

  state = {
    page: 0,
    size: 10,
    sort: Immutable.Map({ sid: 1 }),
    filter: {},
  };


  componentDidMount() {
    const { usageTypes } = this.props;
    if (usageTypes.isEmpty()) {
      this.props.dispatch(getSettings(['usage_types']));
    }
  }

  buildQuery = () => {
    const { size, page, sort, filter } = this.state;
    return postpaidBalancesListQuery(filter, page, sort, size);
  }

  fetchItems = () => {
    this.props.dispatch(getList('postpaid_balances', this.buildQuery()));
  }

  handlePageClick = (page) => {
    this.setState({ page }, this.fetchItems);
  }

  onFilter = (filter) => {
    this.setState({ filter, page: 0 }, this.fetchItems);
  }

  onSort = (newSort) => {
    const sort = Immutable.Map(newSort);
    this.setState({ sort }, this.fetchItems);
  }

  parseTotalCost = ent => (ent.getIn(['balance', 'cost'], 0).toFixed(2));

  getTableFields = () => {
    const { usageTypes } = this.props;
    const usageFields = usageTypes.map(usaget => ({
      id: usaget,
      placeholder: ChangeCase.titleCase(usaget),
      showFilter: false,
      parser: ent => ent.getIn(['balance', 'totals', usaget, 'usagev'], ''),
    })).toJS();

    return ([
      { id: 'aid', placeholder: 'Customer', type: 'number', sort: true, showFilter: false, display: false },
      { id: 'sid', placeholder: 'Subscriber', type: 'number', sort: true },
      { id: 'plan_description', placeholder: 'Plan' },
      { id: 'balance.cost', placeholder: 'Total Cost', type: 'number', showFilter: false, parser: this.parseTotalCost },
      ...usageFields,
      { id: 'from', placeholder: 'From', showFilter: false, type: 'datetime' },
      { id: 'to', placeholder: 'To', showFilter: false, type: 'datetime' },
    ]);
  }

  render() {
    const { items, aid } = this.props;
    const { sort } = this.state;
    const fields = this.getTableFields();
    const baseFilter = { aid, connection_type: 'postpaid' };
    return (
      <div className="Postpaid-Balances">
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

const mapStateToProps = (state, props) => ({
  items: state.list.get('postpaid_balances'),
  usageTypes: usageTypeSelector(state, props),
});

export default connect(mapStateToProps)(PostpaidBalances);
