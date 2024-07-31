import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, ControlLabel } from 'react-bootstrap';
import Field from '@/components/Field';


class System extends Component {

  static propTypes = {
    onChange: PropTypes.func.isRequired,
    data: PropTypes.instanceOf(Immutable.Map),
  };

  static defaultProps = {
    data: Immutable.Map(),
  };

  onToggleClosedCycleChanges = (e) => {
    const { value } = e.target;
    this.props.onChange('system', 'closed_cycle_changes', value);
  }

  render() {
    const { data } = this.props;
    const checkboxStyle = { marginTop: 10 };
    return (
      <div className="DateTime">
        <Form horizontal>
          <FormGroup>
            <Col componentClass={ControlLabel} md={2} />
            <Col sm={6} style={checkboxStyle}>
              <Field
                fieldType="checkbox"
                value={data.get('closed_cycle_changes', '')}
                onChange={this.onToggleClosedCycleChanges}
                label="Allow making changes to entities in closed billing cycles"
              />
            </Col>
          </FormGroup>
        </Form>
      </div>
    );
  }
}


export default System;
