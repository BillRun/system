import React from 'react';
import PropTypes from 'prop-types';
import ReactStepper from 'react-stepper-horizontal';

/**
 * Important !
 * The font size changed from 24, remember to update style file that fix bug
 * src/styles/scss/overrides/react-stepper-horizontal.scss
   * https://github.com/mu29/react-stepper/issues/21
 */

const Stepper = ({steps, activeIndex}) => (
  <div className='stepper-container'>
    <ReactStepper
      titleTop={5}
      lineMarginOffset={10}
      circleTop={0}
      size={25}
      titleFontSize={12}
      circleFontSize={12}
      activeStep={activeIndex}
      steps={steps}
      defaultBorderColor="#cccccc"
      defaultBorderStyle="solid"
      defaultBorderWidth={1}
      activeColor="#008cba"
      activeBorderColor="#004b63"
      activeBorderStyle="solid"
      activeBorderWidth={1}
      completeColor="#008cba"
      completeTitleColor="#757575"
      completeBorderColor="#008cba"
      completeBorderStyle="solid"
      completeBorderWidth={1}
    />
  </div>
);

Stepper.defaultProps = {
  activeStep: 0,
  steps: [],
};

Stepper.propTypes = {
  activeIndex: PropTypes.number,
  steps: PropTypes.arrayOf(
    PropTypes.shape({
      title: PropTypes.string,
    })
  ),
};

export default Stepper;
