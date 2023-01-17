import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import moment from 'moment';
import { Col, Row, Panel } from 'react-bootstrap';
import List from '../List'; //TODO: move to entityList
import Pager from '../Pager'; //TODO: move to entityListPager
import { AdvancedFilter } from '../Filter'; //TODO: move to entityListFilter
import DetailsParser from './DetailsParser';
import {
  getUserKeysQuery,
  auditTrailListQuery,
} from '../../common/ApiQueries';
import { getList, clearList } from '@/actions/listActions';
import {
  userNamesSelector,
  auditlogSelector,
  auditEntityTypesSelector,
} from '@/selectors/listSelectors';


class AuditTrail extends Component {

  static propTypes = {
    items: PropTypes.instanceOf(Immutable.List),
    userNames: PropTypes.instanceOf(Immutable.List),
    auditTrailEntityTypes: PropTypes.instanceOf(Immutable.List),
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    items: Immutable.List(),
    userNames: Immutable.List(),
    auditTrailEntityTypes: Immutable.List(),
  }

  state = {
    fields: {},
    page: 0,
    size: 10,
    sort: Immutable.Map({ urt: -1 }),
    filter: {},
  };

  componentDidMount() {
    this.fetchUser();
  }

  componentWillReceiveProps(nextProps) {
    if (!Immutable.is(this.props.userNames, nextProps.userNames)) {
      this.fetchItems();
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearList('audit'));
    this.props.dispatch(clearList('autocompleteUser'));
  }

  onSort = (newSort) => {
    const sort = Immutable.Map(newSort);
    this.setState({ sort }, this.fetchItems);
  }

  onFilter = (filter) => {
    this.setState({ filter, page: 0 }, this.fetchItems);
  }

  fetchItems = () => {
    this.props.dispatch(getList('audit', this.buildQuery()));
  }

  fetchUser = () => {
    const query = getUserKeysQuery();
    this.props.dispatch(getList('autocompleteUser', query));
  }

  handlePageClick = (page) => {
    this.setState({ page }, this.fetchItems);
  }

  buildQuery = () => {
    const { fields, size, page, sort, filter } = this.state;
    const query = Object.assign({}, filter);
    query.source = 'audit';
    if (query.urt) {
      query.urt = this.urtQueryBuilder(query.urt);
    }
    if (!isNaN(query.key)) {
      query.key = { $in: [query.key, Number(query.key)] };
    } else if (query.key) {
      query.key = { $regex: query.key, $options: 'i' };
    }
    return auditTrailListQuery(query, page, fields, sort, size);
  }

  userParser = item => item.getIn(['user', 'name'], '');

  collectionParser = item => {
    const {auditTrailEntityTypes} = this.props;
    const collection = item.get('collection', '');
    return auditTrailEntityTypes.find(type => type.key === collection, null, {val: collection}).val;
  }

  keyParser = item => (item.get('type', '') === 'login' ? item.get('key', '').split('login_')[1] : item.get('key', ''));

  detailsParser = item => <DetailsParser item={item} />

  urtQueryBuilder = (date) => {
    const fromDate = moment(date).startOf('day');
    const toDate = moment(date).add(1, 'days');
    return ({ $gte: fromDate, $lt: toDate });
  };

  getFilterFields = () => {
    const { userNames, auditTrailEntityTypes } = this.props;
    return ([
      { id: 'urt', title: 'Date', type: 'date' },
      { id: 'user.name', title: 'User', type: 'select', options: userNames },
      { id: 'collection', title: 'Entity Type', type: 'select', options: auditTrailEntityTypes },
      { id: 'key', title: 'Entity Key' },
    ]);
  }

  getTableFields = () => ([
    { id: 'urt', title: 'Date', type: 'datetime', cssClass: 'long-date', sort: true },
    { id: 'user.name', title: 'User', parser: this.userParser, sort: true },
    { id: 'collection', title: 'Module Type', parser: this.collectionParser, sort: true },
    { id: 'key', title: 'Module Key', parser: this.keyParser, sort: true },
    { title: 'Details', parser: this.detailsParser },
  ]);

  render() {
    const { items } = this.props;
    const { sort } = this.state;
    const filterFields = this.getFilterFields();
    const tableFields = this.getTableFields();
    return (
      <div className="Audit-Trail">
        <Row>
          <Col lg={12}>
            <Panel header={<AdvancedFilter fields={filterFields} onFilter={this.onFilter} />}>
              <List
                items={items}
                fields={tableFields}
                edit={false}
                sort={sort}
                onSort={this.onSort}
              />
            </Panel>
          </Col>
        </Row>
        <Pager onClick={this.handlePageClick} size={this.state.size} count={items.size} />
      </div>
    );
  }
}


const mapStateToProps = state => ({
  items: auditlogSelector(state),
  userNames: userNamesSelector(state),
  auditTrailEntityTypes: auditEntityTypesSelector(state),
});

export default connect(mapStateToProps)(AuditTrail);
