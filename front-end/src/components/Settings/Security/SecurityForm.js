import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import DatePicker from 'react-datepicker';
import moment from 'moment';
import { Form, FormControl, FormGroup, Col, ControlLabel, HelpBlock } from 'react-bootstrap';
import { ModalWrapper } from '@/components/Elements';
import { getConfig } from '@/common/Util';


class SecurityForm extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    onCancel: PropTypes.func.isRequired,
    onSave: PropTypes.func.isRequired,
  };

  static defaultProps = {
    item: Immutable.Map(),
  };

  state = {
    item: this.props.item,
    title: this.props.item.isEmpty() ? 'New' : 'Edit',
    action: this.props.item.isEmpty() ? 'create' : 'edit',
  };

  onChangeName = (e) => {
    const { item } = this.state;
    const { value } = e.target;
    this.setState({ item: item.set('name', value) });
  }

  onChangeDateFrom = (momentFromDate) => {
    const { item } = this.state;
    const apiFormat = getConfig('apiDateFormat', 'YYYY-MM-DD');
    const fromValue = momentFromDate ? momentFromDate.format(apiFormat) : '';
    this.setState({ item: item.set('from', fromValue) });
  }

  onChangeDateTo = (momentFromDate) => {
    const { item } = this.state;
    const apiFormat = getConfig('apiDateFormat', 'YYYY-MM-DD');
    const fromValue = momentFromDate ? momentFromDate.format(apiFormat) : '';
    this.setState({ item: item.set('to', fromValue) });
  }

  onSave = () => {
    const { item, action } = this.state;
    this.props.onSave(item, action);
  }

  render() {
    const { item, action } = this.state;
    const title = action === 'create' ? 'New' : 'Edit';

    return (
      <ModalWrapper title={`${title} Secret`} show={true} onOk={this.onSave} onCancel={this.props.onCancel} labelOk="Save" >
        <Form horizontal>
          <FormGroup controlId="name" key="name">
            <Col componentClass={ControlLabel} sm={3}>
              Name
            </Col>
            <Col sm={8}>
              <FormControl type="text" name="name" onChange={this.onChangeName} value={item.get('name', '')} />
            </Col>
          </FormGroup>

          <FormGroup controlId="key" key="key">
            <Col componentClass={ControlLabel} sm={3}>
              Secret Key
            </Col>
            <Col sm={8}>
              <FormControl type="text" name="key" value={item.get('key', '')} disabled={true} />
              { action === 'create' && (
                <HelpBlock>Secret will be available after saving</HelpBlock>
              )}
            </Col>
          </FormGroup>

          <FormGroup controlId="from" key="from">
            <Col componentClass={ControlLabel} sm={3}>
              Creation Date
            </Col>
            <Col sm={8}>
              <div className="pull-left" >
                <DatePicker
                  className="form-control"
                  dateFormat={getConfig('dateFormat', 'DD/MM/YYYY')}
                  selected={item.get('from', '').length ? moment(item.get('from', '')) : null}
                  onChange={this.onChangeDateFrom}
                  isClearable={true}
                  placeholderText="Select Date..."
                />
              </div>
            </Col>
          </FormGroup>

          <FormGroup controlId="to" key="to">
            <Col componentClass={ControlLabel} sm={3}>
              Expiration Date
            </Col>
            <Col sm={8}>
              <div className="pull-left" >
                <DatePicker
                  className="form-control"
                  dateFormat={getConfig('dateFormat', 'DD/MM/YYYY')}
                  selected={item.get('to', '').length ? moment(item.get('to', '')) : null}
                  onChange={this.onChangeDateTo}
                  isClearable={true}
                  placeholderText="Select Date..."
                />
              </div>
            </Col>
          </FormGroup>

        </Form>
      </ModalWrapper>
    );
  }
}


export default SecurityForm;
