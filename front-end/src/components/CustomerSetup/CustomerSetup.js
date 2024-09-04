import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';
import Immutable from 'immutable';
import { Tabs, Tab, Panel } from 'react-bootstrap';
import Customer from './Customer';
import Subscriptions from './Subscriptions';
import CustomerAllowances from './CustomerAllowances';
import {
  ActionButtons,
  LoadingItemPlaceholder,
} from '@/components/Elements';
import PostpaidBalances from '../PostpaidBalances';
import PrepaidBalances from '../PrepaidBalances';
import {
  getPlansKeysQuery,
  getServicesKeysWithInfoQuery,
  getPaymentGatewaysQuery,
  getSubscriptionsWithAidQuery,
  getAccountsQuery,
} from '@/common/ApiQueries';
import {
  saveSubscription,
  saveCustomer,
  updateCustomerField,
  removeCustomerField,
  clearCustomer,
  getCustomer,
  getSubscription,
  setCloneSubscription,
  getCustomerByAid,
} from '@/actions/customerActions';
import {
  clearItems,
  getRevisions,
  clearRevisions,
  clearList,
} from '@/actions/entityListActions';
import { getList } from '@/actions/listActions';
import { getSettings } from '@/actions/settingsActions';
import { setPageTitle } from '@/actions/guiStateActions/pageActions';
import { showSuccess, showAlert } from '@/actions/alertsActions';
import {
  modeSelector,
  itemSelector,
  idSelector,
  tabSelector,
  messageSelector,
} from '@/selectors/entitySelector';
import { currencySelector } from '@/selectors/settingsSelector';
import { buildPageTitle, getConfig, getItemId } from '@/common/Util';


class CustomerSetup extends Component {

  static propTypes = {
    itemId: PropTypes.string,
    aid: PropTypes.number,
    mode: PropTypes.string,
    customer: PropTypes.instanceOf(Immutable.Map),
    subscription: PropTypes.instanceOf(Immutable.Map),
    settings: PropTypes.instanceOf(Immutable.Map),
    plans: PropTypes.instanceOf(Immutable.List),
    services: PropTypes.instanceOf(Immutable.List),
    currency: PropTypes.string,
    gateways: PropTypes.instanceOf(Immutable.List),
    allowancesEnabled: PropTypes.bool,
    activeTab: PropTypes.oneOfType([
      PropTypes.string,
      PropTypes.number,
    ]),
    message: PropTypes.object,
    location: PropTypes.shape({
      pathname: PropTypes.string,
      query: PropTypes.object,
    }),
    router: PropTypes.shape({
      push: PropTypes.func.isRequired,
    }).isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    activeTab: 1,
    customer: Immutable.Map(),
    subscription: Immutable.Map(),
    settings: Immutable.Map(),
    gateways: Immutable.List(),
    plans: Immutable.List(),
    services: Immutable.List(),
    currency: '',
    allowancesEnabled: false,
  };

  componentDidMount() {
    const { mode, message, allowancesEnabled } = this.props;
    this.fetchItem();
    this.props.dispatch(getSettings(['subscribers']));
    if (message) {
      this.props.dispatch(showAlert(message.content, message.type));
    }
    if (['clone', 'create'].includes(mode)) {
      const pageTitle = buildPageTitle(mode, 'customer');
      this.props.dispatch(setPageTitle(pageTitle));
    } else {
      this.props.dispatch(getList('available_gateways', getPaymentGatewaysQuery()));
      this.props.dispatch(getList('available_plans', getPlansKeysQuery({ name: 1, play: 1, description: 1, 'include.services': 1 })));
      this.props.dispatch(getList('available_services', getServicesKeysWithInfoQuery()));
    }
    if (allowancesEnabled) {
      this.props.dispatch(getList('available_subscriptions', getSubscriptionsWithAidQuery()));
      this.props.dispatch(getList('available_accounts', getAccountsQuery()));
    }
  }

  componentWillReceiveProps(nextProps) {
    const { customer, mode, itemId } = nextProps;
    const { customer: oldCustomer, itemId: oldItemId, mode: oldMode } = this.props;
    const modeChanged = mode !== oldMode;
    const revisionChanged = getItemId(customer) !== getItemId(oldCustomer);
    if (modeChanged || revisionChanged) {
      const pageTitle = buildPageTitle(mode, 'customer', customer);
      this.props.dispatch(setPageTitle(pageTitle));
    }
    if (itemId !== oldItemId || (mode !== oldMode && mode === 'clone')) {
      this.fetchItem(itemId);
    }
  }

