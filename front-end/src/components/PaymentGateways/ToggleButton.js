import React, { Component } from 'react';

export default class ToggleButton extends Component {

  shouldComponentUpdate(nextProps) {
    if (this.props.enabled === nextProps.enabled) return false;
    return true;
  }

  onClick = () => {
    const { enabled, onClickEnable, onClickDisable } = this.props;
    if (!enabled) return onClickEnable();
    return onClickDisable();
  }

  render() {
    const { enabled } = this.props;
    const text = enabled ? "Disable" : "Enable";
    const className = enabled ? "btn btn-danger" : "btn btn-success";

    return (
      <button type="button" className={className} onClick={this.onClick}>{ text }</button>
    );
  }
}
