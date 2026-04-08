import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Col } from 'react-bootstrap';
import { ControlLabel, FormGroup } from '@/common/BootstrapCompat';
import Field from '@/components/Field';
import { Actions } from '@/components/Elements';

const BalanceThreshold = (props) => {
  const { name, unitLabel, ppId, value, editable } = props;

  const onChange = (e) => {
    props.onChange(ppId, e.target.value);
  };

  const onRemove = () => {
    props.onRemove(ppId);
  };

  const actions = [
    {
      type: 'remove',
      showIcon: true,
      onClick: onRemove,
      actionStyle: 'outline-secondary',
      actionSize: 'small',
    },
  ];

  return (
    <FormGroup>
      <Col as={ControlLabel} md={2}>
        { `${name} (${unitLabel})` }
      </Col>
      <Col md={7} className="pr5">
        <Field fieldType="number" onChange={onChange} value={value} editable={editable} />
      </Col>
      <Col md={2} className="actions pl5">
        <Actions actions={actions} />
      </Col>
    </FormGroup>
  );
};


BalanceThreshold.propTypes = {
  name: PropTypes.string,
  unitLabel: PropTypes.string,
  editable: PropTypes.bool,
  value: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.number,
  ]),
  ppId: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.number,
  ]),
  onChange: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
};

export default connect()(BalanceThreshold);
