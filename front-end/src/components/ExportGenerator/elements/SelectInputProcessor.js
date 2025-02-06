import React, { Component } from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { List } from 'immutable';
import Field from '@/components/Field';
import { getList } from '@/actions/listActions';
import { selectInputProcessor } from '@/actions/exportGeneratorActions';
import GeneratorName from './GeneratorName';

class SelectInputProcessor extends Component {
  constructor(props) {
    super(props);
    this.onSort = this.onSort.bind(this);
    this.buildQuery = this.buildQuery.bind(this);
    this.onInputProcessChange = this.onInputProcessChange.bind(this);

    this.state = {
      sort: 'name'
    };
  }

  componentDidMount() {
    this.props.getList('input_processors', this.buildQuery());
  }

  buildQuery() {
    return {
      api: 'settings',
      params: [
        { category: 'file_types' },
        { sort: 'name' },
        { data: JSON.stringify({}) }
      ]
    };
  }

  onSort(sort) {
    this.setState({sort}, () => {
      this.props.dispatch(getList('input_processors', this.buildQuery()));
    });
  }

  onInputProcessChange(val) {
    const { inputProcessors } = this.props;
    const selected = inputProcessors.filter(item => item.get('file_type', '') === val).first();
    this.props.selectInputProcessor(selected);
  }


  render() {
    const { inputProcessors, settings, selectedProcess } = this.props;
    const options = [];
    const selectedProcessor = (selectedProcess === undefined) ? '' : selectedProcess.get('file_type', '');
    if (List.isList(inputProcessors)) {
      inputProcessors.map(item => options.push({ value: item.get('file_type', ''), label: item.get('file_type', '') }));
    }

    return (
      <div>
        <form className="InputProcessor form-horizontal">
          <h3 style={{ marginBottom: '35px' }}>Choose Input Processor</h3>
          <GeneratorName name={settings.get('name', '')} />
          <div className="form-group">
            <div className="col-lg-3">
              <label htmlFor="file_type">Please select Input Processor</label>
            </div>
            <div className="col-lg-6">
              <Field
                fieldType="select"
                value={selectedProcessor}
                options={options}
                onChange={this.onInputProcessChange}
                clearable={false}
              />
            </div>
          </div>
        </form>
      </div>
    );
  }
}

function mapDispatchToProps(dispatch) {
  return bindActionCreators({
    getList,
    selectInputProcessor }, dispatch);
}

function mapStateToProps(state, props) {
  return {
    inputProcessors: state.list.get('input_processors', []) || [],
    selectedProcess: state.exportGenerator.get('inputProcess')
  };
}

export default connect(mapStateToProps, mapDispatchToProps)(SelectInputProcessor);
