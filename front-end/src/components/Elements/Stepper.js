import React from 'react';
import PropTypes from 'prop-types';

/**
 * Stepper — inline replacement for abandoned react-stepper-horizontal.
 * DOM: .stepper-container > div[table] > div[table-cell] × N
 *   each cell contains a circle, a label <a>, and two half-connectors (absolute).
 */

const lineColor = (done) => (done ? '#333333' : 'rgb(224, 224, 224)');

const Stepper = ({ steps = [], activeIndex = 0 }) => (
  <div className="stepper-container" style={{ width: '100%', minHeight: 0, padding: 0 }}>
    <div style={{ display: 'table', width: '100%', margin: '0 auto' }}>
      {steps.map((step, i) => {
        const isComplete = i < activeIndex;
        const isActive   = i === activeIndex;
        const isFirst    = i === 0;
        const isLast     = i === steps.length - 1;

        return (
          <div
            key={i}
            style={{
              width: `${100 / steps.length}%`,
              display: 'table-cell',
              position: 'relative',
              paddingTop: 0,
            }}
          >
            <div style={{
              width: 25,
              height: 25,
              margin: '0 auto',
              backgroundColor: (isComplete || isActive) ? '#008cba' : '#e0e0e0',
              borderRadius: '50%',
              textAlign: 'center',
              padding: 1,
              fontSize: 12,
              lineHeight: '22px',
              color: '#fff',
              display: 'block',
              borderWidth: 1,
              borderColor: (isComplete || isActive) ? '#004b63' : '#cccccc',
              borderStyle: 'solid',
            }}>
              {i + 1}
            </div>

            {/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
            <a style={{
              marginTop: 5,
              fontSize: 12,
              fontWeight: 300,
              textAlign: 'center',
              display: 'block',
              color: isActive ? '#000' : '#757575',
            }}>
              {step.title}
            </a>

            {!isFirst && (
              <div style={{
                position: 'absolute',
                top: 12.5,
                height: 1,
                borderTop: `1px solid ${lineColor(isComplete || isActive)}`,
                left: 0,
                right: '50%',
                marginRight: 22.5,
              }} />
            )}
            {!isLast && (
              <div style={{
                position: 'absolute',
                top: 12.5,
                height: 1,
                borderTop: `1px solid ${lineColor(isComplete)}`,
                right: 0,
                left: '50%',
                marginLeft: 22.5,
              }} />
            )}
          </div>
        );
      })}
    </div>
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
