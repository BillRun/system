import React, {Component} from 'react';
import PropTypes from 'prop-types';
import {Button} from 'react-bootstrap';
import Field from '@/components/Field';


class Segments extends Component {
  static propTypes = {
    index: PropTypes.number.isRequired,
    segment: PropTypes.object.isRequired,
    onSelectField: PropTypes.func.isRequired,
    onDelete: PropTypes.func.isRequired
  };

  constructor(props) {
    super(props);
    this.onFieldChange = this.onFieldChange.bind(this);
    this.onValueChange = this.onValueChange.bind(this);
    this.onDeleteLine = this.onDeleteLine.bind(this);
  }

  onFieldChange(val) {
    this.props.onSelectField(this.props.index, 'field', val);
  }

  onValueChange(event) {
    this.props.onSelectField(this.props.index, event.target.name, event.target.value);
  }

  onDeleteLine() {
    this.props.onDelete(this.props.index);
  }

  render() {
    const { segment, options } = this.props;
    const fieldName = (segment.getIn(['field', 'name']) === undefined) ? '' : segment.getIn(['field', 'name']);

    return (
      <div className="form-group row form-inner-edit-row mr0 ml0">
        <div className="col-sm-6">
          <Field
            fieldType="select"
            value={fieldName}
            options={options}
            onChange={this.onFieldChange}
            clearable={false}
          />
        </div>

        <div className="col-sm-2">
          <input
            name="from"
            className="form-control"
            onChange={this.onValueChange}
            value={(segment.get('from') === null) ? '' : segment.get('from')}
            disabled={!fieldName} />
        </div>

        <div className="col-sm-2">
          <input
            name="to"
            className="form-control"
            onChange={this.onValueChange}
            value={(segment.get('to') === null) ? '' : segment.get('to')}
            disabled={!fieldName} />
        </div>

        <div className="col-sm-2 actions">
          <Button onClick={this.onDeleteLine} bsSize="small"><i className="fa fa-trash-o danger-red"/>
            &nbsp;Remove</Button>
        </div>
      </div>
    )
  }
}

export default Segments;