  componentDidUpdate(prevProps, prevState) { // eslint-disable-line no-unused-vars
    const { customer } = this.props;
    const { customer: oldCustomer } = prevProps;
    const olgPg = oldCustomer.getIn(['payment_gateway', 'active'], Immutable.List());
    const pg = customer.getIn(['payment_gateway', 'active'], Immutable.List());
    // if payment gateway was removed, save customer
    if (!olgPg.isEmpty() && pg.isEmpty()) {
      this.onSaveCustomer();
    }
  }

  componentWillUnmount() {
    this.props.dispatch(clearCustomer());
    this.clearSubscriptions();
  }

  fetchItem = (itemId = this.props.itemId) => {
    if(this.props?.location?.query?.aid){
      this.props.dispatch(getCustomerByAid(parseInt(this.props?.location?.query?.aid))).then(this.afterItemReceived);
    } else if (itemId) {
      this.props.dispatch(getCustomer(itemId)).then(this.afterItemReceived);
    }
  }

  afterItemReceived = (response) => {
    if (response.status) {
      //
    } else {
      this.handleBack();
    }
  }

  onChangeCustomerField = (path, value) => {
    this.props.dispatch(updateCustomerField(path, value));
  }

  onRemoveCustomerField = (path) => {
    this.props.dispatch(removeCustomerField(path));
  }

  afterSaveCustomer = (response) => {
    const { mode, customer } = this.props;
    if (response.status) {
      this.props.dispatch(clearItems('customers')); // refetch items list because item was (changed in / added to) list
      const action = (['clone', 'create'].includes(mode)) ? 'created' : 'updated';
      this.props.dispatch(showSuccess(`The customer was ${action}`));
      if (mode === 'create') {
        let customerId;
        if (response && response.data && response.data._id && response.data._id.$id) {
          customerId = response.data._id.$id;
        }
        if (customerId) {
          this.props.router.push(`/customers/customer/${customerId}`);
        }
      }
      const pageTitle = buildPageTitle(mode, 'customer', customer);
      this.props.dispatch(setPageTitle(pageTitle));
    }
  }

  onSaveCustomer = () => {
    const { customer, mode } = this.props;
    this.props.dispatch(saveCustomer(customer, mode)).then(this.afterSaveCustomer);
  }

  onClickChangePaymentGateway = (customer) => {
    const aid = customer.get('aid', null);
    const returnUrlParam = `return_url=${encodeURIComponent(this.getReturnUrl(aid))}`;
    const aidParam = `aid=${encodeURIComponent(aid)}`;
    const action = `action=${encodeURIComponent('updatePaymentGateway')}`;
    window.location = `${getConfig(['env','serverApiUrl'], '')}/internalpaypage?${aidParam}&${returnUrlParam}&${action}`;
  }

  onSaveSubscription = (subscription, mode) =>
    this.props.dispatch(saveSubscription(subscription, mode)).then(this.afterSaveSubscription);


  afterSaveSubscription = (response) => {
    if (response.status) {
      const action = (['clone', 'create'].includes(response.action)) ? 'created' : 'updated';
      this.props.dispatch(showSuccess(`The subscription was ${action}`));
      this.clearSubscriptionRevisions(response.subscription);
      this.clearSubscriptions();
      return true;
    }
    return false;
  }

  clearSubscriptionRevisions = (subscription) => {
    const sid = subscription.get('sid', '');
    const aid = subscription.get('aid', '');
    this.props.dispatch(clearRevisions('subscribers', [sid, aid]));// refetch items list because item was (changed in / added to) list
  }

  clearSubscriptions = () => {
    this.props.dispatch(clearList('subscribers'));
  }

  handleBack = () => {
    const listUrl = getConfig(['systemItems', 'customer', 'itemsType'], '');
    this.props.router.push(`/${listUrl}`);
  }

  getSubscription = (id, mode) => this.props.dispatch(getSubscription(id))
    .then((response) => {
      if (response.status) {
        if (mode === 'clone') {
          this.props.dispatch(setCloneSubscription());
          return this.props.subscription;
        }
        const sid = this.props.subscription.get('sid', '');
        const aid = this.props.subscription.get('aid', '');
        this.props.dispatch(getRevisions('subscribers', ['sid', 'aid'], [sid, aid]));
        return this.props.subscription;
      }
      return null;
    });

