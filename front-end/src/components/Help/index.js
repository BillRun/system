import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { OverlayTrigger, Tooltip } from 'react-bootstrap';

class Help extends Component {
  constructor(props) {
    super(props);
    this.handleTouchTap = this.handleTouchTap.bind(this);
    this.handleRequestClose = this.handleRequestClose.bind(this);
    this.state = { open: false };
  }

  handleTouchTap(event) {
    this.setState({
      open: true,
      anchorEl: event.currentTarget
    });
  }

  handleRequestClose() {
    this.setState({ open: false });
  }

  render() {
    const { contents } = this.props;
    if (!contents || contents === '') {
      return (null);
    }

    const tooltip = (
      <Tooltip id="tooltip">{contents}</Tooltip>
    );

    return (
      <span style={{margin: 5}}>
        <OverlayTrigger placement="top" overlay={tooltip}>
          <i className="fa fa-question-circle" style={{cursor: "pointer"}}></i>
        </OverlayTrigger>
      </span>
    );
  }
}

Help.propTypes = {
  contents: PropTypes.string.isRequired
};

export default Help;
