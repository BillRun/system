import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { FormGroup, Col, ControlLabel, HelpBlock } from 'react-bootstrap';
import { TextWithButton } from '@/components/Elements';
import Field from '@/components/Field';
import { showWarning } from '@/actions/alertsActions';
import {
  getConfig,
  formatSelectOptions,
} from '@/common/Util';


class CollectionTypeHttp extends Component {

  static propTypes = {
    content: PropTypes.instanceOf(Immutable.Map),
    httpMethods: PropTypes.instanceOf(Immutable.List),
    httpDecoders: PropTypes.instanceOf(Immutable.List),
    errors: PropTypes.instanceOf(Immutable.Map),
    onChange: PropTypes.func.isRequired,
    dispatch: PropTypes.func.isRequired,
  };

  static defaultProps = {
    content: Immutable.Map(),
    errors: Immutable.Map(),
    httpMethods: getConfig(['collections', 'http', 'mthods'], Immutable.List()),
    httpDecoders: getConfig(['collections', 'http', 'decoders'], Immutable.List()),
  };

  shouldComponentUpdate(nextProps, nextState) { // eslint-disable-line no-unused-vars
    const { content } = this.props;
    return !Immutable.is(content, nextProps.content);
  }

  onChangeMethod = (value) => {
    this.props.onChange(['method'], value);
  }

  onChangeDcoder = (value) => {
    this.props.onChange(['decoder'], value);
  }

  onChangeUrl = (e) => {
    const { value } = e.target;
    this.props.onChange(['url'], value);
  }

  onChangeCustomParameter = field => (value) => {
    this.props.onChange(['custom_parameter', field], value);
  }

  onAddCustomParameter = (field) => {
    const { content } = this.props;
    if (!content.hasIn(['custom_parameter', field])) {
      this.props.onChange(['custom_parameter', field], '');
    } else {
      this.props.dispatch(showWarning(`Custom Parameter ${field} already exists`));
    }
  }

  onRemoveCustomParameter = field => () => {
    const { content } = this.props;
    if (content.hasIn(['custom_parameter', field])) {
      const newContent = content
        .get('custom_parameter', Immutable.Map())
        .filter((value, key) => key !== field);
      this.props.onChange(['custom_parameter'], newContent);
    } else {
      this.props.dispatch(showWarning(`Custom Parameter ${field} does not exist`));
    }
  }

  renderCustomParameters = () => {
    const { content, errors } = this.props;
    return content.get('custom_parameter', Immutable.Map())
      .map((value, field) => (
        <FormGroup validationState={errors.has(field) ? 'error' : null} key={`custom_parameter_${field}`}>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            {field}
          </Col>
          <Col sm={8} lg={9}>
            <TextWithButton
              key={`custom_parameter_${field}`}
              onChange={this.onChangeCustomParameter(field)}
              onAction={this.onRemoveCustomParameter(field)}
              actionType="remove"
              value={value}
            />
            { errors.has(field) && <HelpBlock>{errors.get(field, '')}</HelpBlock> }
          </Col>
        </FormGroup>
      ))
      .toList()
      .toArray();
  }

  render() {
    const { content, httpMethods, httpDecoders, errors } = this.props;
    const methodOptions = httpMethods.map(formatSelectOptions).toArray();
    const decoderOptions = httpDecoders.map(formatSelectOptions).toArray();
    return (
      <div>
        <FormGroup validationState={errors.has('url') ? 'error' : null}>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            URL<span className="danger-red"> *</span>
          </Col>
          <Col sm={8} lg={9}>
            <Field onChange={this.onChangeUrl} value={content.get('url', '')} />
            { errors.has('url') && <HelpBlock>{errors.get('url', '')}</HelpBlock> }
          </Col>
        </FormGroup>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            HTTP Method
          </Col>
          <Col sm={4}>
            <Field
              fieldType="select"
              options={methodOptions}
              onChange={this.onChangeMethod}
              value={content.get('method', '')}
              clearable={false}
            />
          </Col>
        </FormGroup>
        <FormGroup validationState={errors.has('decoder') ? 'error' : null}>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            Decoder
          </Col>
          <Col sm={4}>
            <Field
              fieldType="select"
              options={decoderOptions}
              onChange={this.onChangeDcoder}
              value={content.get('decoder', '')}
              clearable={false}
            />
            { errors.has('decoder') && <HelpBlock>{errors.get('decoder', '')}</HelpBlock> }
          </Col>
        </FormGroup>
        { this.renderCustomParameters() }
        <hr />
        <FormGroup validationState={errors.has('decoder') ? 'error' : null}>
          <Col sm={3} lg={2}>
            &nbsp;
          </Col>
          <Col componentClass={ControlLabel} sm={8} lg={9}>
            <TextWithButton
              onAction={this.onAddCustomParameter}
              actionLabel="Add custom parameter"
              clearAfterAction={true}
            />
          </Col>
        </FormGroup>
      </div>
    );
  }
}

export default connect(null)(CollectionTypeHttp);
