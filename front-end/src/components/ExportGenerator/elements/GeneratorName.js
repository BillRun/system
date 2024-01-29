import React, {Component} from 'react';
import {connect} from 'react-redux';
import {bindActionCreators} from 'redux';
import {setGeneratorName} from '@/actions/exportGeneratorActions';

function mapDispatchToProps(dispatch) {
  return bindActionCreators({
    setGeneratorName
  }, dispatch);
}

function mapStateToProps(state, props) {
  return {
    name: state.exportGenerator.get('name')
  };
}

class GeneratorName extends Component {
  constructor(props) {
    super(props);

    this.onNameChange = this.onNameChange.bind(this);
  }

  onNameChange(event) {
    this.props.setGeneratorName(event.target.value)
  }

  render() {
    return (
      <div className="form-group">
        <div className="col-lg-3">
          <label htmlFor="name">Generator Name</label>
        </div>
        <div className="col-lg-6">
          <input id="name" name="name" className="form-control" onChange={this.onNameChange} value={this.props.name}/>
        </div>
      </div>
    )
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(GeneratorName);
