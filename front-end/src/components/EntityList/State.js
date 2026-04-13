import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import Multiselect from 'react-bootstrap-multiselect';


class State extends Component {

  static propTypes = {
    states: PropTypes.instanceOf(Immutable.List),
    onChangeState: PropTypes.func.isRequired,
  };

  static defaultProps = {
    states: Immutable.List(),
  };

  state = {
    states: this.props.states,
  }

  componentWillReceiveProps(nextProps) {
    const { states: nextStates } = nextProps;
    const { states } = this.state;
    if (!Immutable.is(states, nextStates)) {
      this.setState({ states: nextStates });
    }
  }

  shouldComponentUpdate(nextProps, nextState) {
    const { states } = this.state;
    return !Immutable.is(states, nextState.states);
  }

  // onSelectAllState = () => {
  //   this.setState({ states: Immutable.List([0, 1, 2]) });
  // }

  onSelectState = (option, checked) => {
    const { states } = this.state;
    const value = parseInt(option.val());
    const selected = (checked) ? states.push(value) : states.filter(state => state !== value);
    this.setState({ states: selected });
  };

  onDropdownHide = () => {
    const { states } = this.state;
    this.props.onChangeState(states);
  }

  buttonTitle = () => '';

  getHistoryOptions = () => {
    const { states } = this.state;
    return (
    [{
      value: 2,
      label: '<span><div class="cycle expired option" /> Closed</span>',
      selected: states.includes(2),
      disabled: states.includes(2) && states.size === 1,
    }, {
      value: 0,
      label: '<span><div class="cycle active option" /> Active</span>',
      selected: states.includes(0),
      disabled: states.includes(0) && states.size === 1,
    }, {
      value: 1,
      label: '<span><div class="cycle future option" /> Planned</span>',
      selected: states.includes(1),
      disabled: states.includes(1) && states.size === 1,
    }]
    );
  }

  // includeSelectAllOption
  // onSelectAll={this.onSelectAllState}
  // onDeselectAll={this.onSelectAllState}
  // selectAllText="<span><div class='cycle all option' /> All States</span>"
  render() {
    return (
      <Multiselect
        multiple
        enableHTML
        data={this.getHistoryOptions()}
        onChange={this.onSelectState}
        buttonWidth="100%"
        nonSelectedText="Select State"
        allSelectedText="<span><div class='cycle all option' /> All States</span>"
        selectAllNumber={false}
        onDropdownHide={this.onDropdownHide}
        buttonTitle={this.buttonTitle}
      />
    );
  }
}

export default State;
