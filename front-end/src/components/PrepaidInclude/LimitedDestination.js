import React from 'react';
import PropTypes from 'prop-types';
import { List } from 'immutable';
import { Panel } from 'react-bootstrap';
import Field from '@/components/Field';
import { Actions } from '@/components/Elements';

const LimitedDestination = ({ name, rates, allRates, onChange, onRemove, editable }) => {
  const onChangeValue = (value) => {
    onChange(name, List(value.split(',')));
  };

  const onRemoveClick = () => {
    onRemove(name);
  };

  const actions = [
    { type: 'remove', showIcon: true, onClick: onRemoveClick },
  ];

  const renderPanelHeader = () => (
    <div>
      { name }
      <div className="pull-right" style={{ marginTop: -5 }}>
        <Actions actions={actions} />
      </div>
    </div>
  );

  return (
    <div className="LimitedDestination">
      <Panel header={renderPanelHeader()}>
        <Field
          fieldType="select"
          multi={true}
          value={rates.join(',')}
          options={allRates}
          onChange={onChangeValue}
          editable={editable}
        />
      </Panel>
    </div>
  );
};

LimitedDestination.defaultProps = {
  name: '',
  rates: List(),
  allRates: [],
  actions: [],
  editable: true,
};

LimitedDestination.propTypes = {
  name: PropTypes.string,
  rates: PropTypes.instanceOf(List),
  allRates: PropTypes.array,
  onChange: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
  editable: PropTypes.bool,
};

export default LimitedDestination;
