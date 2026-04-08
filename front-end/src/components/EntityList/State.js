import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import Field from '@/components/Field';


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

  // onSelectAllState = () => {
  //   this.setState({ states: Immutable.List([0, 1, 2]) });
  // }

  onSelectState = (value = '') => {
    const states = value === ''
      ? Immutable.List()
      : Immutable.List(value.split(',').filter(v => v !== '').map(v => parseInt(v)));
    this.setState({ states }, () => {
      this.props.onChangeState(states);
    });
  };

  buttonTitle = () => '';

  getHistoryOptions = () => {
    const { states } = this.state;
    return [{
      value: 2,
      label: 'Closed',
      disabled: states.includes(2) && states.size === 1,
    }, {
      value: 0,
      label: 'Active',
      disabled: states.includes(0) && states.size === 1,
    }, {
      value: 1,
      label: 'Planned',
      disabled: states.includes(1) && states.size === 1,
    }];
  }

  // includeSelectAllOption
  // onSelectAll={this.onSelectAllState}
  // onDeselectAll={this.onSelectAllState}
  // selectAllText="<span><div class='cycle all option' /> All States</span>"
  
  componentDidUpdate(prevProps, prevState) {// eslint-disable-line no-unused-vars
    const { states: nextStates } = this.props;
    const { states } = this.state;
    if (!Immutable.is(states, nextStates)) {
      this.setState({ states: nextStates });
    }
  }

  getStateFilterLabel = () => {
    const { states } = this.state;
    if (states.size === 0 || states.size === 3) {
      return 'All States';
    }
    if (states.size === 1) {
      const stateValue = states.first();
      const option = this.getHistoryOptions().find(item => item.value === stateValue);
      return option ? option.label : 'State';
    }
    return `${states.size} States`;
  }

  getStateIndicatorClass = () => {
    const { states } = this.state;
    if (states.size === 0 || states.size === 3) {
      return 'all';
    }
    if (states.size > 1) {
      return 'all';
    }
    const stateValue = states.first();
    switch (stateValue) {
      case 0:
        return 'active';
      case 1:
        return 'future';
      case 2:
        return 'expired';
      default:
        return 'all';
    }
  }

  renderStatePlaceholder = () => {
    const indicatorClass = this.getStateIndicatorClass();
    return (
      <span>
        <span className={`cycle ${indicatorClass} option`} />
        {' '}
        {this.getStateFilterLabel()}
      </span>
    );
  }

  render() {
    return (
      <Field
        fieldType="select"
        className="entity-state-select"
        multi
        controlShouldRenderValue={false}
        clearable={false}
        closeMenuOnSelect={false}
        value={this.state.states.join(',')}
        options={this.getHistoryOptions()}
        onChange={this.onSelectState}
        placeholder={this.renderStatePlaceholder()}
      />
    );
  }
}

export default State;
