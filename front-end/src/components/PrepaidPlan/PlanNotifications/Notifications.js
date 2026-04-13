import React from 'react';
import { connect } from 'react-redux';
import { Panel, Button } from 'react-bootstrap';
import { CreateButton } from '@/components/Elements';
import Notification from './Notification';

const Notifications = (props) => {
  const onAdd = () => {
    props.onAdd(props.pp_id);
  };

  const onRemove = (index) => {
    props.onRemove(props.pp_id, index);
  };

  const onRemoveBalance = () => {
    props.onRemoveBalance(props.pp_id);
  };

  const onUpdateField = (index, field, value) => {
    props.onUpdateField(props.pp_id, index, field, value);
  };

  const notification_el = (notification, i) => {
    const first = i === 0;
    const last = i === props.notifications.size - 1;
    return (
      <Notification
        editable={props.editable}
        notification={notification}
        first={first}
        last={last}
        onRemove={onRemove}
        onUpdateField={onUpdateField}
        index={i}
        key={i}
        unitLabel={props.unitLabel}
      />
    );
  };

  const header = (
    <h3>
      { props.name }
      { props.editable &&
        <Button onClick={onRemoveBalance} bsSize="xsmall" className="pull-right" style={{ minWidth: 80 }}>
          <i className="fa fa-trash-o danger-red" />&nbsp;Remove
        </Button>
      }
    </h3>
  );

  return (
    <div className="Notifications">
      <Panel header={header}>
        { props.notifications.map(notification_el) }
        <br />
        { props.editable && <CreateButton onClick={onAdd} label="Add New" /> }
      </Panel>
    </div>
  );
};

export default connect()(Notifications);
