import { getConfig, getUnitLabel } from '@/common/Util';
import Field from '@/components/Field';
import Immutable from 'immutable';
import PropTypes from 'prop-types';
import React, { Component } from 'react';
import { Col, Row } from 'react-bootstrap';
import Help from '../Help';
import UsageTypesSelector from '../UsageTypes/UsageTypesSelector';

export default class FieldsMapping extends Component {

  static propTypes = {
    settings: PropTypes.instanceOf(Immutable.Map),
    usageTypes: PropTypes.instanceOf(Immutable.List),
    usageTypesData: PropTypes.instanceOf(Immutable.List),
    propertyTypes: PropTypes.instanceOf(Immutable.List),
    mongoOps: PropTypes.instanceOf(Immutable.List),
    onSetStaticUsaget: PropTypes.func,
    onSetFieldMapping: PropTypes.func,
  };

  static defaultProps = {
    settings: Immutable.Map(),
    usageTypes: Immutable.List(),
    usageTypesData: Immutable.List(),
    propertyTypes: Immutable.List(),
    mongoOps: getConfig(['inputProcessor', 'dynamicFieldMapping', 'conditions'], Immutable.List()),
    onSetStaticUsaget: () => {},
    onSetFieldMapping: () => {},
  };

  constructor(props) {
    super(props);

    this.onChangePattern = this.onChangePattern.bind(this);
    this.onChangeUsaget = this.onChangeUsaget.bind(this);
    this.addUsagetMapping = this.addUsagetMapping.bind(this);
    this.onSetType = this.onSetType.bind(this);
    this.onChangeStaticUsaget = this.onChangeStaticUsaget.bind(this);
    this.addFieldCondition = this.addFieldCondition.bind(this);

    this.state = {
      fieldName: '',
      op: '',
      opLabel: '',
      pattern: "",
      usaget: "",
      unit: '',
      separateTime: false,
      separateTimeZone: false,
      volumeType: 'field',
      volumeFields: [],
      volumeHardCodedValue: '',
      conditions: [],
    };
  }

  componentWillMount() {
    if (this.props.settings.getIn(['processor', 'time_field'])) {
      this.setState({ separateTime: true });
    }
    const timeZone = this.props.settings.getIn(['processor', 'timezone_field']);
    if (this.props.settings.get('fields').includes(timeZone)) {
      this.setState({ separateTimeZone: true });
    }
  }

  onChangePattern(index, e) {
    const { conditions } = this.state;
    const { value } = e.target;

    conditions[index].pattern = value;
    this.setState({ pattern: value, conditions });
  }

  changeUsaget(val, setStaticUsaget) {
    this.setState({ usaget: val });
    if (setStaticUsaget) {
      this.props.onSetStaticUsaget(val);
    }
  }

  onChangeUsaget(val) {
    this.changeUsaget(val, false);
  }

  onChangeStaticUsaget(usaget) {
    this.changeUsaget(usaget, true);
  }

  onChangeStaticUom = (value) => {
    const e = {
      target: {
        id: 'default_unit',
        value,
      },
    };
    this.props.onSetFieldMapping(e);
  }

  onChangeStaticUsagetVolumeType = (e) => {
    const { value } = e.target;
    const eModified = {
      target: {
        id: 'default_volume_type',
        value,
      },
    };
    this.onChangeStaticUsagetVolumeField('');
    this.props.onSetFieldMapping(eModified);
  }

  onChangeStaticUsagetHardCodedVolume = (e) => {
    this.props.onSetFieldMapping(e);
  }

  onChangeStaticUsagetVolumeField = (volumes) => {
    const value = (volumes.length) ? volumes.split(',') : [];
    const e = {
      target: {
        id: 'default_volume_src',
        value,
      },
    };
    this.props.onSetFieldMapping(e);
  }

  onChangeUom = (unit) => {
    this.setState({ unit });
  }

  onChangeFieldName = (index, e) => {
    const { conditions } = this.state;
    const { value } = e.target;
    conditions[index].src_field = value;
    this.setState({ fieldName: value, conditions });
  }

