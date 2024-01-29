import React from 'react';
import PropTypes from 'prop-types';
import { List, Map } from 'immutable';
import { connect } from 'react-redux';
import { Row, Col, Form, Panel } from 'react-bootstrap';
import getSymbolFromCurrency from 'currency-symbol-map';
import Notifications from './Notifications';
import Field from '@/components/Field';
import {
  getUnitLabel,
} from '@/common/Util';

const PlanNotifications = (props) => {
  const { plan, ppIncludes, mode, usageTypesData, propertyTypes, currency } = props;

  const editable = (mode !== 'view');

  const getPpInclude = ppId => ppIncludes.find(pp => pp.get('external_id', '') === parseInt(ppId)) || Map();

  const onSelectBalance = (ppIncludeId) => {
    props.onSelectBalance(ppIncludeId.toString());
  };

  const onAddNotification = (id) => {
    props.onAddNotification(id);
  };

  const onRemoveNotification = (id, index) => {
    props.onRemoveNotification(id, index);
  };

  const onUpdateNotificationField = (id, index, field, value) => {
    props.onUpdateNotificationField(id, index, field, value);
  };

  const onRemoveBalance = (id) => {
    props.onRemoveBalanceNotifications(id);
  };

  const notificationsEl = (ppId, i) => {
    const data = plan.getIn(['notifications_threshold', ppId], List());
    const ppInclude = getPpInclude(ppId);
    const name = ppInclude.get('name', '');
    const unit = ppInclude.get('charging_by_usaget_unit', false);
    const usaget = ppInclude.get('charging_by_usaget', '');
    const unitLabel = unit
      ? `Volume (${getUnitLabel(propertyTypes, usageTypesData, usaget, unit)})`
      : `Cost (${getSymbolFromCurrency(currency)})`;
    return (
      data.size ?
        <Notifications
          editable={editable}
          notifications={data}
          onAdd={onAddNotification}
          onRemove={onRemoveNotification}
          onUpdateField={onUpdateNotificationField}
          onRemoveBalance={onRemoveBalance}
          name={name}
          pp_id={ppId}
          key={i}
          unitLabel={unitLabel}
        /> :
      null
    );
  };

  const options = ppIncludes.map(pp => ({
    value: pp.get('external_id'),
    label: pp.get('name'),
  })).toJS();

  return (
    <div className="PlanNotifications">
      <Row>
        <Col lg={12}>
          <Form>
            { editable && (
              <Panel header={<h4>Select prepaid bucket</h4>}>
                <Field
                  fieldType="select"
                  placeholder="Select"
                  options={options}
                  onChange={onSelectBalance}
                  value=""
                />
              </Panel>
            )}
            { editable && <hr /> }
            { plan
                .get('notifications_threshold', Map())
                .keySeq()
                .filter(i => i !== 'on_load')
                .map(notificationsEl)
            }
            {/*
              <Notifications
                notifications={plan.getIn(['notifications_threshold', 'on_load'], List())}
                onAdd={onAddNotification}
                onRemove={onRemoveNotification}
                onUpdateField={onUpdateNotificationField}
                name="On Load"
              />
            */}
          </Form>
        </Col>
      </Row>
    </div>
  );
};

PlanNotifications.defaultProps = {
  plan: Map(),
  ppIncludes: List(),
  mode: 'create',
  usageTypesData: List(),
  propertyTypes: List(),
  currency: '',
};

PlanNotifications.propTypes = {
  plan: PropTypes.instanceOf(Map),
  ppIncludes: PropTypes.instanceOf(List),
  onAddNotification: PropTypes.func.isRequired,
  onRemoveNotification: PropTypes.func.isRequired,
  onUpdateNotificationField: PropTypes.func.isRequired,
  onSelectBalance: PropTypes.func.isRequired,
  onRemoveBalanceNotifications: PropTypes.func.isRequired,
  mode: PropTypes.string,
  usageTypesData: PropTypes.instanceOf(List),
  propertyTypes: PropTypes.instanceOf(List),
  currency: PropTypes.string,
};

export default connect()(PlanNotifications);
