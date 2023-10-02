import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { Button, Table } from 'react-bootstrap';
import DatePicker from 'react-datepicker';
import classNames from 'classnames';
import Immutable from 'immutable';
import moment from 'moment';


export default class AdvancedFilter extends Component {

  static defaultProps = {
    fields: [],
  };

  static propTypes = {
    fields: PropTypes.array, // eslint-disable-line react/forbid-prop-types
    onFilter: PropTypes.func,
  };

  state = {
    filters: Immutable.Map(),
  }

  onChangeInput = (e) => {
    const { id, value } = e.target;
    this.onChange(id, value);
  }

  onChangeDate = (id, momentDate) => {
    const value = momentDate ? momentDate.toJSON() : '';
    this.onChange('urt', value);
  }

  onChangeDateFrom = (id, momentFromDate) => {
    const fromValue = momentFromDate ? momentFromDate.toJSON() : '';
    const { filters } = this.state;
    const value = filters.get(id, {});
    value.from = fromValue;
    this.onChange(id, value);
  }

  onChangeDateTo = (id, momentToDate) => {
    const toValue = momentToDate ? momentToDate.toJSON() : '';
    const { filters } = this.state;
    const value = filters.get(id, {});
    value.to = toValue;
    this.onChange(id, value);
  }


  onChange = (filterName, value) => {
    const { filters } = this.state;
    if (value) {
      this.setState({ filters: filters.set(filterName, value) });
    } else {
      this.setState({ filters: filters.delete(filterName) });
    }
  }

  onClear = () => {
    const filters = Immutable.Map();
    this.setState({ filters });
    this.props.onFilter(filters.toJS());
  }

  onApplay = () => {
    const { filters } = this.state;
    this.props.onFilter(filters.toJS());
  }

  getFilterInput = (field) => {
    const { filters } = this.state;
    const value = filters.get(field.id, '');

    switch (field.type) {
      case 'select': {
        const options = field.options.map((option, i) => {
          let val;
          let key;
          if (option && option.key && option.val) {
            val = option.val;
            key = option.key;
          } else {
            key = val = option;
          }
          return (<option key={i} value={key}>{val}</option>);
        });
        return (
          <select id={field.id} className="form-control" value={value} onChange={this.onChangeInput}>
            [
              <option key="select" value="">Select...</option>,
              ...{options}
            ]
          </select>

        );
      }
      case 'date': {
        return (
          <DatePicker
            className="form-control"
            dateFormat="DD/MM/YYYY"
            selected={value.length ? moment(value) : null}
            onChange={this.onChangeDate.bind(this, field.id)}
            isClearable={true}
            placeholderText="Select Date..."
          />
        );
      }
      case 'date-range': {
        let dateFrom = value ? value.from : '';
        dateFrom = (dateFrom ? moment(dateFrom) : null);

        let dateTo = value ? value.to : '';
        dateTo = (dateTo ? moment(dateTo) : null);
        return (
          <div style={{ width: '100%' }}>
            <div className="pull-left" style={{ width: '48%' }}>
              <DatePicker
                className="form-control"
                dateFormat="DD/MM/YYYY"
                selected={dateFrom}
                selectsStart
                startDate={dateFrom}
                endDate={dateTo}
                onChange={this.onChangeDateFrom.bind(this, field.id)}
                isClearable={true}
                placeholderText="Select Start Date..."
              />
            </div>
            <div className="pull-right" style={{ width: '48%' }}>
              <DatePicker
                className="form-control"
                dateFormat="DD/MM/YYYY"
                selected={dateTo}
                selectsEnd
                startDate={dateFrom}
                endDate={dateTo}
                onChange={this.onChangeDateTo.bind(this, field.id)}
                isClearable={true}
                placeholderText="Select End Date..."
              />
            </div>
          </div>
        );
      }
      default:
        return (<input id={field.id} onChange={this.onChangeInput} value={value} placeholder="Search..." className="form-control" />);
    }
  }

  renderTitles = (field, index, fields) => (
    <th style={{ width: `${100 / (fields.length === 0 ? 1 : fields.length)}%` }} key={index}>{field.title}</th>
  );

  renderInputs = (field, index, fields) => {
    const input = this.getFilterInput(field);
    const tdClass = classNames({
      pl0: index === 0,
      pr0: index === (fields.length - 1),
    });
    return (<td className={tdClass} key={index}>{input}</td>);
  }

  renderActions = () => {
    const { fields } = this.props;
    return ([
      <td colSpan={fields.length - 1} className="pl0" key="search">
        <Button bsStyle="primary" onClick={this.onApplay} className="full-width mr10"><i className="fa fa-search" />&nbsp;Search</Button>
      </td>,
      <td className="pr0" key="reset">
        <Button onClick={this.onClear} className="full-width"><i className="fa fa-eraser danger-red" />&nbsp;Reset</Button>
      </td>,
    ]);
  }

  render() {
    const { fields } = this.props;
    const titles = fields.map(this.renderTitles);
    const inputs = fields.map(this.renderInputs);
    const actions = this.renderActions();

    return (
      <div className="AdvancedFilter">
        <Table responsive className="mb0">
          <thead>
            <tr>{ titles }</tr>
          </thead>
          <tbody>
            <tr>{ inputs }</tr>
            <tr>{ actions }</tr>
          </tbody>
        </Table>
      </div>
    );
  }
}
