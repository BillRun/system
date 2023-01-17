import React, {Component} from 'react';
import {bindActionCreators} from 'redux';
import {connect} from 'react-redux';
import Immutable from 'immutable';
import { Panel } from 'react-bootstrap';
import { setSegmentation, addSegmentation, deleteSegmentation } from '@/actions/exportGeneratorActions';
import Help from '../../Help';
import { CreateButton } from '@/components/Elements';
import Segments from './Segments';

class Segmentation extends Component {

  constructor(props) {
    super(props);
    this.onSelectField = this.onSelectField.bind(this);
    this.onDelete = this.onDelete.bind(this);
  }

  onSelectField(index, key, value) {
    this.props.setSegmentation(index, key, value);
  }

  onDelete(index) {
    this.props.deleteSegmentation(index);
  }

  render() {
    const { fields, segments } = this.props;
    const options = fields.map(val => ({value: val, label: val.get('name')})).toJS();
    return (
      <div className="Segmentation">
        Please add segments filters for Export generator.
        <br/>
        <br/>
        <Panel header={<h3>Segments <Help contents="Each Segment should have a field and ranges value" /></h3>}>
          <div className="form-group row form-inner-edit-row mr0 ml0">
            <div className="col-sm-6"><label htmlFor="date_field">Field</label></div>
            <div className="col-sm-2"><label htmlFor="date_field">From</label></div>
            <div className="col-sm-2"><label htmlFor="date_field">To</label></div>
          </div>
          {segments.map((entity, index) => (
            <Segments
              options={options}
              index={index}
              segment={entity}
              onSelectField={this.onSelectField}
              onDelete={this.onDelete}
              key={index}
            />
          ))}
          <CreateButton onClick={this.props.addSegmentation} label="Add Segment" />
        </Panel>
      </div>
    )
  }
}

function mapDispatchToProps(dispatch) {
  return bindActionCreators({
    setSegmentation,
    addSegmentation,
    deleteSegmentation
  }, dispatch);
}

function mapStateToProps(state, props) {
  return {
    fields: state.exportGenerator.getIn(['inputProcess', 'parser', 'structure'], Immutable.List()), //.toObject(),
    segments: state.exportGenerator.get('segments', Immutable.List())
  };
}

export default connect(mapStateToProps, mapDispatchToProps)(Segmentation);
