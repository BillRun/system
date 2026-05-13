import React from 'react';
import PropTypes from 'prop-types';

/**
 * Stepper — inline replacement for abandoned react-stepper-horizontal.
 * Renders a step indicator with the same visual contract.
 */
const Stepper = ({ steps = [], activeIndex = 0 }) => (
  <div className="stepper-container" style={{
    display: 'flex',
    alignItems: 'center',
    width: '100%',
    padding: '0 80px',
    minHeight: 50,
  }}>
    {steps.map((step, i) => {
      const isComplete = i < activeIndex;
      const isActive = i === activeIndex;
      const circleColor = (isComplete || isActive) ? '#008cba' : '#fff';
      const circleBorder = (isComplete || isActive) ? '#004b63' : '#cccccc';
      const textColor = isComplete ? '#757575' : '#333';

      return (
        <React.Fragment key={i}>
          <div style={{
            position: 'relative',
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
            flex: '0 0 auto',
          }}>
            {isComplete ? '✓' : i + 1}
            <div style={{
              position: 'absolute',
              top: 30,
              left: '50%',
              transform: 'translateX(-50%)',
              fontSize: 12,
              color: textColor,
              textAlign: 'center',
              whiteSpace: 'nowrap',
              fontWeight: 'normal',
            }}>
              {step.title}
            </div>
          </div>
          {i < steps.length - 1 && (
            <div style={{
              flex: 1,
              height: 1,
              backgroundColor: i < activeIndex ? '#008cba' : '#cccccc',
              marginLeft: 8,
              marginRight: 8,
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
