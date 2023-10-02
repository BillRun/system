import React from 'react';
import PropTypes from 'prop-types';
import { Map } from 'immutable';
import Field from '@/components/Field';


const SelectDelimiter = (
  { settings, onSetDelimiterType, delimiterOptions, onChangeDelimiter }
) => {
  const onChange = (value) => {
    onChangeDelimiter({ target: { value } });
  };
  return (
    <div className="form-group">
      <div className="col-lg-3">
        <label htmlFor="delimiter">Delimiter</label>
      </div>
      <div className="col-lg-9">
        <div className="col-lg-1" style={{marginTop: 8}}>
          <i className="fa fa-long-arrow-right"></i>
        </div>
        <div className="col-lg-5">
          <div className="input-group">
            <div className="input-group-addon">
              <input
                type="radio"
                name="delimiter-type"
                style={{ verticalAlign: 'middle' }}
                value="separator"
                disabled={!settings.get('file_type', false)}
                onChange={onSetDelimiterType}
                checked={settings.get('delimiter_type', '') === "separator"}
              />
              <small>&nbsp;By delimiter</small>
            </div>
            <Field
              fieldType="select"
              className="delimiter-select"
              allowCreate
              disabled={!settings.get('file_type', '') || settings.get('delimiter_type', '') !== 'separator'}
              onChange={onChange}
              options={delimiterOptions}
              value={settings.get('delimiter', '')}
              placeholder="Select or type..."
              addLabelText="{label}"
            />
          </div>
        </div>
        <div className="col-lg-3" style={{marginTop: 10}}>
          <input
            type="radio"
            name="delimiter-type"
            style={{ verticalAlign: 'middle' }}
            value="fixed"
            disabled={!settings.get('file_type', false)}
            onChange={onSetDelimiterType}
            checked={settings.get('delimiter_type', '') === "fixed"}
          />
          <label htmlFor="delimiter-type">&nbsp;Fixed width
        </label>
        </div>
      </div>
    </div>
  );
};


SelectDelimiter.propTypes = {
  settings: PropTypes.instanceOf(Map),
  delimiterOptions: PropTypes.array,
  onSetDelimiterType: PropTypes.func.isRequired,
  onChangeDelimiter: PropTypes.func.isRequired,
};


SelectDelimiter.defaultProps = {
  settings: Map(),
  delimiterOptions: [
    { value: '	', label: 'Tab' }, // eslint-disable-line no-tabs
    { value: ' ', label: 'Space' },
    { value: ',', label: 'Comma' },
  ],
};

export default SelectDelimiter;
