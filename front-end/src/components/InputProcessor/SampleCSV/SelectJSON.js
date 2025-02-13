import React from 'react';
import { connect } from 'react-redux';

const SelectJSON = (props) => (
  <div className="form-group">
    <div className="col-lg-3">
      <label htmlFor="sample_csv">Select Sample JSON</label>
    </div>
    <div className="col-lg-9">
      <div className="col-lg-1" style={{marginTop: 8}}>
        <i className="fa fa-long-arrow-right"></i>
      </div>
      <div className="col-lg-9">
        <input
            type="file"
            id="sample_csv"
            onChange={ props.onSelectJSON }
            disabled={!props.settings.get('file_type') }
        />
      </div>
    </div>
  </div>    
);

export default connect()(SelectJSON);