  getReturnUrl = (aid=null) => {
    const { itemId } = this.props;
    return `${window.location.origin}/#/customers/customer/${itemId}?tab=1&aid=${aid}`;
  }

  handleSelectTab = (tab) => {
    const { pathname, query } = this.props.location;
    this.props.router.push({
      pathname,
      query: Object.assign({}, query, { tab }),
    });
  }

  render() {
    const {
      customer,
      settings,
      plans,
      services,
      currency,
      gateways,
      mode,
      aid,
      activeTab,
      allowancesEnabled,
    } = this.props;
    const showActionButtons = [1, 5].includes(activeTab);

    if (mode === 'loading') {
      return (<LoadingItemPlaceholder onClick={this.handleBack} />);
    }

    const accountFields = settings.getIn(['account', 'fields'], Immutable.List());
    const subscriberFields = settings.getIn(['subscriber', 'fields'], Immutable.List());

    return (
      <div className="CustomerSetup">
        <div className="row">
          <div className="col-lg-12">
            <Tabs defaultActiveKey={activeTab} animation={false} id="CustomerEditTabs" onSelect={this.handleSelectTab}>
              { !accountFields.isEmpty() &&
                <Tab title="Customer Details" eventKey={1} key={1}>
                  <Panel style={{ borderTop: 'none' }}>
                    <Customer
                      customer={customer}
                      action={mode}
                      supportedGateways={gateways}
                      onChange={this.onChangeCustomerField}
                      onRemoveField={this.onRemoveCustomerField}
                      onChangePaymentGateway={this.onClickChangePaymentGateway}
                    />
                  </Panel>
                </Tab>
              }

              { (mode !== 'create') && !subscriberFields.isEmpty() &&
                <Tab title="Subscribers" eventKey={2}>
                  <Panel style={{ borderTop: 'none' }}>
                    <Subscriptions
                      aid={aid}
                      settings={subscriberFields}
                      allPlans={plans}
                      allServices={services}
                      onSaveSubscription={this.onSaveSubscription}
                      getSubscription={this.getSubscription}
                      clearRevisions={this.clearSubscriptionRevisions}
                      clearList={this.clearSubscriptions}
                    />
                  </Panel>
                </Tab>
              }
              { (mode !== 'create') &&
                <Tab title="Postpaid Counters" eventKey={3}>
                  <Panel style={{ borderTop: 'none' }}>
                    <PostpaidBalances aid={aid} />
                  </Panel>
                </Tab>
              }
              { (mode !== 'create') &&
                <Tab title="Prepaid Counters" eventKey={4}>
                  <Panel style={{ borderTop: 'none' }}>
                    <PrepaidBalances aid={aid} />
                  </Panel>
                </Tab>
              }
              {allowancesEnabled && (
                <Tab title="Allowances" eventKey={5}>
                  <Panel style={{ borderTop: 'none' }}>
                    <CustomerAllowances
                      customer={customer}
                      currency={currency}
                      onChange={this.onChangeCustomerField}
                    />
                  </Panel>
                </Tab>
              )}
            </Tabs>
          </div>
        </div>
        { showActionButtons &&
          <ActionButtons onClickSave={this.onSaveCustomer} onClickCancel={this.handleBack} />
        }
      </div>
    );
  }
}


const mapStateToProps = (state, props) => ({
  itemId: idSelector(state, props, 'customer'),
  customer: itemSelector(state, props, 'customer'),
  subscription: itemSelector(state, props, 'subscription'),
  mode: modeSelector(state, props, 'customer'),
  activeTab: tabSelector(state, props),
  aid: state.entity.getIn(['customer', 'aid']) || undefined,
  settings: state.settings.get('subscribers') || undefined,
  allowancesEnabled: state.settings.getIn(['billrun', 'allowances', 'enabled']) || undefined,
  plans: state.list.get('available_plans') || undefined,
  services: state.list.get('available_services') || undefined,
  gateways: state.list.get('available_gateways') || undefined,
  currency: currencySelector(state, props) || undefined,
  message: messageSelector(state, props),
});

export default withRouter(connect(mapStateToProps)(CustomerSetup));
