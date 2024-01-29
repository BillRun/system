import React from 'react';
import { connect } from 'react-redux';

const SelectCSV = (props) => {
  const { settings, onSelectSampleCSV } = props;

  const onFileReset = (e) => {
    e.target.value = null;
  };

  return (
    <div className="form-group">
      <div className="col-lg-3">
        <label htmlFor="sample_csv">Select Sample CSV</label>
        <p className="help-block">Notice: Spaces will be convereted to underscores</p>
      </div>
      <div className="col-lg-9">
        <div className="col-lg-1" style={{marginTop: 8}}>
          <i className="fa fa-long-arrow-right"></i>
        </div>
        <div className="col-lg-9">
          <input type="file" id="sample_csv"
                 accept=".csv"
                 onClick={onFileReset}
                 onChange={onSelectSampleCSV}
                 disabled={!settings.get('file_type') || !settings.get('delimiter_type') ||
                           settings.get('delimiter_type') !== "separator" || settings.get('delimiter') === ''} />
        </div>
      </div>
    </div>
  );
}

export default connect()(SelectCSV);
