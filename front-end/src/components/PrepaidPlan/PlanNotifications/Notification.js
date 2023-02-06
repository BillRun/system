import React from 'react';
import { connect } from 'react-redux';
import { Row, Col, FormGroup, ControlLabel, Button } from 'react-bootstrap';
import Field from '@/components/Field';


const Notification = (props) => {
  const { notification, first, last, index, editable, unitLabel } = props;

  const onRemove = () => {
    props.onRemove(index);
  };

  const onUpdateField = (e) => {
    const { id, value } = e.target;
    props.onUpdateField(index, id, value);
  };

  return (
    <div className="Notification">
      <Row>
        <Col lg={2} md={2} sm={2}>
          <FormGroup style={{ margin: 0 }}>
            {first && <ControlLabel>{unitLabel}</ControlLabel>}
            <Field
              id="value" value={notification.get('value', '')}
              onChange={onUpdateField}
              fieldType="number"
              editable={editable}
            />
          </FormGroup>
        </Col>
        <Col lg={2} md={2} sm={2}>
          <FormGroup style={{ margin: 0 }}>
            {first && <ControlLabel>Type</ControlLabel>}
            <Field
              id="type"
              value={notification.get('type', '')}
              onChange={onUpdateField}
              editable={editable}
            />
          </FormGroup>
        </Col>
        <Col lg={6} md={6} sm={6}>
          <FormGroup style={{ margin: 0 }}>
            {first && <ControlLabel>Message</ControlLabel>}
            <Field
              id="msg"
              value={notification.get('msg', '')}
              onChange={onUpdateField}
              editable={editable}
            />
          </FormGroup>
        </Col>
        <Col lg={1} md={1} sm={1} className="text-right">
          { (!first && last && editable) &&
            <Button bsSize="small" className="pull-right" onClick={onRemove}>
              <i className="fa fa-trash-o danger-red" />&nbsp;Remove
            </Button>
          }
        </Col>
        { !last &&
          <Col lg={12} md={12} sm={12} xs={12}>
            <hr style={{ marginTop: 8, marginBottom: 8 }} />
          </Col>
        }
      </Row>
    </div>
  );
};

export default connect()(Notification);