  onChangeOperator = (index, e) => {
    const { conditions } = this.state;
    const { value } = e.target;
    conditions[index].op = value;
    this.setState({ op: value, conditions, pattern: '' });
  }

  addUsagetMapping(e) {
    const {
      usaget, pattern, unit, volumeType, volumeFields, volumeHardCodedValue, fieldName, op, conditions,
    } = this.state;
    const volumeSrc = (volumeType === 'field' ? volumeFields : volumeHardCodedValue);
    const { onError } = this.props;
    if (!fieldName) {
      onError("Please select usage type field");
      return;
    }
    if (!usaget || !pattern || !unit || !volumeType || !volumeSrc || !op) {
      onError('Please input a value, usage type, unit and volume field/value');
      return;
    }
    if (conditions.length === 0) {
      conditions.push({ src_field: fieldName, pattern, op });
    }
    const args = { usaget, pattern, unit, volumeType, volumeSrc, fieldName, op, conditions };
    this.props.onAddUsagetMapping.call(this, args);
    this.setState({
      pattern: '',
      usaget: '',
      unit: '',
      volumeType: 'field',
      volumeFields: [],
      volumeHardCodedValue: '',
      fieldName: '',
      conditions: [],
      op: '',
    });
  }

  addFieldCondition(e) {
    const { fieldName, pattern, conditions, op } = this.state;
    const { onError } = this.props;

    if (!pattern || !fieldName || !op) {
      onError('Please input a field name, operator and a value');
      return;
    }
    conditions.push({ src_field: '', pattern: '', op: '' });
    this.setState({ conditions });
  }

  removeUsagetMapping(index, e) {
    this.props.onRemoveUsagetMapping.call(this, index);
  }

  onSetType(e) {
    const { value } = e.target;
    this.props.setUsagetType(value);
  }

  onChangeSeparateTime = (e) => {
    const { checked } = e.target;
    if (!checked) {
      this.props.unsetField(['processor', 'time_field']);
      this.onChangeTimeFormatExists();
    }
    this.setState({separateTime: !this.state.separateTime});
  };

  onChangeSeparateTimeZone = (e) => {
    const { checked } = e.target;
    if (!checked) {
      this.props.unsetField(['processor', 'timezone_field']);
      this.onChangeTimeZoneExists();
    }
    this.setState({ separateTimeZone: !this.state.separateTimeZone });
  };

  onChangeDynamicUsagetVolumeType = (e) => {
    const { value } = e.target;
    this.setState({ volumeType: value, volumeHardCodedValue: '', volumeFields: [] });
  }

  onChangeDynamicUsagetHardCodedVolume = (e) => {
    const { value } = e.target;
    this.setState({ volumeHardCodedValue: value });
  }

  onChangeDynamicUsagetVolumeField = (volumes) => {
    const volumeFields = (volumes.length) ? volumes.split(',') : [];
    this.setState({ volumeFields });
  }

  onChangeDateFormat = (e) => {
    this.props.onSetFieldMapping(e);
  }

  onChangeDateFormatExists = () => {
    const e = {
      target: {
        value: undefined,
        id: 'date_format',
      },
    };
    this.onChangeDateFormat(e);
  }

  onChangeTimeFormat = (e) => {
    this.props.onSetFieldMapping(e);
  }

  onChangeTimeZoneFormat = (e) => {
    this.props.onSetFieldMapping(e);
  }

  onChangeTimeFormatExists = () => {
    const e = {
      target: {
        value: undefined,
        id: 'time_format',
      },
    };
    this.onChangeTimeFormat(e);
  }

  onChangeTimeZoneExists = () => {
    const e = {
      target: {
        value: undefined,
        id: 'timezone_field',
      },
    };
    this.onChangeTimeZoneFormat(e);
  }

  getVolumeOptions = () => this.props.settings
    .get('fields', Immutable.List())
    .sortBy(field => field)
    .map(field => ({ label: field, value: field }))
    .toArray();

  onRemoveCondition = (index) => {
    const { conditions } = this.state;

    conditions.splice(index, 1);
    this.setState({ conditions });
  }

