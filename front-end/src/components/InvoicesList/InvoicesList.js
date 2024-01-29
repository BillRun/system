import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import moment from 'moment';
import Immutable from 'immutable';
import { Col, Row, Panel } from 'react-bootstrap';
/* COMPONENTS */
import Pager from '../Pager';
import Filter from '../Filter';
import List from '../List';
/* ACTIONS */
import { getList, clearList } from '@/actions/listActions';
import { getConfig } from '@/common/Util';


class InvoicesList extends Component {

  static propTypes = {
    settings: PropTypes.instanceOf(Immutable.Map),
    items: PropTypes.instanceOf(Immutable.List),
    collection: PropTypes.string,
    baseFilter: PropTypes.object,
    dispatch: PropTypes.func.isRequired,
  }

  static defaultProps = {
    settings: Immutable.Map(),
    items: Immutable.List(),
    baseFilter: {},
    collection: 'bills',
  }

  state = {
    page: 0,
    size: 10,
    sort: Immutable.Map({ invoice_id: -1 }),
    filter: {},
  };

  componentWillUnmount() {
    this.props.dispatch(clearList('invoices'));
  }

  buildQuery = () => {
    const { collection } = this.props;
    const { page, size, filter, sort } = this.state;

    const query = Object.assign({}, filter, { action: 'query_bills_invoices', type: 'inv' });
    if (query.aid) {
      query.aid = { $in: [query.aid] };
    }
    if (query.invoice_id) {
      query.invoice_id = { $in: [query.invoice_id] };
    }

    return {
      entity: collection,
      action: 'get',
      params: [
        { size },
        { page },
        { sort: JSON.stringify(sort) },
        { query: JSON.stringify(query) },
      ],
    };
  }

  onFilter = (filter) => {
    this.setState({ filter, page: 0 }, this.fetchItems);
  }

  handlePageClick = (page) => {
    this.setState({ page }, this.fetchItems);
  }

  onSort = (newSort) => {
    const sort = Immutable.Map(newSort);
    this.setState({ sort }, this.fetchItems);
  }

  fetchItems = () => {
    this.props.dispatch(getList('invoices', this.buildQuery()));
    this.props.dispatch(getList('billrun', this.buildQuery()));
  }

  downloadURL = (aid, billrunKey, invoiceId) =>
    `${getConfig(['env','serverApiUrl'], '')}/api/accountinvoices?action=download&aid=${aid}&billrun_key=${billrunKey}&iid=${invoiceId}`

  renderMainPanelTitle = () => (
    <div>
      <span>
        List of all invoices
      </span>
    </div>
  );

  parserPaidBy = (ent) => {
    let paid = ent.get('paid', false);
    if ([true, '1'].includes(paid)) {
      return (<span style={{ color: '#3c763d' }}>Paid</span>);
    }
    else if (paid === '2') {
      return (<span style={{ color: '#5b5e5b' }}>Pending</span>);
    }
    if (moment(ent.get('due_date')).isAfter(moment())) {
      return (<span style={{ color: '#8a6d3b' }}>Due</span>);
    }
    return (<span style={{ color: '#a94442' }}>Not Paid</span>);
  }

  parserDownload = (ent) => {
    const downloadUrl = this.downloadURL(ent.get('aid'), ent.get('billrun_key'), ent.get('invoice_id'));
    return (
      ent.get('invoice_file', false) &&
      <form method="post" action={downloadUrl} target="_blank">
        <input type="hidden" name="a" value="a" />
        <button className="btn btn-link" type="submit">
          <i className="fa fa-download" /> Download
        </button>
      </form>
    );
  };

  parseCycleDataEmailSent = (entity) => {
    const sentDate = entity.getIn(['email_sent', 'sec'], false);
    if (!sentDate) {
      return 'Not Sent';
    }
    return moment.unix(sentDate).format(getConfig('datetimeFormat', 'DD/MM/YYYY HH:mm'));
  };

  getTableFields = () => ([
    { id: 'invoice_id', title: 'Invoice Id', sort: true },
    { id: 'invoice_date', title: 'Date', cssClass: 'short-date', sort: true, type: 'mongodate' },
    { id: 'due_date', title: 'Due', cssClass: 'short-date', sort: true, type: 'mongodate' },
    { id: 'amount', title: 'Amount', sort: true },
    { id: 'paid', title: 'Status', parser: this.parserPaidBy },
    { id: 'billrun_key', title: 'Cycle', sort: true },
    { id: 'aid', title: 'Customer ID', sort: true },
    { id: 'payer_name', title: 'Name', sort: true },
    { title: 'Download', parser: this.parserDownload },
    { id: 'email_sent', title: 'Email Sent', sort: true, parser: this.parseCycleDataEmailSent, display: this.props.settings.get('billrun', Immutable.Map()).get('email_after_confirmation', false) },
  ]);

  getFilterFields = () => {
    const { baseFilter } = this.props;
    return ([
      { id: 'aid', placeholder: 'Customer ID', type: 'number', showFilter: !Object.prototype.hasOwnProperty.call(baseFilter, 'aid') },
      { id: 'invoice_id', placeholder: 'Invoice Id', type: 'number', showFilter: !Object.prototype.hasOwnProperty.call(baseFilter, 'invoice_id') },
    ]);
  }

  render() {
    const { items, baseFilter } = this.props;
    const { sort } = this.state;
    const tableFieds = this.getTableFields();
    const filterFields = this.getFilterFields();
    return (
      <div className="InvoicesList">
        <Row>
          <Col lg={12}>
            <Panel header={this.renderMainPanelTitle()}>
              <Filter fields={filterFields} onFilter={this.onFilter} base={baseFilter} />
              <List items={items} fields={tableFieds} onSort={this.onSort} sort={sort} className="invoices-list" />
            </Panel>
          </Col>
        </Row>
        <Pager onClick={this.handlePageClick} size={this.state.size} count={items.size} />
      </div>
    );
  }
}

const mapStateToProps = (state, props) => ({
  settings: state.settings,
  baseFilter: props.location.query.base ? JSON.parse(props.location.query.base) : {},
  items: state.list.get('invoices'),
});

export default connect(mapStateToProps)(InvoicesList);
