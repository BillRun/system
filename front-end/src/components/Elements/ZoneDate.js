import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import moment from 'moment';
import { getConfig } from '@/common/Util';
/**
 * This Component Display a date aligned to the  time zone selected in the server.
 * ( As selected in the  configuration 'billrun.timezone' )
 */
class ZoneDate extends Component {

  static propTypes = {
    value: PropTypes.object.isRequired,
    format:PropTypes.string,
    emptyValue: PropTypes.node,
  };

  static defaultProps = {
    value: moment(),
    format: getConfig('dateFormat', 'DD/MM/YYYY'),
    emptyValue: '-',
  };

  render( ) {
    const { value, format, timezone, emptyValue } = this.props;

    const date = !value ? emptyValue : moment(value).tz(timezone).format(format)
    return (
    <span>
      {date}
    </span>
  );
  }
};


const mapStateToProps = (state, props) => ({
  timezone: state.settings.getIn([ 'billrun','timezone']),
});
export default connect(mapStateToProps)(ZoneDate);
