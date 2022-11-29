import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Map, List } from 'immutable';
import { Panel } from 'react-bootstrap';
import LimitedDestination from './LimitedDestination';
import { PlanSearch } from '@/components/Elements';


const LimitedDestinations = (props) => {
  const { limitedDestinations, allRates, mode } = props;
  const editable = (mode !== 'view');
  const selectedPlans = limitedDestinations.keySeq().toArray();

  return (
    <div className="LimitedDestinations">
      { editable &&
        <Panel>
          <PlanSearch onSelectPlan={props.onSelectPlan} selectedOptions={selectedPlans} planType="prepaid" />
        </Panel>
      }
      {
        limitedDestinations
          .keySeq()
          .filter(n => n !== 'BASE')
          .map((name, key) => (
            <LimitedDestination
              editable={editable}
              key={key}
              rates={limitedDestinations.get(name, List())}
              onChange={props.onChange}
              onRemove={props.onRemove}
              allRates={allRates}
              name={name}
            />
          ))
      }
    </div>
  );
};

LimitedDestinations.defaultProps = {
  limitedDestinations: Map(),
  allRates: [],
  mode: 'create',
};

LimitedDestinations.propTypes = {
  limitedDestinations: PropTypes.instanceOf(Map),
  allRates: PropTypes.array,
  onSelectPlan: PropTypes.func.isRequired,
  onChange: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
  mode: PropTypes.string,
};

export default connect()(LimitedDestinations);
