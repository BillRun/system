import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { compose } from 'redux';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Form, FormGroup, ControlLabel, Col, Panel, Table } from 'react-bootstrap';
import uuid from 'uuid';
import moment from 'moment';
import SubscriptionServicesDetails from './SubscriptionServices/SubscriptionServicesDetails';
import { ActionButtons, Actions, CreateButton } from '@/components/Elements';
import Field from '@/components/Field';
import { EntityRevisionDetails, EntityFields } from '../Entity';
import { DiscountPopup } from '@/components/Discount';
import PlaysSelector from '../Plays/PlaysSelector';
import { discountFieldsSelector } from '@/selectors/settingsSelector';
import { showFormModal, setFormModalError, showConfirmModal } from '@/actions/guiStateActions/pageActions';
import { validateEntity } from '@/actions/discountsActions';
import {
  getConfig,
  getItemId,
  getItemMode,
  getItemDateValue,
  buildPageTitle,
  toImmutableList,
  getFieldName,
} from '@/common/Util';

class Subscription extends Component {

  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    subscription: PropTypes.instanceOf(Immutable.Map),
    revisions: PropTypes.instanceOf(Immutable.List),
    settings: PropTypes.instanceOf(Immutable.List), // Subscriptions Fields
    discountFields: PropTypes.instanceOf(Immutable.List), // Subscriptions Fields
    allPlans: PropTypes.instanceOf(Immutable.List),
    allServices: PropTypes.instanceOf(Immutable.List),
    mode: PropTypes.string,
    onSave: PropTypes.func.isRequired,
    onCancel: PropTypes.func.isRequired,
    clearRevisions: PropTypes.func.isRequired,
    clearList: PropTypes.func.isRequired,
    getSubscription: PropTypes.func.isRequired,
  }

  static defaultProps = {
    subscription: Immutable.Map(),
    mode: 'create',
    revisions: Immutable.List(),
    settings: Immutable.List(),
    discountFields: Immutable.List(),
    allPlans: Immutable.List(),
    allServices: Immutable.List(),
  }

  constructor(props) {
    super(props);
    this.state = {
      subscription: props.subscription,
      progress: false,
      discountsHiddenFields: ['key', 'params.min_subscribers', 'params.max_subscribers']
    };
  }

  componentWillReceiveProps(nextProps) {
    if (!Immutable.is(this.props.subscription, nextProps.subscription)) {
      this.setState({ subscription: nextProps.subscription });
    }
  }

  initService = (serviceName) => {
    const { subscription: originSubscription } = this.props;
    const { subscription } = this.state;
    const type = this.getServiceType(serviceName);
    const from = getItemDateValue(subscription, 'from').format('YYYY-MM-DD');
    const to = getItemDateValue(subscription, 'to').format('YYYY-MM-DD');
    const newService = Immutable.Map({ name: serviceName, from, to });
    const originServices = originSubscription.get('services', Immutable.List()) || Immutable.List();
    const existingService = originServices.find(originService => originService.get('name', '') === serviceName);

    switch (type) {
      case 'quantitative': {
        return (existingService && existingService.get('quantity', '') === 1) ? existingService : newService.set('quantity', 1);
      }
      case 'balance_period': {
        return newService.setIn(['ui_flags', 'balance_period'], true);
      }
      default: {
        return newService;
      }
    }
  }

  onSave = () => {
    const { subscription } = this.state;
    const { mode } = this.props;
    // prosses subscriber before save
    const subscriptionToSave = compose(
      this.removeServiceUiFlags,
      // Now update services dates runs of sub. From field change,
      // can be uncomment to be sure that services date are correct if bugs will be found
      // this.updateServicesDates,
    )(subscription);

    this.props.onSave(subscriptionToSave, mode);
  }

  onChangeFrom = (path, value) => {
    const { subscription } = this.state;
    const newSubscription = this.updateServicesDates(subscription.setIn(path, value));
    this.setState({ subscription: newSubscription });
  }

  onChangePlan = (plan) => {
    this.updateSubscriptionField(['plan'], plan);
  }

  onChangePlay = (play) => {
    this.updateSubscriptionField(['play'], play);
    this.filterSubscriptionServicesByPlay(play);
    this.filterSubscriptionPlanByPlay(play);
  }

  onChangeServiceDetails = (index, key, value) => {
    const path = Array.isArray(key) ? key : [key];
    this.updateSubscriptionField(['services', index, ...path], value);
  }

  onRemoveService = (index) => {
    const { subscription } = this.state;
    const services = subscription.get('services', Immutable.List()) || Immutable.List();
    const newServices = services.delete(index);
    this.updateSubscriptionField(['services'], newServices);
  }

  onAddService = (name) => {
    const { subscription } = this.state;
    const newService = this.initService(name);
    const services = subscription.get('services', Immutable.List()) || Immutable.List();
    const newServices = services.push(newService);
    this.updateSubscriptionField(['services'], newServices);
  }

  onChangeService = (services) => {
    const { subscription } = this.state;
    if (!services.length) {
      this.updateSubscriptionField(['services'], Immutable.List());
      return;
    }
    const servicesNames = Immutable.Set(services.split(','));
    const originServices = subscription.get('services', Immutable.List()) || Immutable.List();
    const originServicesNames = Immutable.Set(originServices.map(originService => originService.get('name', '')));

    const addedServices = servicesNames.filter(item => !originServicesNames.has(item));
    const removedServices = originServicesNames.filter(item => !servicesNames.has(item));

    if (addedServices.size) {
      addedServices.forEach((newServiceName) => { this.onAddService(newServiceName); });
    }
    if (removedServices.size) {
      removedServices.forEach((removeService) => {
        originServices.forEach((originService, index) => {
          if (originService.get('name', '') === removeService) {
            this.onRemoveService(index);
          }
        });
      });
    }
  }

  getDiscountActions = () => {
    const { mode } = this.props;
    const allowEdit = mode !== 'view';
    return ([{
      type: 'view',
      helpText: 'view discount',
      onClick: this.onDiscountEditForm,
      show: !allowEdit,
      actionClass: "pl0 pr0"
    },{
      type: 'edit',
      helpText: 'Edit discount',
      onClick: this.onDiscountEditForm,
      show: allowEdit,
      actionClass: "pl0 pr0"
    }, {
      type: 'remove',
      helpText: 'Remove discount',
      onClick: this.onRemoveDiscount,
      show: allowEdit,
      actionClass: "pl0 pr0"
    }]);
  }

  onDiscountEditForm = (idx) => {
    const { dispatch, mode, discountFields } = this.props;
    const { subscription, discountsHiddenFields } = this.state;
    const discounts = subscription.get('discounts', Immutable.List()) || Immutable.List();
    const isCreate = idx === null;
    const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]');
    const newDiscount = discounts.get(idx, Immutable.Map({
      key: `SUBSCRIBER_DISCOUNT_${uuid.v4().replace(/-/g, '')}`,
      type: 'monetary',
      from: subscription.get('from', moment().format(apiDateTimeFormat)),
      to: subscription.get('to', moment().add(100, 'years').format(apiDateTimeFormat)),
    }))
    const onOk = (newItem) => {
      this.props.dispatch(setFormModalError());
      const errors = dispatch(validateEntity(newItem, discountFields, 'saveInSubscriber'));
      if (!errors.isEmpty()) {
        errors.forEach((message, fieldId) => {
          this.props.dispatch(setFormModalError(fieldId, message));
        })
        return false;
      }
      const newDiscounts = isCreate ? discounts.push(newItem) : discounts.set(idx, newItem);
      this.updateSubscriptionField(['discounts'], newDiscounts);
      return true;
    };
    const config = {
      title: isCreate ? 'Create discount' : `Edit ${newDiscount.get('description', 'discount')}`,
      onOk,
      mode,
      hideFields: discountsHiddenFields,
    };
    return dispatch(showFormModal(newDiscount, DiscountPopup, config));
  }

  onRemoveDiscount = (index) => {
    const { dispatch } = this.props;
    const { subscription } = this.state;

    const discounts = subscription.get('discounts', Immutable.List()) || Immutable.List();
    const discount = discounts.get(index, null);
    if (discount === null) {
      return false;
    }

    const onOk = () => {
      const newDiscounts = discounts.delete(index);
      this.updateSubscriptionField(['discounts'], newDiscounts);
    };
    const confirm = {
      message: `Are you sure you want to remove discount "${discount.get('description', '')}"?`,
      onOk,
      labelOk: 'Delete',
      type: 'delete',
    };
    return dispatch(showConfirmModal(confirm));
  }


  filterCustomFields = (field) => {
    const hiddenFields = ['plan', 'services', 'play'];
    const isCustomField = !hiddenFields.includes(field.get('field_name'));
    const isEditable = field.get('editable', false);
    const isMandatory = field.get('mandatory', false);
    const shouldDisplayed = field.get('display', true);

    return isCustomField &&
    (isEditable || isMandatory) &&
    shouldDisplayed;
    // PHP .../application/views/internalpaypage/index.phtml condition
    // if ((empty($c['display']) && empty($c['mandatory']))
    //  || $c['field_name'] === 'plan'
    //  || (isset($c['editable']) && !$c['editable'])
    // ) continue;
  }

  filterSubscriptionPlanByPlay = (play) => {
    const { subscription } = this.state;
    const { allPlans } = this.props;
    const selectedPlanPlays = allPlans.find(
      plan => plan.get('name', '') === subscription.get('plan', ''),
      null, Immutable.Map(),
    ).get('play', Immutable.List());
    if (!selectedPlanPlays.isEmpty() && !selectedPlanPlays.includes(play)) {
      this.updateSubscriptionField(['plan'], '');
    }
  }

  filterSubscriptionServicesByPlay = (play) => {
    const { allServices } = this.props;
    const { subscription } = this.state;
    const services = subscription.get('services', Immutable.List()) || Immutable.List();
    const newServices = services.filter((service) => {
      const servicePlays = allServices.find(
        option => option.get('name', '') === service.get('name', ''),
        null, Immutable.Map(),
      ).get('play', Immutable.List());
      return servicePlays.isEmpty() || servicePlays.includes(play);
    });
    this.updateSubscriptionField(['services'], newServices);
  }

  updateSubscriptionField = (path, value) => {
    this.setState(prevState => ({ subscription: prevState.subscription.setIn(path, value) }));
  }

  updateServicesDates = (subscription, newFrom = null) => {
    const { subscription: originSubscription } = this.props;
    const originServices = originSubscription.get('services', Immutable.List()) || Immutable.List();
    // const services = subscription.get('services', Immutable.List()) || Immutable.List();
    const from = newFrom || getItemDateValue(subscription, 'from').toISOString();

    return subscription.update('services', Immutable.List(), (services) => {
      if (!services) {
        return Immutable.List();
      }
      return services.map((service) => {
        const serviceType = this.getServiceType(service); // 'normal', 'quantitative', 'balance_period'
        const existingService = originServices.find(originService => originService.getIn(['ui_flags', 'serviceId'], '') === service.getIn(['ui_flags', 'serviceId'], ''));
        const newService = service.getIn(['ui_flags', 'serviceId'], '') === '';

        switch (serviceType) {
          case 'normal': { // New -> update to SUB from if its less.
            if (newService) {
              const serviceFrom = service.get('from', from);
              if (moment(serviceFrom).isBefore(from, 'days')) {
                return service.set('from', from);
              }
            }
            return service;
          }

          case 'quantitative': { // New or Existing with change -> update to SUB from.
            const existingServiceWithChange = existingService && existingService.get('quantity', '') !== service.get('quantity', '');
            return (newService || existingServiceWithChange) ? service.set('from', from) : service;
          }

          case 'balance_period': {
            const existingServiceWithChange = existingService && !moment(existingService.get('from', '')).isSame(moment(service.get('from', '')), 'days');
            const incorrectForm = moment(service.get('from', '')).isBefore(from, 'days');
            // New or Existing with change and incorrect FROM -> Update from to SUB from
            return ((newService || existingServiceWithChange) && incorrectForm) ? service.set('from', from) : service;
          }

          default:
            return service;
        }
      });
    });
  }

  removeSubscriptionField = (path, value) => {
    this.setState(prevState => ({ subscription: prevState.subscription.deleteIn(path, value) }));
  }

  getServiceType = (service) => {
    const { allServices } = this.props;
    const serviceName = (Immutable.Map.isMap(service)) ? service.get('name', '') : service;
    const serviceOption = allServices.find(option => option.get('name', '') === serviceName);
    if (!serviceOption) {
      return null;
    }
    if (serviceOption.get('quantitative', false)) {
      return 'quantitative';
    }
    if (serviceOption.get('balance_period', 'default') !== 'default') {
      return 'balance_period';
    }
    return 'normal';
  }

  formatSelectOptions = items => items.map(item => ({
    value: item.get('name', ''),
    label: item.get('description', item.get('name', '')),
  }));

  getAvailablePlans = () => {
    const { subscription } = this.state;
    const { allPlans } = this.props;
    const play = subscription.get('play', false);
    if ([false, ''].includes(play)) {
      return this.formatSelectOptions(allPlans);
    }
    return this.formatSelectOptions(allPlans
      .filter(allPlan => allPlan.get('play', Immutable.List()).isEmpty() || allPlan.get('play', Immutable.List()).includes(play)),
    );
  }

  getPlanIncludedServices = (planName) => {
    if (planName === '') {
      return '-';
    }
    const { allPlans } = this.props;
    const selectedPlan = allPlans.find(plan => plan.get('name', '') === planName, null, Immutable.Map());
    const includedServices = selectedPlan.getIn(['include', 'services'], Immutable.List());
    return includedServices.size ? includedServices.join(', ') : '-';
  }

  getAvailableServices = () => {
    const { subscription } = this.state;
    const { allServices } = this.props;
    const play = subscription.get('play', false);
    if ([false, ''].includes(play)) {
      return this.formatSelectOptions(allServices);
    }
    return this.formatSelectOptions(allServices
      .filter(allService => allService.get('play', Immutable.List()).isEmpty() || allService.get('play', Immutable.List()).includes(play)),
    );
  }

  getActions = () => [{
    type: 'back',
    label: 'Back To List',
    onClick: this.props.onCancel,
    actionStyle: 'primary',
    actionSize: 'xsmall',
  }];

  fetchItem = () => {
    const { subscription } = this.state;
    this.props.getSubscription(subscription);
  }

  clearRevisions = () => {
    const { subscription } = this.state;
    this.props.clearRevisions(subscription);
    this.clearItemsList();
  }

  clearItemsList = () => {
    this.props.clearList();
  }

  removeServiceUiFlags = subscription => subscription.update('services', Immutable.List(),
    services => (services ? services.map(service => service.delete('ui_flags')) : Immutable.List()),
  );

  renderDiscountRow = (discount, idx) => {
    const dateFormat = getConfig('dateFormat', 'DD/MM/YYYY');
    return (
      <tr className="List" key={discount.get('key', idx)}>
        <td className="td-ellipsis">{discount.get('description', '')}</td>
        <td className="td-ellipsi text-center">{getItemDateValue(discount, 'from').format(dateFormat)}</td>
        <td className="td-ellipsis text-center">{getItemDateValue(discount, 'to').format(dateFormat)}</td>
        <td className="td-ellipsis text-center">{getFieldName(`type_${discount.get('type', 'monetary')}`, 'discount')}</td>
        <td className="td-ellipsis text-center">{discount.get('cycles', 'Infinite')}</td>
        <td className="td-ellipsis text-center">{discount.get('priority', '-')}</td>
        <td className="text-right row pr0 pl0">
          <Actions actions={this.getDiscountActions()} data={idx} />
        </td>
      </tr>
    );
  }

  renderSystemFields = (editable) => {
    const { subscription } = this.state;
    const { mode } = this.props;
    const plansOptions = this.getAvailablePlans().toJS();
    const servicesOptions = this.getAvailableServices().toJS();
    const services = subscription.get('services', Immutable.List()) || Immutable.List();
    const servicesList = Immutable.Set(services.map(service => service.get('name', ''))).join(',');
    const plan = subscription.get('plan', '');
    return ([(
      <PlaysSelector key="plays-selector"
        entity={subscription}
        editable={editable && mode === 'create'}
        mandatory={true}
        onChange={this.onChangePlay}
        />
    ), (
      <FormGroup key="plan">
        <Col componentClass={ControlLabel}sm={3} lg={2}>Plan <span className="danger-red"> *</span></Col>
        <Col sm={8} lg={9}>
          <Field
            fieldType="select"
            options={plansOptions}
            value={plan}
            onChange={this.onChangePlan}
            editable={editable}
            />
        </Col>
      </FormGroup>
    ), (
      <FormGroup key="includedServices">
        <Col componentClass={ControlLabel} sm={3} lg={2}>Included Services</Col>
        <Col sm={7}>
          <Field value={this.getPlanIncludedServices(plan)} editable={false} />
        </Col>
      </FormGroup>
    ), (
      <FormGroup key="services">
        <Col componentClass={ControlLabel} sm={3} lg={2}>Services</Col>
        <Col sm={8} lg={9}>
          <Field
            fieldType="select"
            multi={true}
            value={servicesList}
            options={servicesOptions}
            onChange={this.onChangeService}
            clearable={false}
            editable={editable}
            />
        </Col>
      </FormGroup>
    )]);
  }

  renderPanelTitle = () => {
    const { mode, subscription } = this.props;
    const title = buildPageTitle(mode, 'subscription', subscription);
    return (
      <div>
        { title }
        <div className="pull-right">
          <Actions actions={this.getActions()} />
        </div>
      </div>
    );
  }

  getServiceStartMinDate = () => {
    const { subscription } = this.state;
    const subscriptionFrom = getItemDateValue(subscription, 'from');
    const subscriptionActivation = getItemDateValue(subscription, 'activation_date', subscriptionFrom);
    return moment.max(subscriptionFrom, subscriptionActivation);
  }

  render() {
    const { progress, subscription } = this.state;
    const { revisions, mode, allServices, subscription: originSubscription } = this.props;
    const allowEdit = ['update', 'clone', 'closeandnew', 'create'].includes(mode);
    const services = subscription.get('services', Immutable.List()) || Immutable.List();
    const minStartDate = this.getServiceStartMinDate();
    const originServices = originSubscription.get('services', Immutable.List()) || Immutable.List();

    return (
      <div className="Subscription">
        <Panel header={this.renderPanelTitle()}>
          <EntityRevisionDetails
            itemName="subscription"
            revisions={revisions}
            item={subscription}
            mode={mode}
            onChangeFrom={this.onChangeFrom}
            backToList={this.props.onCancel}
            reLoadItem={this.fetchItem}
            clearRevisions={this.clearRevisions}
            onActionEdit={this.props.getSubscription}
            onActionClone={this.props.getSubscription}
            clearList={this.clearItemsList}
          />

          <hr />

          <Form horizontal>
            { this.renderSystemFields(allowEdit) }
            <SubscriptionServicesDetails
              subscriptionServices={services}
              originSubscriptionServices={originServices}
              servicesOptions={allServices}
              editable={allowEdit}
              minStartDate={minStartDate}
              onChangeService={this.onChangeServiceDetails}
              onRemoveService={this.onRemoveService}
              onAddService={this.onAddService}
            />
            <EntityFields
              entityName={['subscribers', 'subscriber']}
              entity={subscription}
              onChangeField={this.updateSubscriptionField}
              onRemoveField={this.removeSubscriptionField}
              fieldsFilter={this.filterCustomFields}
              editable={allowEdit}
            />
          </Form>

          <Panel header={<h3>Discounts</h3>}>
            {subscription.get('discounts', Immutable.List()).isEmpty() && (
              <p><small>No Discounts</small></p>
            )}
            {!subscription.get('discounts', Immutable.List()).isEmpty() && (
              <Table style={{ tableLayout: 'fixed' }} className="mb0">
                <thead>
                  <tr>
                    <th>{ getFieldName('description', 'discount')}</th>
                    <th className="text-center">{ getFieldName('from', 'discount')}</th>
                    <th className="text-center">{ getFieldName('to', 'discount')}</th>
                    <th className="text-center">{ getFieldName('type', 'discount')}</th>
                    <th className="text-center">{ getFieldName('cycles', 'discount')}</th>
                    <th className="text-center">{ getFieldName('priority', 'discount')}</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  { subscription.get('discounts', Immutable.List())
                    .map(this.renderDiscountRow)
                    .toList()
                    .toArray() }
                  </tbody>
                </Table>
            )}
            { allowEdit && (
              <CreateButton
                buttonStyle={{}}
                onClick={this.onDiscountEditForm}
                action="Add"
                label=""
                type="Discount"
              />
            )}
          </Panel>
        </Panel>

        <ActionButtons
          onClickCancel={this.props.onCancel}
          onClickSave={this.onSave}
          hideSave={!allowEdit}
          cancelLabel={allowEdit ? undefined : 'Back'}
          progress={progress}
        />
      </div>
    );
  }
}

const mapStateToProps = (state, props) => {
  const { subscription } = props;
  const collection = getConfig(['systemItems', 'subscription', 'collection'], '');
  const key = toImmutableList(getConfig(['systemItems', 'subscription', 'uniqueField'], ''))
    .map(revisionBy => subscription.get(revisionBy, ''))
    .join('_');
  const revisions = state.entityList.revisions.getIn([collection, key]);
  const mode = (!subscription || !getItemId(subscription, false)) ? 'create' : getItemMode(subscription);
  return ({
    discountFields: discountFieldsSelector(state, props),
    revisions,
    mode,
  });
};
export default connect(mapStateToProps)(Subscription);