  onChangeMultiValues = (index, e) => {
    if (Array.isArray(e)) {
      this.onChangeTagValue(index, e.join(','));
    } else {
      this.onChangeTagValue(index, '');
    }
  };

  onChangeTagValue = (index, e) => {
    const { conditions } = this.state;

    conditions[index].pattern = e;
    this.setState({ pattern: e, conditions });
  };

  render() {
    const {
      separateTime,
      separateTimeZone,
      usaget,
      unit,
      volumeType,
      volumeFields,
      volumeHardCodedValue,
      conditions,
      fieldName,
      pattern,
      op,
    } = this.state;
    const { settings,
            usageTypesData,
            mongoOps,
            propertyTypes,
            onSetFieldMapping } = this.props;

    const available_fields = [(<option disabled value="" key={-1}>Select Field...</option>),
                              ...settings.get('fields', Immutable.List()).sortBy(field => field).map((field, key) => (
                                <option value={field} key={key}>{field}</option>
                              ))];

    const defaultUsaget = settings.get('usaget_type', '') !== 'static' ? '' : settings.getIn(['processor', 'default_usaget'], '');
    const defaultUsagetUnit = settings.get('usaget_type', '') !== 'static' ? '' : settings.getIn(['processor', 'default_unit'], '');

    const defaultVolumeType = settings.get('usaget_type', '') !== 'static' ? '' : settings.getIn(['processor', 'default_volume_type'], 'field');
    const defaultVolumeSrc = settings.get('usaget_type', '') !== 'static' ? '' : settings.getIn(['processor', 'default_volume_src'], []);
    const volumeOptions = this.getVolumeOptions();
    const mongoOperators = [
      (<option disabled value="" key={-1}>Select Field...</option>),
      ...mongoOps.map((operator, key) => (
        <option value={operator.get('id', '')} key={key}>{operator.get('title', '')}</option>
      ))
    ];

    const dateFormat = settings.getIn(['processor', 'date_format']) || '';

    const dateFormatProps = {
      fieldType: 'text',
      id: 'date_format',
      onChange: this.onChangeDateFormat,
    };

    const timeFormat = settings.getIn(['processor', 'time_format']) || '';

    const timeFormatProps = {
      fieldType: 'text',
      id: 'time_format',
      onChange: this.onChangeTimeFormat,
    };

    if (conditions.length === 0) {
      conditions.push({ src_field: fieldName, pattern, op });
    }

    return (
      <form className="form-horizontal FieldsMapping">
        <div className="form-group">
          <div className="col-lg-3">
            <label htmlFor="date_field">Date Time</label>
            <p className="help-block">
              Date and time of record creation<br />
              <a target="_blank" rel="noopener noreferrer" href="http://php.net/manual/en/function.date.php#refsect1-function.date-parameters">Formatting info</a>
            </p>
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{marginTop: 8}}>
              <i className="fa fa-long-arrow-right"></i>
            </div>
            <div className="col-lg-4 form-inner-edit-row">
              <select id="date_field"
                      className="form-control"
                      onChange={onSetFieldMapping}
                      value={settings.getIn(['processor', 'date_field'], '')}>
                { available_fields }
              </select>
            </div>
            <div className="col-lg-5 form-inner-edit-row">
              <Field
                fieldType="toggeledInput"
                value={dateFormat}
                disabledValue=""
                disabled={dateFormat === null || dateFormat === undefined}
                onChange={this.onChangeDateFormatExists}
                label={this.state.separateTime === true ? 'Date format' : 'Date and time format'}
                inputProps={dateFormatProps}
              />
            </div>
            <div className="col-lg-1">
              <Help contents="Check to force a custom format. If not checked, default format is mm/dd/yy" />
            </div>
          </div>

          <div className="col-lg-offset-3 col-lg-9">
            <div className="col-lg-offset-1 col-lg-4">
              <div className="input-group">
                <div className="input-group-addon">
                  <input type="checkbox"
                         checked={separateTime}
                         onChange={this.onChangeSeparateTime}
                  />
                  <small style={{ verticalAlign: 'bottom' }}>&nbsp;Time in a separate field</small>
                </div>
                <select id="time_field"
                        className="form-control"
                        onChange={onSetFieldMapping}
                        disabled={!separateTime}
                        value={settings.getIn(['processor', 'time_field'], '')}>
                  { available_fields }
                </select>
              </div>
            </div>
            <div className="col-lg-5">
              <Field
                fieldType="toggeledInput"
                value={timeFormat}
                disabledValue=""
                disabled={!this.state.separateTime || dateFormat === ''}
                onChange={this.onChangeTimeFormatExists}
                label="Time format"
                inputProps={timeFormatProps}
              />
            </div>
            <div className="col-lg-1">
              <Help contents="To enable, enter date format. For formatting info please check the link on the left" />
            </div>
          </div>

          <div className="col-lg-offset-3 col-lg-9" style={{ marginTop: 30 }}>
            <div className="col-lg-offset-1 col-lg-4">
              <div className="input-group">
                <div className="input-group-addon">
                  <input
                    type="checkbox"
                    checked={separateTimeZone}
                    onChange={this.onChangeSeparateTimeZone}
                  />
                  <small>&nbsp;Timezone in a separate field</small>
                </div>
                <select
                  id="timezone_field"
                  className="form-control"
                  onChange={onSetFieldMapping}
                  disabled={!separateTimeZone}
                  value={settings.getIn(['processor', 'timezone_field'], '')}
                >
                  { available_fields }
                </select>
              </div>
            </div>
          </div>
        </div>
        <div className="separator" />
        <div className="form-group">
          <div className="col-lg-3">
            <label>Usage types / volumes</label>
          </div>
          <div className="col-lg-9">
            <div className="col-lg-1" style={{marginTop: 8}}>
              <i className="fa fa-long-arrow-right"></i>
            </div>

            <div className="col-lg-4 form-inner-edit-row" style={{ marginTop: 8 }}>
              <label htmlFor="static">
                <input
                  type="radio"
                  style={{ verticalAlign: 'top' }}
                  name="usage_types_type"
                  value="static"
                  checked={settings.get('usaget_type', '') === 'static'}
                  onChange={this.onSetType}
                />
                &nbsp;Static
              </label>
            </div>

            <div className="col-lg-4 form-inner-edit-row" style={{ marginTop: 8 }}>
              <label htmlFor="dynamic">
                <input
                  type="radio"
                  style={{ verticalAlign: 'top' }}
                  name="usage_types_type"
                  value="dynamic"
                  checked={settings.get('usaget_type', '') === 'dynamic'}
                  onChange={this.onSetType}
                />
                &nbsp;Dynamic
              </label>
            </div>
          </div>

          {
            settings.get('usaget_type', '') === 'static' &&
            (
              <Col sm={12}>
                <Row className="form-inner-edit-row">
                  <div className="col-lg-7">
                    <div className="col-lg-7">
                      <strong>Usage Type</strong>
                    </div>
                    <div className="col-lg-5">
                      <strong>Unit</strong>
                    </div>
                  </div>
                  <div className="col-lg-4">
                    <strong>Volume field / value</strong>
                  </div>
                </Row>
                <Row>
                  <div className="col-lg-7">
                    <UsageTypesSelector
                      usaget={defaultUsaget}
                      unit={defaultUsagetUnit}
                      onChangeUsaget={this.onChangeStaticUsaget}
                      onChangeUnit={this.onChangeStaticUom}
                      enabled={settings.get('usaget_type', '') === 'static'}
                      showDisplayUnits={true}
                    />
                  </div>
                  <div className="col-lg-1">
                    <Field
                      fieldType="radio"
                      name="static-usaget-volume-type"
                      id="static-usaget-volume-type-field"
                      value="field"
                      checked={defaultVolumeType === 'field'}
                      onChange={this.onChangeStaticUsagetVolumeType}
                      label="By field"
                    />
                    <Field
                      fieldType="radio"
                      name="static-usaget-volume-type"
                      id="static-usaget-volume-type-value"
                      value="value"
                      checked={defaultVolumeType === 'value'}
                      onChange={this.onChangeStaticUsagetVolumeType}
                      label="By value"
                    />
                  </div>
                  <div className="col-lg-3">
                    { defaultVolumeType === 'field' ? (
                      <Field
                        fieldType="select"
                        multi={true}
                        value={defaultVolumeSrc.join(',')}
                        options={volumeOptions}
                        onChange={this.onChangeStaticUsagetVolumeField}
                      />
                    ) : (
                      <Field
                        fieldType="number"
                        id="default_volume_src"
                        value={defaultVolumeSrc}
                        onChange={this.onChangeStaticUsagetHardCodedVolume}
                      />
                    )}
                  </div>
                </Row>
              </Col>)
          }
          {
            settings.get('usaget_type', '') === 'dynamic' &&
            (<div className="col-lg-12 form-inner-edit-row">
              <div className="form-group">
                <div className="col-lg-5">
                  <div className="col-lg-5">
                    <strong>Field Name</strong>
                  </div>
                  <div className="col-lg-3">
                    <strong>Op</strong>
                  </div>
                  <div className="col-lg-4">
                    <strong>Input Value</strong>
                  </div>
                </div>

                <div className="col-lg-7">
                  <div className="col-lg-6 pl0 pr0">
                    <div className="col-lg-7">
                      <strong>Usage Type</strong>
                    </div>
                    <div className="col-lg-5">
                      <strong>Unit</strong>
                    </div>
                  </div>
                  <div className="col-lg-6">
                    <strong>Volume field / value</strong>
                  </div>
                </div>
              </div>
              { settings.getIn(['processor', 'usaget_mapping'], Immutable.List()).map((usageType, key) => (
                <div className="form-group" key={key} style={{ marginRight: 60 }}>
                  <div className="col-lg-6">
                    { settings.getIn(['processor', 'usaget_mapping', key, 'conditions'], Immutable.List()).map((condition, value) => (
                      <div className="row-lg-12" key={`condition-${value}`}>
                        <div className="col-lg-4" style={{ marginLeft: -4 }}>{condition.get('src_field')}</div>
                        <div className="col-lg-4" style={{ marginLeft: 18 }}>
                          {mongoOps.find((op) => op.get('id', '') === condition.get('op', ''), null, Immutable.Map()).get('title', '')}
                        </div>
                        <div className="col-lg-4" style={{ marginLeft: -75 }}>{condition.get('pattern')}</div>
                      </div>
                    ))}
                  </div>
                  <div className="col-lg-6">
                    <div className="col-lg-6 pl0 pr0">
                      <div className="col-lg-7" style={{ marginLeft: -92 }}>{usageType.get('usaget', '')}</div>
                      <div className="col-lg-5" style={{ marginLeft: 50 }}> {getUnitLabel(propertyTypes, usageTypesData, usageType.get('usaget', ''), usageType.get('unit', ''))}</div>
                    </div>
                    <div className="col-lg-3" style={{ marginLeft: -8 }}>
                      {
                        usageType.get('volume_type', 'field') === 'field'
                        ? usageType.get('volume_src', []).join(', ')
                        : usageType.get('volume_src', '')
                      }
                    </div>
                    <div className="col-lg-1">
                      <button
                        type="button"
                        className="btn btn-default btn-sm"
                        disabled={settings.get('usaget_type', '') !== 'dynamic'}
                        onClick={this.removeUsagetMapping.bind(this, key)}
                      >
                        <i className="fa fa-trash-o danger-red" /> Remove
                      </button>
                    </div>
                  </div>
                  <div className="col-lg-offset-1 col-lg-10" style={{ padding: 16 }} >
                    <div className="separator" />
                  </div>
                </div>
              ))}

              <div className="form-group">
                <div className="col-lg-5">
                  { conditions.map((condition, key) => (
                    <div className="row-lg-12 form-inner-edit-row row" key={key}>
                      <div className="col-lg-5">
                        <select
                          id="src_field"
                          className="form-control"
                          onChange={this.onChangeFieldName.bind(this, key)}
                          value={condition.src_field === '' ? '' : condition.src_field}
                          disabled={settings.get('usaget_type', '') !== 'dynamic'}
                        >
                          { available_fields }
                        </select>
                      </div>
                      <div className="col-lg-2">
                        <select
                          id="op"
                          className="form-control"
                          onChange={this.onChangeOperator.bind(this, key)}
                          value={condition.op === '' ? '' : condition.op}
                          disabled={settings.get('usaget_type', '') !== 'dynamic'}
                        >
                          { mongoOperators }
                        </select>
                      </div>
                      <div className="col-lg-4">
                        {(condition.op !== '$in' && condition.op !== '$nin') && (
                          <input
                            className="form-control"
                            onChange={this.onChangePattern.bind(this, key)}
                            disabled={settings.get('usaget_type', '') !== 'dynamic'}
                            value={condition.pattern === '' ? '' : condition.pattern}
                          />
                        )}
                        {(condition.op === '$in' || condition.op === '$nin') && (
                          <Field
                            fieldType="tags"
                            id={`cond-value-${key}`}
                            onChange={this.onChangeMultiValues.bind(this, key)}
                            value={condition.pattern.split(',').filter(val => val !== '')}
                          />
                        )}
                      </div>
                      <div className="col-lg-1">
                        {conditions.length > 1 && (
                          <button
                            type="button"
                            className="btn btn-link"
                            data-dismiss="alert"
                            aria-label="Close"
                            onClick={this.onRemoveCondition.bind(this, key)}
                          >
                            <i className="fa fa-trash-o danger-red" />
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
                <div className="col-lg-7">
                  <div className="col-lg-5">
                    <UsageTypesSelector
                      usaget={usaget}
                      unit={unit}
                      onChangeUsaget={this.onChangeUsaget}
                      onChangeUnit={this.onChangeUom}
                      enabled={settings.get('usaget_type', '') === 'dynamic'}
                      showDisplayUnits={true}
                    />
                  </div>
                  <div className="col-lg-2 field-mapping-radio">
                    <Field
                      fieldType="radio"
                      name="dynamic-usaget-volume-type"
                      id="dynamic-usaget-volume-type-field"
                      value="field"
                      checked={volumeType === 'field'}
                      onChange={this.onChangeDynamicUsagetVolumeType}
                      label="By field"
                    />
                    <Field
                      fieldType="radio"
                      name="dynamic-usaget-volume-type"
                      id="dynamic-usaget-volume-type-value"
                      value="value"
                      checked={volumeType === 'value'}
                      onChange={this.onChangeDynamicUsagetVolumeType}
                      label="By value"
                    />
                  </div>
                  <div className="col-lg-3">
                    { volumeType === 'field' ? (
                      <Field
                        fieldType="select"
                        multi={true}
                        value={volumeFields.join(',')}
                        options={volumeOptions}
                        onChange={this.onChangeDynamicUsagetVolumeField}
                      />
                    ) : (
                    <Field
                        fieldType="number"
                        value={volumeHardCodedValue}
                        onChange={this.onChangeDynamicUsagetHardCodedVolume}
                      />
                    )}
                  </div>
                </div>
              </div>
              <div className="col-lg-1">
                <button
                  type="button"
                  className="btn btn-primary btn-sm"
                  onClick={this.addFieldCondition}
                >
                  <i className="fa fa-plus" /> Add Condition
                </button>
              </div>
            </div>)
          }
          {
            settings.get('usaget_type', '') === 'dynamic' &&
            <div className="form-group" style={{ marginRight: 60 }}>
              <div className="col-lg-offset-1 col-lg-10" style={{ padding: 16 }} >
                <div className="separator" />
              </div>
            </div>
          }
          {
            settings.get('usaget_type', '') === 'dynamic' &&
            <div className="col-lg-offset-10 col-lg-1" style={{ marginLeft: 600 }}>
              <button
                type="button"
                className="btn btn-primary btn-sm"
                onClick={this.addUsagetMapping}
              >
                <i className="fa fa-plus" /> Add Mapping
                </button>
              </div>
        }
        </div>
      </form>
    );
  }
}
