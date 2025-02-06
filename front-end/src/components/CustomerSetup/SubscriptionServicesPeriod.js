import React from 'react';
import moment from 'moment';
import { InputGroup } from 'react-bootstrap';
import Field from '@/components/Field';


const SubscriptionServicesPeriod = (props) => {
  const { service, idx, minStartDate, originSubscriptionServices, editable } = props;
  if (!editable) {
    return (
      <div>{idx + 1}. Start Date:&nbsp;
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
  const existingService = originSubscriptionServices.find(originService => originService.get('name', '') === service.get('name', ''));
  const originFrom = (service.hasIn(['ui_flags', 'serviceId']) && existingService) ? existingService.get('from', null) : null;
  const onChangeStart = (date) => {
    props.onChangePeriodStartDate(serviceKey, date);
    if (!moment(service.get('to', '')).isAfter(date)) {
      const newToDate = date.clone().add(1, 'days');
      props.onChangePeriodEndDate(serviceKey, newToDate);
    }
  };
  const onChangeEnd = (date) => {
    props.onChangePeriodEndDate(serviceKey, date);
  };
  const onRemove = (index) => {
    props.onClickRemoveCloneService(serviceKey, index);
  };
  const filterServiceDate = date => props.filterServiceStartDate(originFrom, date);
  const filterServiceEndDate = date => {
    const from = service.get('from', '');
    if (from) {
      return date.isAfter(moment(from));
    }
    return date.isSameOrAfter(minStartDate);
  };
  const badge = props.renderServiceBadge(service, 'byPeriod');
  return (
    <InputGroup style={{ width: '100%', marginTop: 5 }} >
      <InputGroup.Addon>{idx + 1}. Start Date {badge}</InputGroup.Addon>
      <Field
        style={{ width: '100%' }}
        fieldType="date"
        filterDate={filterServiceDate}
        value={moment(service.get('from', ''))}
        onChange={onChangeStart}
        editable={editable}
        highlightDates={[minStartDate]}
      />
      <InputGroup.Addon>End Date</InputGroup.Addon>
      <Field
        style={{ width: '100%' }}
        fieldType="date"
        filterDate={filterServiceEndDate}
        value={moment(service.get('to', ''))}
        onChange={onChangeEnd}
        editable={editable}
      />
      <InputGroup.Addon onClick={onRemove}>
        <i className="fa fa-trash-o text-danger" />
      </InputGroup.Addon>
    </InputGroup>
  );
}

export default SubscriptionServicesPeriod;