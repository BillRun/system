import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import BS3Multiselect from '@/components/Filter/BS3Multiselect';


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

  shouldComponentUpdate(nextProps, nextState) {
    const { states } = this.state;
    return !Immutable.is(states, nextState.states);
  }

  componentDidUpdate(prevProps, prevState) {// eslint-disable-line no-unused-vars
    const { states: nextStates } = this.props;
    const { states } = this.state;
    if (!Immutable.is(states, nextStates)) {
      this.setState({ states: nextStates });
    }
  }

  onSelectState = (value = '') => {
    const states = value === ''
      ? Immutable.List()
      : Immutable.List(value.split(',').filter(v => v !== '').map(v => parseInt(v)));
    this.setState({ states }, () => {
      this.props.onChangeState(states);
    });
  };

  getHistoryOptions = () => {
    const { states } = this.state;
    return [{
      value: 2,
      label: (<span><span className="cycle expired option" /> Closed</span>),
      selected: states.includes(2),
      disabled: states.includes(2) && states.size === 1,
    }, {
      value: 0,
      label: (<span><span className="cycle active option" /> Active</span>),
      selected: states.includes(0),
      disabled: states.includes(0) && states.size === 1,
    }, {
      value: 1,
      label: (<span><span className="cycle future option" /> Planned</span>),
      selected: states.includes(1),
      disabled: states.includes(1) && states.size === 1,
    }];
  }

  getStateFilterLabel = () => {
    const { states } = this.state;
    if (states.size === 0 || states.size === 3) {
      return 'All States';
    }
    if (states.size === 1) {
      const stateValue = states.first();
      const option = this.getHistoryOptions().find(item => item.value === stateValue);
      switch (stateValue) {
        case 0: return 'Active';
        case 1: return 'Planned';
        case 2: return 'Closed';
        default: return option ? 'State' : 'State';
      }
    }
    return `${states.size} States`;
  }

  getStateIndicatorClass = () => {
    const { states } = this.state;
    if (states.size === 0 || states.size === 3 || states.size > 1) {
      return 'all';
    }
    const stateValue = states.first();
    switch (stateValue) {
      case 0:  return 'active';
      case 1:  return 'future';
      case 2:  return 'expired';
      default: return 'all';
    }
  }

  renderToggle = () => (
    <span>
      <span className={`cycle ${this.getStateIndicatorClass()} option`} />
      {' '}
      {this.getStateFilterLabel()}
    </span>
  );

  // For BS3Multiselect: data with comma-joined string ids of currently selected
  // (component itself sends back the next CSV in onChange).
  getMultiselectData = () => {
    // pass numeric values as strings — Dropdown.Item keys / checkbox state still work.
    return this.getHistoryOptions().map((o) => ({
      ...o,
      value: String(o.value),
    }));
  };

  onMultiselectChange = (csv) => {
    this.onSelectState(csv);
  };

  render() {
    return (
      <BS3Multiselect
        data={this.getMultiselectData()}
        onChange={this.onMultiselectChange}
        buttonWidth="100%"
        renderToggle={this.renderToggle}
      />
    );
  }
}

export default State;
