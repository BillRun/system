import React, { Component } from 'react';
import PropTypes from 'prop-types';
import moment from 'moment-timezone';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';

export default class DateTime extends Component {

  static defaultProps = {
    data: Immutable.Map(),
    timeZoneOptions:  moment.tz.names().map(label => ({ label, value: label }))
  };

  static propTypes = {
    data: PropTypes.instanceOf(Immutable.Map),
    timeZoneOptions: PropTypes.array,
    onChange: PropTypes.func.isRequired,
  };

  /* commented to avoid un-sync UI and BE */
  // componentDidMount() {
  //   const { data } = this.props;
  //   if (data.get('timezone', '') !== '') {
  //     this.props.onChange('billrun', 'timezone', moment.tz.guess());
  //   }
  // }

  onChange = (value) => {
    this.props.onChange('billrun', 'timezone', value);
  }

  render() {
    const { data, timeZoneOptions } = this.props;
    return (
      <div className="DateTime">
        <Form horizontal>
          <FormGroup controlId="timezone" key="timezone">
            <Col componentClass={ControlLabel} md={2}>
              Time Zone
            </Col>
            <Col sm={6}>
              <Field
                fieldType="select"
                options={timeZoneOptions}
                onChange={this.onChange}
                value={data.get('timezone', '')}
                clearable={false}
              />
            </Col>
          </FormGroup>
        </Form>
      </div>
    );
  }
}
