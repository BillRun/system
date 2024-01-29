import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { showDanger } from '@/actions/alertsActions';
import Templates from '../../config/Templates';

export default class SelectTemplate extends Component {
  constructor(props) {
    super(props);

    this.state = {
      type: 'api',
      selected: '',
      format: 'json',
      template: Object.keys(Templates)[0]
    };
  }

  onSelectFormat = (e) => {
    const { value } = e.target;
    this.setState({format: value});
  };

  onSelectType = (e) => {
    const { value } = e.target;
    this.setState({type: value});
  };

  onCheck = (e) => {
    const { value } = e.target;
    this.setState({selected: value});
  };

  onSelectTemplate = (e) => {
    const { value } = e.target;
    this.setState({template: value});
  };

  handleCancel = () => {
    this.context.router.push({
      pathname: 'input_processors'
    });
  };

  buildQuery = () => {
    const { type, format, selected, template } = this.state;
    const action = "new";
    if (type === "api") {
      return {
        action,
        type,
        format
      };
    }

    if (selected === "predefined") {
      return {
        action,
        template
      };
    }

    return {
      action
    };
  };

  handleNext = () => {
    const { selected, type } = this.state;
    if (selected === '' && type !== 'api') {
      this.props.dispatch(showDanger('Please choose one option'));
    } else {
      this.context.router.push({
        pathname: 'input_processor',
        query: this.buildQuery(),
      });
    }
  };

  render() {
    const { selected, template } = this.state;

    const template_options = Object.keys(Templates).map((type, idx) => (
      <option value={type} key={idx}>{type}</option>
    ));

    return (
      <div className="row">
        <div className="col-lg-12">
          <div className="panel panel-default">
            <div className="panel-heading">
              <span>Create new input processor</span>
            </div>
            <div className="panel-body">
              <form className="form-horizontal">

                <div className="form-group">
                  <div className="col-lg-3 col-md-4">
		    <label>
                      <input type="radio"
                             name="select-type"
                             value="api"
                             checked={ this.state.type === "api" }
                             onChange={ this.onSelectType } /> API-based
		    </label>
                  </div>
                </div>

                <div className="form-group" style={{ marginLeft: 15 }}>
                  <div className="col-lg-3">
                    <label>
                      <input
                          type="radio"
                          name="format"
                          value="json"
                          disabled={ this.state.type !== "api" }
                          checked={ this.state.format === "json" || this.state.type === 'api'}
                          onChange={ this.onSelectFormat } /> I will manually configure a JSON API
                    </label>
                  </div>
                </div>

                <div className="form-group">
                  <div className="col-lg-3 col-md-4">
		    <label>
                      <input type="radio"
                             name="select-type"
                             value="file"
                             checked={ this.state.type === "file" }
                             onChange={ this.onSelectType } /> File-based
		    </label>
                  </div>
                </div>

                <div className="form-group" style={{ marginLeft: 15 }}>
                  <div className="col-lg-3 col-md-4">
		    <label>
                      <input type="radio"
                             name="select-template"
                             value="predefined"
                             disabled={ this.state.type !== "file" }
                             checked={ this.state.selected === "predefined" }
                             onChange={ this.onCheck } /> I will use predefined input processor
		    </label>
                  </div>
                  <div className="col-lg-7 col-md-7">
                    <select className="form-control"
                            value={template}
                            onChange={this.onSelectTemplate}
                            disabled={selected !== "predefined" || this.state.type !== "file" }>
                      { template_options }
                    </select>
                  </div>
                </div>
                <div className="form-group" style={{ marginLeft: 15 }}>
                  <div className="col-lg-3 col-md-4">
		    <label>
                      <input type="radio"
                             name="select-template"
                             value="manual"
                             disabled={ this.state.type !== "file" }
                             checked={this.state.selected === "manual"}
                             onChange={this.onCheck} /> I will configure a custom input processor
		    </label>
                  </div>
                </div>

                <div style={{marginTop: 12, float: "right"}}>
                  <button className="btn btn-default"
                          type="button"
                          onClick={this.handleCancel}
                          style={{marginRight: 12}}>
                    Cancel
                  </button>
                  <button className="btn btn-primary"
                          type="button"
                          onClick={this.handleNext}>
                    Next
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    );
  }
}

SelectTemplate.contextTypes = {
  router: PropTypes.object.isRequired
};
