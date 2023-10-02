import React, { Component } from 'react';
import { connect } from 'react-redux';

import Field from '@/components/Field';

class FtpDetials extends Component {

  render() {
    const { settings, onChangeField, onChangeCheckboxField } = this.props;
    const period_options = [{min: 1, label: "1 Minute"},
                            {min: 15, label: "15 Minutes"},
                            {min: 30, label: "30 Minutes"},
                            {min: 60, label: "1 Hour"},
                            {min: 360, label: "6 Hours"},
                            {min: 720, label: "12 Hours"},
                            {min: 1440, label: "24 Hours"}].map((opt, key) => (
                              <option value={opt.min} key={key}>{opt.label}</option>
                            ));

    return (
      <div className="FTPDetails">
        <h4>FTP</h4>
        <form className="form-horizontal">
          <div className="form-group">
            <label htmlFor="name" className="col-xs-2 control-label">Name</label>
            <div className="col-xs-4">
              <Field id="name"
                     onChange={onChangeField}
                     value={settings.get('name', '')} />
            </div>
          </div>
          <div className="form-group">
            <label htmlFor="host" className="col-xs-2 control-label">Host</label>
            <div className="col-xs-4">
              <Field id="host"
                     onChange={onChangeField}
                     value={settings.get('host', '')}/>
            </div>
          </div>
          <div className="form-group">
            <label htmlFor="user" className="col-xs-2 control-label">User</label>
            <div className="col-xs-4">
              <Field id="user"
                     onChange={onChangeField}
                     value={settings.get('user', '')} />
            </div>
          </div>
          <div className="form-group">
            <label htmlFor="password" className="col-xs-2 control-label">Password</label>
            <div className="col-xs-4">
              <Field id="password"
                     onChange={onChangeField}
                     value={settings.get('password', '')} />
            </div>
          </div>
          <div className="form-group">
            <label htmlFor="remote_directory" className="col-xs-2 control-label">Directory</label>
            <div className="col-xs-4">
              <Field id="remote_directory"
                     onChange={onChangeField}
                     value={settings.get('remote_directory', '')} />
            </div>
          </div>
          <div className="form-group">
            <label htmlFor="filename_regex" className="col-xs-2 control-label">Regex</label>
            <div className="col-xs-4">
              <Field id="filename_regex"
                     onChange={onChangeField}
                     value={settings.get('filename_regex', '')} />
            </div>
          </div>
          <div className="form-group">
            <label htmlFor="period" className="col-xs-2 control-label">Period</label>
            <div className="col-xs-4">
              <select className="form-control" id="period" onChange={onChangeField} value={settings.get('period', '')}>
                { period_options }
              </select>
            </div>
          </div>

          <div className="form-group">
            <label htmlFor="passive" className="col-xs-2 control-label">Passive</label>
            <div className="col-xs-4">
              <input
                type="checkbox"
                id="passive"
                onChange={onChangeCheckboxField}
                checked={settings.get('passive', false)}
                value="1"
                style={{marginTop: 10}}
              />
            </div>
          </div>
        </form>
      </div>
    );
  }
}

export default connect()(FtpDetials);
