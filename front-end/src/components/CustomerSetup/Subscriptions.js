import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import uuid from 'uuid';
import SubscriptionsList from './SubscriptionsList';
import Subscription from './Subscription';
import { getItemId } from '@/common/Util';


export default class Subscriptions extends Component {

  static propTypes = {
    aid: PropTypes.number.isRequired,
    settings: PropTypes.instanceOf(Immutable.List),
    allPlans: PropTypes.instanceOf(Immutable.List),
    allServices: PropTypes.instanceOf(Immutable.List),
    onSaveSubscription: PropTypes.func.isRequired,
    getSubscription: PropTypes.func.isRequired,
    clearRevisions: PropTypes.func.isRequired,
    clearList: PropTypes.func.isRequired,
  };

  static defaultProps = {
    settings: Immutable.List(),
    allPlans: Immutable.List(),
    allServices: Immutable.List(),
  };

  state = {
    subscription: null,
  }

  fetchSubscription = (subscription, name, action) => {
    const { allServices } = this.props;
    const id = getItemId(subscription, null);
    if (id !== null) {
      this.props.getSubscription(id, action).then((newSubscription) => {
        const newSubscriptionWithServiceId = newSubscription.update('services', Immutable.List(),
          (services) => {
            if (services) {
              return services.map((service) => {
                const isBalancePeriod = allServices.find(
                  allService => allService.get('name', '') === service.get('name', ''),
                  null,
                  Immutable.Map(),
                ).get('balance_period', 'default') !== 'default';
                const uiFlags = Immutable.Map({
                  balance_period: isBalancePeriod,
                  serviceId: uuid.v4(),
                });
                return service.set('ui_flags', uiFlags);
              });
            }
            return Immutable.List();
          });
        this.setState({ subscription: newSubscriptionWithServiceId });
      });
    }
  }

  onClickNew = (aid) => {
    this.setState({ subscription: Immutable.Map({ aid }) });
  }

  onClickCancel = (itemWasChanged = false) => {
    if (itemWasChanged === true) {
      this.props.clearList();
    }
    this.setState({ subscription: null });
  }

  onClickSave = (subscription, mode) => {
    this.props.onSaveSubscription(subscription, mode).then(this.afterSave);
  };

  afterSave = (response) => {
    if (response) {
      this.setState({ subscription: null });
    }
  }

  render() {
    const { aid, settings, allPlans, allServices } = this.props;
    const { subscription } = this.state;
    if (!subscription) {
      return (
        <SubscriptionsList
          settings={settings}
          aid={aid}
          onNew={this.onClickNew}
          onClickEdit={this.fetchSubscription}
        />
      );
    }
    return (
      <Subscription
        subscription={subscription}
        settings={settings}
        allPlans={allPlans}
        allServices={allServices}
        clearRevisions={this.props.clearRevisions}
        clearList={this.props.clearList}
        getSubscription={this.fetchSubscription}
        onSave={this.onClickSave}
        onCancel={this.onClickCancel}
      />
    );
  }

}
