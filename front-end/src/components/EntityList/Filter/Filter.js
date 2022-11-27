import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
/* COMPONENTS */
import Multiselect from 'react-bootstrap-multiselect';

export default class Filter extends Component {

  static propTypes = {
    filter: PropTypes.instanceOf(Immutable.Map),
    fields: PropTypes.array,
    base: PropTypes.object,
    children: PropTypes.element,
  };

  static defaultProps = {
    filter: Immutable.Map(),
    fields: [],
    base: {},
    children: null,
  };

  constructor(props) {
    super(props);

    this.onChangeFilterString = this.onChangeFilterString.bind(this);
    this.onSelectFilterField = this.onSelectFilterField.bind(this);
    this.onClickFilterBtn = this.onClickFilterBtn.bind(this);
    this.buildQueryString = this.buildQueryString.bind(this);
    this.filterCond = this.filterCond.bind(this);
    let string = '';
    let filter_by = [];
    if (!props.filter.isEmpty()) {
      const firstFilterValue = props.filter.first();
      filter_by = props.filter.keySeq().toArray();
      string = this.unfilterCond(firstFilterValue);
    }
    this.state = { string, filter_by };
  }

  componentDidMount() {
    // this.onClickFilterBtn();
  }

  onChangeFilterString(e) {
    const { value } = e.target;
    this.setState({string: value});
  }

  filterCond(field, value) {
    const { fields } = this.props;
    let found = fields.find(f => f.id === field);
    if (!found) return {"$regex": value, "$options": "i"};
    switch (found.type) {
      case "number":
        return parseFloat(value);
      case "datetime":
        return value;
      case "text":
      default:
        return {"$regex": value, "$options": "i"};
    }
  }

  unfilterCond = (value) => {
    if (typeof value === 'undefined' || value === null) {
      return '';
    }
    if (Immutable.Map.isMap(value)) {
      return value.get('$regex', '');
    }
    return `${value}`;
  }

  buildQueryString() {
    const { string, filter_by } = this.state;
    const { base } = this.props;
    const baseObj = Object.values(base).reduce((acc, value, field) => {
      return Object.assign({}, acc, {
        [field]: this.filterCond(field, value)
      });
    }, {});

    if (!string.replace(/\s/gi, '')) {
      return baseObj;
    }

    const filterObj = filter_by.reduce((acc, field) => {
      return Object.assign({}, acc, {
        [field]: this.filterCond(field, string)
      });
    }, baseObj);
    return filterObj;
  }

  onClickFilterBtn() {
    const { onFilter } = this.props;
    const filter = this.buildQueryString();
    onFilter(filter);
  }

  onClearFilter = () => {
    this.setState({filter_by: [], string: ''}, () => {
      this.onClickFilterBtn();
    });
  };

  onSelectFilterField(option, checked) {
    const value = option.val();
    const { filter_by } = this.state;
    const included = filter_by.includes(value);
    if (checked && included) {
      return;
    }
    if (!checked && included) {
      return this.setState({filter_by: filter_by.filter(f => f !== value)});
    }
    return this.setState({filter_by: filter_by.concat(value)});
  }

  render() {
    const { fields = [], children } = this.props;
    const { filter_by, string } = this.state;

    const fields_options = fields
      .filter(field => field.showFilter !== false)
      .map((field, key) => {
        let selected = filter_by.includes(field.id);
        return {value: field.id, label: field.placeholder, selected };
      });

    return (
      <div className="Filter row" style={{marginBottom: 10}}>
        <div className="filter-warp">
          <div className="pull-left">
            <input id="filter-string"
                   placeholder="Search for..."
                   onChange={ this.onChangeFilterString }
		   value={ string }
                   className="form-control"/>
          </div>
          <div className="pull-left">
            <Multiselect data={fields_options}
                         multiple
                         onChange={this.onSelectFilterField}
                         buttonWidth="100%"
                         nonSelectedText="Search in fields"
            />
          </div>
          <div className="search-button pull-left">
            <button className="btn btn-default search-btn"
                    onClick={this.onClickFilterBtn}
                    type="submit"
                    disabled={(string && filter_by.length === 0) || (!string && filter_by.length === 0)}>
              <i className="fa fa-search"></i>
            </button>
          </div>
          <div className="search-button pull-left">
            <button className="btn btn-default search-btn"
                    onClick={this.onClearFilter}
                    type="button">
              <i className="fa fa-eraser"></i>
            </button>
          </div>
          { children }
        </div>
      </div>
    );
  }
}
