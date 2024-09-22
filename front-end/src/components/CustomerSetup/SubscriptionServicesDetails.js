import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import moment from 'moment';
import { Panel, InputGroup, Badge } from 'react-bootstrap';
import Field from '@/components/Field';
import { CreateButton } from '@/components/Elements';


export default class SubscriptionServicesDetails extends Component {

  static propTypes = {
    editable: PropTypes.bool,
    originSubscriptionServices: PropTypes.instanceOf(Immutable.List),
    subscriptionServices: PropTypes.instanceOf(Immutable.List),
    subscriptionFrom: PropTypes.object,
    servicesOptions: PropTypes.instanceOf(Immutable.List),
    onChangeService: PropTypes.func.isRequired,
    onRemoveService: PropTypes.func.isRequired,
    onAddService: PropTypes.func.isRequired,
  }

  static defaultProps = {
    editable: true,
    originSubscriptionServices: Immutable.List(),
    subscriptionServices: Immutable.List(),
    subscriptionFrom: null,
    servicesOptions: Immutable.List(),
  }

  onChangePeriodStartDate = (index, newDate) => {
    if (newDate) {
      this.props.onChangeService(index, 'from', newDate.format('YYYY-MM-DD'));
    }
  }

  onClickRemoveCloneService = (index) => {
    if (index !== -1) {
      this.props.onRemoveService(index);
    }
  }

  onClickAddPeriodService = (serviceName) => {
    this.props.onAddService(serviceName);
  }

  onChangeServiceQuantity = (index, e) => {
    const { subscriptionFrom, originSubscriptionServices, subscriptionServices } = this.props;
    const { value } = e.target;
    const fixedValue = value > 1 ? Number(value) : 1; // not possible to add 0 for quantity service
    const service = subscriptionServices.get(index, null);
    if (service) {
      const existingService = originSubscriptionServices.find(originService => originService.get('name', '') === service.get('name', ''));
      const newFrom = (existingService && existingService.get('quantity', '') === fixedValue)
        ? existingService.get('from', '')
        : subscriptionFrom.format('YYYY-MM-DD');
      this.props.onChangeService(index, 'quantity', fixedValue);
      this.props.onChangeService(index, 'from', newFrom);
    }
  }

  filterServiceStartDate = (serviceDate, date) => {
    const { subscriptionFrom } = this.props;
    if (serviceDate) {
      return date.isSame(moment(serviceDate), 'days') || date.isSameOrAfter(subscriptionFrom);
    }
    return date.isSameOrAfter(subscriptionFrom);
  }

  renderServiceBadge = (service, type) => {
    const { originSubscriptionServices } = this.props;

    if (!service.hasIn(['ui_flags', 'serviceId'])) {
      return (<Badge>new</Badge>);
    }
    const existingService = originSubscriptionServices.find(originService => originService.getIn(['ui_flags', 'serviceId'], '') === service.getIn(['ui_flags', 'serviceId'], ''));
    if (type === 'byPeriod' && !moment(existingService.get('from', '')).isSame(moment(service.get('from', '')), 'days')) {
      return (<Badge>updated</Badge>);
    }
    if (type === 'quantity' && existingService.get('quantity', '') !== service.get('quantity', '')) {
      return (<Badge>updated</Badge>);
    }

    return '';
  }

  renderServicesQuentity = (editable) => {
    const { servicesOptions, subscriptionServices } = this.props;
    return subscriptionServices
      .map((service, index) => service.set('index', index))
      .filter(service => service.get('quantity', null) !== null)
      .map((service, key) => {
        const serviceName = servicesOptions.find(
          allService => allService.get('name', '') === service.get('name', ''),
          null,
          Immutable.Map(),
        ).get('description', service.get('name', ''));
        const serviceKey = service.get('index', '');
        const onChangeBind = (e) => { this.onChangeServiceQuantity(serviceKey, e); };
        const badge = this.renderServiceBadge(service, 'quantity');
        const viewStyle = editable ? {} : { display: 'inline-block' };
        return (
          <tr key={`quentity_${key}`}>
            <td style={{ verticalAlign: 'middle', width: '30%', textAlign: 'right', paddingRight: 20, paddingBottom: 5 }}>
              {serviceName}
            </td>
            <td style={{ width: '70%', paddingBottom: 5 }}>
              <InputGroup style={{ width: '100%' }} >
                {editable ? <InputGroup.Addon>Quantity {badge}</InputGroup.Addon> : 'Quantity: ' }
                <Field fieldType="number" min={1} value={service.get('quantity', '')} onChange={onChangeBind} editable={editable} style={viewStyle} />
              </InputGroup>
            </td>
          </tr>
        );
      })
      .toArray();
  }

