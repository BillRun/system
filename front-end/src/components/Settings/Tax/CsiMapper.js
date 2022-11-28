import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { sentenceCase } from 'change-case';
import { FormGroup, Col, ControlLabel, Panel } from 'react-bootstrap';
import Field from '@/components/Field';
import { getFieldName } from '@/common/Util';


class CsiMapper extends Component {

  static propTypes = {
    fileType: PropTypes.string,
    usageType: PropTypes.string,
    csiMap: PropTypes.instanceOf(Immutable.Map),
    options: PropTypes.instanceOf(Immutable.List),
    disabled: PropTypes.bool,
    onChange: PropTypes.func.isRequired,
  };

  static defaultProps = {
    fileType: '',
    usageType: '',
    csiMap: Immutable.Map(),
    options: Immutable.List(),
    disabled: false,
  };

  shouldComponentUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
    return this.props.disabled !== nextProps.disabled
      || this.props.fileType !== nextProps.fileType
      || this.props.usageType !== nextProps.usageType
      || !Immutable.is(this.props.options, nextProps.options)
      || !Immutable.is(this.props.csiMap, nextProps.csiMap);
  }

  onChangeOrigNum = (value) => {
    const { fileType, usageType } = this.props;
    this.props.onChange(fileType, usageType, 'orig_num', value);
  }

  onChangeTermNum = (value) => {
    const { fileType, usageType } = this.props;
    this.props.onChange(fileType, usageType, 'term_num', value);
  }

  getOptions = () => {
    const { options } = this.props;
    return options
      .map(option => ({label: `${getFieldName(option, 'lines', sentenceCase(option))}`, value: option}))
      .toArray();
  }

  render() {
    const { fileType, usageType, csiMap, disabled } = this.props;
    const options = this.getOptions()
    return (
      <Panel header={`${fileType} - ${usageType}`}>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            Origin Number
          </Col>
          <Col sm={8} lg={9}>
            <Field
              fieldType="select"
              value={csiMap.get('orig_num', '')}
              onChange={this.onChangeOrigNum}
              options={options}
              disabled={disabled}
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            Term Number
          </Col>
          <Col sm={8} lg={9}>
            <Field
              fieldType="select"
              value={csiMap.get('term_num', '')}
              onChange={this.onChangeTermNum}
              options={options}
              disabled={disabled}
            />
          </Col>
        </FormGroup>
      </Panel>
    );
  }
}

export default CsiMapper;
