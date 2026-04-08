import React from 'react';
import PropTypes from 'prop-types';

/**
 * Stepper — inline replacement for abandoned react-stepper-horizontal.
 * Renders a step indicator with the same visual contract.
 */
const Stepper = ({ steps = [], activeIndex = 0 }) => (
  <div className="stepper-container" style={{ display: 'flex', alignItems: 'flex-start', width: '100%', padding: '0 8px' }}>
    {steps.map((step, i) => {
      const isComplete = i < activeIndex;
      const isActive = i === activeIndex;
      const circleColor = (isComplete || isActive) ? '#008cba' : '#fff';
      const circleBorder = (isComplete || isActive) ? '#004b63' : '#cccccc';
      const textColor = isComplete ? '#757575' : '#333';

      return (
        <React.Fragment key={i}>
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', flex: 1 }}>
            <div style={{
              width: 25,
              height: 25,
              borderRadius: '50%',
              border: `1px solid ${circleBorder}`,
              backgroundColor: circleColor,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: 12,
              color: (isComplete || isActive) ? '#fff' : '#333',
              fontWeight: isActive ? 'bold' : 'normal',
              marginBottom: 5,
            }}>
              {isComplete ? '✓' : i + 1}
            </div>
            <div style={{ fontSize: 12, color: textColor, textAlign: 'center', maxWidth: 80 }}>
              {step.title}
            </div>
          </div>
          {i < steps.length - 1 && (
            <div style={{
              flex: 1,
              height: 1,
              backgroundColor: i < activeIndex ? '#008cba' : '#cccccc',
              marginTop: 12,
            }} />
          )}
        </React.Fragment>
      );
    })}
  </div>
);

Stepper.propTypes = {
  activeIndex: PropTypes.number,
  steps: PropTypes.arrayOf(
    PropTypes.shape({
      title: PropTypes.string,
    })
  ),
};

export default Stepper;
