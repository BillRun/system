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
    entityOptions: PropTypes.arrayOf(PropTypes.string),
    onExport: PropTypes.func.isRequired,
  };

  static defaultProps = {
    entityKey: '',
    exportLabel: 'Export',
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
      const defaultFuleName = Exporter.defaultOptions.get('file_name', '');
      const entitiesName = getConfig(['systemItems', props.entityKey, 'itemsType'], props.entityKey);
      const fileName = `${defaultFuleName}_${entitiesName}`
      return ({
        options: state.options.set('file_name', fileName),
        entity: props.entityKey,
      });
    }
    return null;
  }

  clickExport = () => {
    const { entity, query, options } = this.state;
    this.setState(() => ({ progress: true }));
    this.props.onExport(entity, Immutable.Map({query, options}));
    this.setState(() => ({ progress: false }));
  }

  onChangeEntity = (value) => {
    const newEntity = (value.length) ? value : '';
    this.setDefaultFileName({ newEntity });
    this.setState(() => ({ entity: newEntity }));
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
    const correntFileName = options.get('file_name', '');
    const isDefaultNameChnaged = defaultFileName !== correntFileName;
    const currentEmpty = correntFileName === '';
    const apiDateFormat = getConfig('apiDateFormat', 'YYYY-MM-DD');

    if (typeof newDate !== 'undefined') {
      const newDateLable = newDate !== null ? `_${newDate.format(apiDateFormat)}` : '';
      const oldDate = query.get('from', null);
      const oldDateLabel = oldDate !== null ? `_${moment(oldDate).format(apiDateFormat)}` : '';
      if (`${defaultFileName}_${entity}${oldDateLabel}` === correntFileName || currentEmpty || !isDefaultNameChnaged) {
        const newFileName = `${defaultFileName}_${entity}${newDateLable}`;
        this.setState((prevState) => ({
          options: prevState.options.set('file_name', newFileName)
        }));
      }
    } else if (typeof newEntity !== 'undefined') {
      const oldDate = query.get('from', null);
      const dateLabel = oldDate !== null ? `_${moment(oldDate).format(apiDateFormat)}` : '';
      if (`${defaultFileName}_${entity}${dateLabel}` === correntFileName || currentEmpty || !isDefaultNameChnaged) {
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
    const entitySeletOptions = entityOptions.map(entityKey => ({
      value: entityKey,
      label: sentenceCase(getConfig(['systemItems', entityKey, 'itemName'], entityKey)),
    }));
    const fromValue = moment(query.get('from', null));
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
                options={entitySeletOptions}
                value={entity}
                placeholder="Select entity to export...."
                clearable={false}
              />
            </Col>
          </FormGroup>
        )}
        <FormGroup>
          <Col componentClass={ControlLabel} sm={3} lg={2}>
            From
          </Col>
          <Col sm={8} lg={9}>
            <Field
              fieldType="date"
              onChange={this.onChangeFrom}
              value={fromValue}
              isClearable={true}
              showYearDropdown={true}
              placeholderText="Select Date..."
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
              suffix=".csv"
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
