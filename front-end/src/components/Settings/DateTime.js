import React, { Component } from 'react';
import PropTypes from 'prop-types';
import moment from 'moment-timezone';
import Immutable from 'immutable';
import { Form, Col } from 'react-bootstrap';
import { ControlLabel, FormGroup } from '@/common/BootstrapCompat';
import Field from '@/components/Field';

export default class DateTime extends Component {

  static defaultProps = {
    data: Immutable.Map(),
    timeZoneOptions:  moment.tz.names().map(label => ({ label: `${label} ${moment().tz(label).format('Z')}`, value: label }))
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
    <Form className="form-horizontal">
          <FormGroup controlId="timezone" key="timezone">
            <Col as={ControlLabel} md={2}>
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