  renderServicesByPeriod = (editable) => {
    const { servicesOptions, subscriptionServices } = this.props;
    return subscriptionServices
      .map((service, index) => service.set('index', index))
      .filter(service => service.getIn(['ui_flags', 'balance_period'], false))
      .reduce((acc, service) => {
        if (acc.has(service.get('name', ''))) {
          return acc.update(service.get('name', ''), list => list.push(service));
        }
        return acc.set(service.get('name', ''), Immutable.List([service]));
      }, Immutable.Map())
      .map((servicesGroups, key) => {
        const service = servicesGroups.last();
        const serviceName = servicesOptions.find(
          allService => allService.get('name', '') === service.get('name', ''),
          null,
          Immutable.Map(),
        ).get('description', service.get('name', ''));
        const onClickAddCloneService = (e) => {
          this.onClickAddPeriodService(service.get('name', ''), e);
        };
        const style = editable ? { verticalAlign: 'middle', paddingBottom: 47, width: '30%', textAlign: 'right', paddingRight: 20 } : { verticalAlign: 'middle', textAlign: 'right', paddingRight: 20 };
        return (
          <tr key={`byPeriod_${key}`}>
            <td style={style}>
              {serviceName}
            </td>
            <td style={{ width: '70%' }}>
              { servicesGroups.map((serviceGroup, i) =>
                  this.renderPeriods(serviceGroup, i, editable))
              }
              {editable && <CreateButton buttonStyle={{ marginTop: 5, marginBottom: 10 }} onClick={onClickAddCloneService} label="Add start date" />}
            </td>
          </tr>
        );
      })
      .toList()
      .toArray();
  }

  renderPeriods = (service, key, editable) => {
    const { subscriptionFrom, originSubscriptionServices } = this.props;
    if (!editable) {
      return (
        <div key={`byPeriod_${key}_${service.get('name', 'no-name')}`} >{key + 1}. Start Date:&nbsp;
          <Field
            style={{ display: 'inline-block' }}
            fieldType="date"
            value={moment(service.get('from', ''))}
            editable={editable}
          />
        </div>
      );
    }
    const serviceKey = service.get('index', '');
    const onChangeBind = (e) => { this.onChangePeriodStartDate(serviceKey, e); };
    const onRemoveBind = (e) => { this.onClickRemoveCloneService(serviceKey, e); };
    const existingService = originSubscriptionServices.find(originService => originService.get('name', '') === service.get('name', ''));
    const originFrom = (service.hasIn(['ui_flags', 'serviceId']) && existingService) ? existingService.get('from', null) : null;
    const filterServiseDate = e => this.filterServiceStartDate(originFrom, e);
    const badge = this.renderServiceBadge(service, 'byPeriod');
    return (
      <InputGroup key={`byPeriod_${key}_${service.get('name', 'no-name')}`} style={{ width: '100%', marginTop: 5 }} >
        <InputGroup.Addon>{key + 1}. Start Date {badge}</InputGroup.Addon>
        <Field
          style={{ width: '100%' }}
          fieldType="date"
          filterDate={filterServiseDate}
          value={moment(service.get('from', ''))}
          onChange={onChangeBind}
          editable={editable}
          highlightDates={[subscriptionFrom]}
        />
        <InputGroup.Addon onClick={onRemoveBind}>
          <i className="fa fa-trash-o text-danger" />
        </InputGroup.Addon>
      </InputGroup>
    );
  }

  render() {
    const { editable } = this.props;
    const servicesQuentity = this.renderServicesQuentity(editable);
    const servicesByPeriod = this.renderServicesByPeriod(editable);
    if (servicesQuentity.length + servicesByPeriod.length > 0) {
      return (
        <Panel header="Services Details">
          <table style={{ width: '100%' }}><tbody>{servicesQuentity}</tbody></table>
          {servicesQuentity.length > 0 && servicesByPeriod.length > 0 && <hr />}
          <table style={{ width: '100%' }}><tbody>{servicesByPeriod}</tbody></table>
        </Panel>
      );
    }
    return (<hr />);
  }
}
