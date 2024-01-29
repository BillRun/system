import React from 'react';
import PropTypes from 'prop-types';
import { Stepper } from '@/components/Elements';

const steps = [
  {title: 'Choose Input'},
  {title: 'Segmentation'},
  {title: 'FTP Details'}
];

const ExportGeneratorSteps = ({ stepIndex }) => (
  <div className="br-stepper">
    <Stepper activeIndex={stepIndex} steps={steps} />
  </div>
)


ExportGeneratorSteps.propTypes = {
  stepIndex: PropTypes.number.isRequired
}


export default ExportGeneratorSteps;
