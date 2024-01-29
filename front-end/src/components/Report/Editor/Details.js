import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { ControlLabel, FormGroup, Col } from 'react-bootstrap';
import { upperCaseFirst } from 'change-case';
import { ReportDescription } from '../../../language/FieldDescriptions';
import Help from '../../Help';
import Field from '@/components/Field';
import { reportTypes } from '@/actions/reportsActions';
import {
  getConfig,
  getFieldName,
  formatSelectOptions,
} from '@/common/Util';

class Details extends Component {

  static propTypes = {
    title: PropTypes.string,
    entity: PropTypes.string,
    entities: PropTypes.instanceOf(Immutable.List),
    type: PropTypes.number,
    mode: PropTypes.string,
    onChangeKey: PropTypes.func,
    onChangeEntity: PropTypes.func,
    onChangeType: PropTypes.func,
  }

  static defaultProps = {
    title: '',
    entity: '',
    entities: Immutable.List(),
    type: 0,
    mode: 'update',
    onChangeKey: () => {},
    onChangeEntity: () => {},
    onChangeType: () => {},
  }

  shouldComponentUpdate(nextProps) {
    const { title, entity, mode, type } = this.props;
    return (
      title !== nextProps.title
      || entity !== nextProps.entity
      || mode !== nextProps.mode
      || type !== nextProps.type
    );
  }

  onChangeTitle = (e) => {
    const { value } = e.target;
    this.props.onChangeKey(value);
  };

  onChangeEntity = (value) => {
    this.props.onChangeEntity(value);
  };

  getEntityOptions = () => {
    const { entities } = this.props;
    return entities.map(option => Immutable.Map({
      value: option,
      label: upperCaseFirst(getConfig(['systemItems', option, 'itemName'], option)),
    }))
    .map(formatSelectOptions)
    .toArray();
  }

  onChangeTypeGrouped = () => {
    this.props.onChangeType(reportTypes.GROPED);
  }

  onChangeTypeSimple = () => {
    this.props.onChangeType(reportTypes.SIMPLE);
  }

  render() {
    const { title, entity, type, mode } = this.props;
    const disabled = mode === 'view';
    const entityOptions = this.getEntityOptions();
    const isGrouped = type === reportTypes.GROPED;
    return (
      <div>
        <Col sm={12}>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3}>
              Name
            </Col>
            <Col sm={7}>
              <Field
                onChange={this.onChangeTitle}
                value={title}
                disabled={disabled}
              />
            </Col>
          </FormGroup>
        </Col>
        <Col sm={12}>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3}>
              Entity
            </Col>
            <Col sm={7}>
              <Field
                fieldType="select"
                options={entityOptions}
                value={entity}
                onChange={this.onChangeEntity}
                disabled={disabled}
                />
            </Col>
          </FormGroup>
        </Col>
        <Col sm={12}>
          <FormGroup>
            <Col componentClass={ControlLabel} sm={3}>
              Report Type
            </Col>
            <Col sm={3} key="pricing-method-1">
              <div className="inline">
                <Field
                  fieldType="radio"
                  name="report-method"
                  id="report-method-simple"
                  value="simple"
                  checked={!isGrouped}
                  onChange={this.onChangeTypeSimple}
                  label={getFieldName(`report_type_${reportTypes.SIMPLE}`, 'report')}
                />
              </div>
              &nbsp;<Help contents={ReportDescription.method_simple} />
            </Col>
            <Col sm={3} key="pricing-method-2">
              <div className="inline">
                <Field
                  fieldType="radio"
                  name="report-method"
                  id="report-method-grouped"
                  value="grouped"
                  checked={isGrouped}
                  onChange={this.onChangeTypeGrouped}
                  label={getFieldName(`report_type_${reportTypes.GROPED}`, 'report')}
                />
              </div>
              &nbsp;<Help contents={ReportDescription.method_grouped} />
            </Col>
          </FormGroup>
        </Col>
      </div>
    );
  }

}

export default Details;
