import React from 'react';
import PropTypes from 'prop-types';
import { List } from 'immutable';
import { Panel } from 'react-bootstrap';
import ChargingPlanInclude from './ChargingPlanInclude';
import Field from '@/components/Field';

const ChargingPlanIncludes = ({
  includes, mode, prepaidIncludesOptions,
  onSelectPPInclude, onUpdateField, onUpdatePeriodField, onRemoveChargingPlan,
}) => {
  const editable = mode !== 'view';
  return (
    <div className="ChargingPlanIncludes">
      { editable && (
        <Panel header={<h3>Select prepaid bucket</h3>}>
          <Field
            fieldType="select"
            value=""
            options={prepaidIncludesOptions}
            onChange={onSelectPPInclude}
          />
        </Panel>
      )}
      { editable && (<hr />) }
      { includes
          .keySeq()
          .filter(inc => !['cost', 'groups'].includes(inc))
          .map((index, key) => (
            <ChargingPlanInclude
              key={key}
              editable={editable}
              include={includes.get(index)}
              onUpdateField={onUpdateField}
              onUpdatePeriodField={onUpdatePeriodField}
              onRemove={onRemoveChargingPlan}
              index={index}
            />
          ))
      }
    </div>
  );
}

ChargingPlanIncludes.defaultProps = {
  includes: List(),
  prepaidIncludesOptions: [],
  mode: 'create',
};

ChargingPlanIncludes.propTypes = {
  includes: PropTypes.instanceOf(List),
  prepaidIncludesOptions: PropTypes.array,
  mode: PropTypes.string,
  onUpdateField: PropTypes.func.isRequired,
  onSelectPPInclude: PropTypes.func.isRequired,
  onUpdatePeriodField: PropTypes.func.isRequired,
  onRemoveChargingPlan: PropTypes.func.isRequired,
};

export default ChargingPlanIncludes;
