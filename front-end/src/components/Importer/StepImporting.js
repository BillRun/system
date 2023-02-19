import React from 'react';


const StepImporting = () => (
  <div className="StepImporting text-center">
    <p className="pt50">This action can take some time</p>
    <p className="pt20"><i className="fa fa-spinner fa-pulse" /> Please wait...</p>
  </div>
);

StepImporting.propTypes = {
};

StepImporting.defaultProps = {
};

export default StepImporting;
