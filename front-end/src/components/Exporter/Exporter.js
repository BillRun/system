import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import moment from 'moment';
import { sentenceCase } from 'change-case';
import { Form, FormGroup, Col, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';
import { Action } from '@/components/Elements';
import {
  getConfig,
} from '@/common/Util';


class Exporter extends Component {

  static propTypes = {
    entityKey: PropTypes.string,
    exportLabel: PropTypes.string,
    query: PropTypes.any,
    entityOptions: PropTypes.arrayOf(PropTypes.string),
    onExport: PropTypes.func.isRequired,
  };

  static defaultProps = {
    entityKey: '',
    exportLabel: 'Export',
    query: null,
    entityOptions: getConfig(['export', 'allowed_entities'], Immutable.List()).toJS(),
  };

  static defaultQuery = Immutable.Map();
  static defaultOptions = Immutable.Map({
    file_name: "export"
  });

  state = {
    progress: false,
    query: Exporter.defaultQuery,
    options:  Exporter.defaultOptions,
    entity: '',
  }

  static getDerivedStateFromProps(props, state) {
    if (props.entityKey !== '' && props.entityKey !== state.entity) {
      const defaultFileName = Exporter.defaultOptions.get('file_name', '');
      const entitiesName = getConfig(['systemItems', props.entityKey, 'itemsType'], props.entityKey);
      const fileName = `${defaultFileName}_${entitiesName}`;
      return ({
        options: state.options.set('file_name', fileName),
        entity: props.entityKey,
      });
    }
    return null;
  }

  clickExport = () => {
    const { entity, options } = this.state;
    const { query } = this.props;
    this.setState(() => ({ progress: true }));
    this.props.onExport(entity, Immutable.fromJS(query).merge(options));
    this.setState(() => ({ progress: false }));
  }

  onChangeEntity = (value) => {
    const newEntity = (value.length) ? value : '';
    this.setDefaultFileName({ newEntity });
    this.setState(() => ({ entity: newEntity }));
  }

  onChangeRange = (range) => {
    const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]');
    const to = moment(range.get('to', ''));
    const from = moment(range.get('from', ''));
    this.setState((prevState) => ({ query: prevState.query
      .set('to', to.isValid() ? to.set({ second: 0,millisecond: 0 }).utc().format(apiDateTimeFormat) : null )
      .set('from', from.isValid() ? from.set({ second: 0,millisecond: 0 }).utc().format(apiDateTimeFormat) : null )
    }));
    if (from.isValid()) {
      this.setDefaultFileName({ newDate: from });
    }
  }

  onChangeFrom = (date) => {
    if (moment.isMoment(date) && date.isValid()) {
      const apiDateTimeFormat = getConfig('apiDateTimeFormat', 'YYYY-MM-DD[T]HH:mm:ss.SSS[Z]');
      this.setState((prevState) => ({
        query: prevState.query.set('from', date.format(apiDateTimeFormat))
      }));
      this.setDefaultFileName({ newDate: date });
    } else {
      this.setState((prevState) => ({
        query: prevState.query.delete('from')
      }));
      this.setDefaultFileName({ newDate: null });
    }
  }

  onChangeFileName = (e) => {
    const { value } = e.target;
    this.setState((prevState) => {
      const newOptions = (value && value.length > 0)
        ? prevState.options.set('file_name', value)
        : prevState.options.delete('file_name');
      return ({ options: newOptions});
    });
  }

  setDefaultFileName = ({newEntity, newDate}) => {
    const { entity, query, options } = this.state;
    const defaultFileName = Exporter.defaultOptions.get('file_name', '');
    const currentFileName = options.get('file_name', '');
    const isDefaultNameChanged = defaultFileName !== currentFileName;
    const currentEmpty = currentFileName === '';
    const apiDateFormat = getConfig('apiDateFormat', 'YYYY-MM-DD');

    if (typeof newDate !== 'undefined') {
      const newDateLabel = newDate !== null ? `_${newDate.format(apiDateFormat)}` : '';
      const oldDate = query.get('from', null);
      const oldDateLabel = oldDate !== null ? `_${moment(oldDate).format(apiDateFormat)}` : '';
      const entitiesName = getConfig(['systemItems', entity, 'itemsType'], entity);
      if (`${defaultFileName}_${entitiesName}${oldDateLabel}` === currentFileName || currentEmpty || !isDefaultNameChanged) {
        const newFileName = `${defaultFileName}_${entity}${newDateLabel}`;
        this.setState((prevState) => ({
          options: prevState.options.set('file_name', newFileName)
        }));
      }
    } else if (typeof newEntity !== 'undefined') {
      const oldDate = query.get('from', null);
      const dateLabel = oldDate !== null ? `_${moment(oldDate).format(apiDateFormat)}` : '';
      if (`${defaultFileName}_${entity}${dateLabel}` === currentFileName || currentEmpty || !isDefaultNameChanged) {
        const newFileName = `${defaultFileName}_${newEntity}${dateLabel}`;
        this.setState((prevState) => ({
          options: prevState.options.set('file_name', newFileName)
        }));
      }
    }
  }

  render () {
    const { entityKey, entityOptions, exportLabel } = this.props;
    const { query, options, entity, progress } = this.state;
    const entitySelectOptions = entityOptions.map(entityKey => ({
      value: entityKey,
      label: sentenceCase(getConfig(['systemItems', entityKey, 'itemName'], entityKey)),
    }));
    const exportVersion = getConfig(['env', 'exportVersion'], '');
    const fileNameSuffix = (exportVersion === '') ? '.csv' : `_${exportVersion}.csv`;
    const fromToValue = Immutable.Map({
      from: moment(query.get('from', null)).isValid() ? moment(query.get('from', null)) : '',
      to: moment(query.get('to', null)).isValid() ? moment(query.get('to', null)) : '',
    });
    return (
      <Form horizontal>
        { entityKey === '' && (
          <FormGroup>
            <Col sm={3} lg={2} componentClass={ControlLabel}>
              Entity<span className="danger-red"> *</span>
            </Col>
            <Col sm={8} lg={9}>
              <Field
                fieldType="select"
                onChange={this.onChangeEntity}
                options={entitySelectOptions}
                value={entity}
                placeholder="Select entity to export...."
                clearable={false}
              />
            </Col>
          </FormGroup>
        )}
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            Revision date 
          </Col>
          <Col sm={8} lg={9}>
            <Field
              fieldType="range"
              onChange={this.onChangeRange}
              value={fromToValue}
              inputProps={{fieldType: 'datetime', isClearable: true}}
              inputFromProps={{selectsStart: true, endDate:'@valueTo@'}}
              inputToProps={{selectsEnd: true, startDate: '@valueFrom@', endDate: '@valueTo@', minDate: '@valueFrom@'}}
            />
          </Col>
        </FormGroup>
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            File Name
          </Col>
          <Col sm={8} lg={9}>
            <Field
              onChange={this.onChangeFileName}
              value={options.get('file_name', '')}
              suffix={fileNameSuffix}
            />
          </Col>
        </FormGroup>
        <hr />
        <Action
          type="export_csv"
          label={exportLabel}
          actionStyle="primary"
          actionSize="small"
          onClick={this.clickExport}
          enable={!progress}
        />
      </Form>
    );
  }
}

export default Exporter
