import React from 'react';
import { connect } from 'react-redux';
import { List } from 'immutable';
import Field from '@/components/Field';

const RealtimeMapping = (props) => {
  const { onChange, onChangeDefault, settings } = props;

  const available_fields = [(<option disabled value="" key={-1}>Select Field...</option>),
                            ...settings.get('fields', []).sortBy(field => field).map((field, key) => (
                              <option value={field} key={key}>{field}</option>
                            ))];

  const multi_available_fields = settings.get('fields', []).sortBy(field => field).map(field => {
    return { label: field, value: field }
  }).toJS();

  const onChangeSessionField = (values) => {
    const e = {target: {id: 'session_id_fields', value: values.split(',')}};
    onChange(e);
  };

  const onChangeRebalance = (values) => {
    const e = { target: { id: 'used_usagev_field', value: values.split(',') } };
    onChange(e);
  };

  const onChangePostpayCharge = (e) => {
    onChange({ target: { id: 'postpay_charge', value: (e.target.value === 'true') } });
  };

  return (
    <div className="RealtimeMapping">
      <form className="form-horizontal CalculatorMapping">

        <div className="form-group">
          <div className="col-lg-3">
            <input
              type="radio"
              name="postpay_charge"
              id="postpay_charge_false"
              value="false"
              onChange={onChangePostpayCharge}
              checked={!settings.getIn(['realtime', 'postpay_charge'], false)}
            />
            <label htmlFor="postpay_charge">&nbsp;Allocation based requests</label>
          </div>
          <div className="col-lg-3">
            <input
              type="radio"
              name="postpay_charge"
              id="postpay_charge_true"
              value="true"
              onChange={onChangePostpayCharge}
              checked={settings.getIn(['realtime', 'postpay_charge'], false)}
            />
            <label htmlFor="postpay_charge">&nbsp;One time charge requests</label>
          </div>
        </div>

        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="request_type_field">Request type field</label>
            <p className="help-block"></p>
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{marginTop: 8}}>
              <i className="fa fa-long-arrow-right"></i>
            </div>
            <div className="col-lg-9">
              <div className="col-lg-6">
                <select
                    id="request_type_field"
                    className="form-control"
                    onChange={ onChange }
                    disabled={settings.getIn(['realtime', 'postpay_charge'], false)}
                    value={settings.getIn(['realtime', 'request_type_field'], '')}>
                  { available_fields }
                </select>
              </div>
            </div>
          </div>
        </div>

        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="pretend_field">Request type pretend field</label>
            <p className="help-block"></p>
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{marginTop: 8}}>
              <i className="fa fa-long-arrow-right"></i>
            </div>
            <div className="col-lg-9">
              <div className="col-lg-6">
                <select
                    id="pretend_field"
                    className="form-control"
                    onChange={ onChange }
                    value={settings.getIn(['realtime', 'pretend_field'], '')}>
                  { available_fields }>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="used_usagev_field">Rebalance fields</label>
            <p className="help-block"></p>
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{marginTop: 8}}>
              <i className="fa fa-long-arrow-right"></i>
            </div>
            <div className="col-lg-9">
              <div className="col-lg-6">
                <Field
                  fieldType="select"
                  options={multi_available_fields}
                  onChange={onChangeRebalance}
                  disabled={settings.getIn(['realtime', 'postpay_charge'], false)}
                  multi={true}
                  value={settings.getIn(['realtime', 'used_usagev_field'], List()).join(',')}
                />
              </div>
            </div>
          </div>
        </div>

        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="used_usagev_field">Cumulative volume</label>
            <p className="help-block" />
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{ marginTop: 8 }}>
              <i className="fa fa-long-arrow-right" />
            </div>
            <div className="col-lg-9">
              <div className="col-lg-6">
                <Field
                  id="used_usagev_accumulative"
                  value={settings.getIn(['realtime', 'used_usagev_accumulative'], false)}
                  disabled={settings.getIn(['realtime', 'postpay_charge'], false)}
                  onChange={onChange}
                  fieldType="checkbox"
                />
              </div>
            </div>
          </div>
        </div>

        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="used_usagev_field">Rebalance on last request only?</label>
            <p className="help-block" />
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{ marginTop: 8 }}>
              <i className="fa fa-long-arrow-right" />
            </div>
            <div className="col-lg-9">
              <div className="col-lg-6">
                <Field
                  id="rebalance_on_final"
                  value={settings.getIn(['realtime', 'rebalance_on_final'], false)}
                  disabled={settings.getIn(['realtime', 'postpay_charge'], false)}
                  onChange={onChange}
                  fieldType="checkbox"
                />
              </div>
            </div>
          </div>
        </div>

        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="">Group requests fields</label>
            <p className="help-block"></p>
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{marginTop: 8}}>
              <i className="fa fa-long-arrow-right"></i>
            </div>
            <div className="col-lg-9">
              <div className="col-lg-6">
                <Field
                  fieldType="select"
                  options={ multi_available_fields }
                  onChange={ onChangeSessionField }
                  disabled={settings.getIn(['realtime', 'postpay_charge'], false)}
                  multi={true}
                  value={settings.getIn(['realtime', 'session_id_fields'], List()).join(',')}
                />
              </div>
            </div>
          </div>
        </div>

        <hr />

        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="initial_request">Initial request</label>
            <p className="help-block">Optional</p>
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{marginTop: 8}}>
              <i className="fa fa-long-arrow-right"></i>
            </div>
            <div className="col-lg-9">
              <div className="col-lg-6">
                <input
                    id="initial_request"
                    type="number"
                    className="form-control"
                    onChange={ onChangeDefault }
                    disabled={settings.getIn(['realtime', 'postpay_charge'], false)}
                    value={ settings.getIn(['realtime', 'default_values', 'initial_request'], 10) }
                />
              </div>
            </div>
          </div>
        </div>

        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="update_request">Update request</label>
            <p className="help-block">Optional</p>
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{marginTop: 8}}>
              <i className="fa fa-long-arrow-right"></i>
            </div>
            <div className="col-lg-9">
              <div className="col-lg-6">
                <input
                    id="update_request"
                    type="number"
                    className="form-control"
                    onChange={ onChangeDefault }
                    disabled={settings.getIn(['realtime', 'postpay_charge'], false)}
                    value={ settings.getIn(['realtime', 'default_values', 'update_request'], 10) }
                />
              </div>
            </div>
          </div>
        </div>

        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="final_request">Final request</label>
            <p className="help-block">Optional</p>
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{marginTop: 8}}>
              <i className="fa fa-long-arrow-right"></i>
            </div>
            <div className="col-lg-9">
              <div className="col-lg-6">
                <input
                    id="final_request"
                    type="number"
                    className="form-control"
                    onChange={ onChangeDefault }
                    disabled={settings.getIn(['realtime', 'postpay_charge'], false)}
                    value={ settings.getIn(['realtime', 'default_values', 'final_request'], 0) }
                />
              </div>
            </div>
          </div>
        </div>

        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="default">Default</label>
            <p className="help-block">Optional</p>
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{marginTop: 8}}>
              <i className="fa fa-long-arrow-right"></i>
            </div>
            <div className="col-lg-9">
              <div className="col-lg-6">
                <input
                    id="default"
                    type="number"
                    className="form-control"
                    onChange={ onChangeDefault }
                    disabled={settings.getIn(['realtime', 'postpay_charge'], false)}
                    value={ settings.getIn(['realtime', 'default_values', 'default'], 15) }
                />
              </div>
            </div>
          </div>
        </div>

      </form>
    </div>
  );
}

export default connect()(RealtimeMapping);
