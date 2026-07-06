import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import DatePicker from 'react-datepicker';
import moment from 'moment';
import { Form, FormControl, Col } from 'react-bootstrap';
import { ControlLabel, FormGroup, HelpBlock } from '@/common/BootstrapCompat';
import { ModalWrapper } from '@/components/Elements';
import { getConfig } from '@/common/Util';

const toDateFnsFormat = format => format.replace(/YYYY/g, 'yyyy').replace(/DD/g, 'dd');


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

  onChangeDateFrom = (date) => {
    const { item } = this.state;
    const apiFormat = getConfig('apiDateFormat', 'YYYY-MM-DD');
    const fromValue = date ? moment(date).format(apiFormat) : '';
    this.setState({ item: item.set('from', fromValue) });
  }

  onChangeDateTo = (date) => {
    const { item } = this.state;
    const apiFormat = getConfig('apiDateFormat', 'YYYY-MM-DD');
    const fromValue = date ? moment(date).format(apiFormat) : '';
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
        <Form className="form-horizontal">
          <FormGroup controlId="name" key="name">
            <Col as={ControlLabel} sm={3}>
              Name
            </Col>
            <Col sm={8}>
              <FormControl type="text" name="name" onChange={this.onChangeName} value={item.get('name', '')} />
            </Col>
          </FormGroup>

          <FormGroup controlId="key" key="key">
            <Col as={ControlLabel} sm={3}>
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
            <Col as={ControlLabel} sm={3}>
              Creation Date
            </Col>
            <Col sm={8}>
              <div className="pull-left" >
                <DatePicker
                  className="form-control"
                  dateFormat={toDateFnsFormat(getConfig('dateFormat', 'DD/MM/YYYY'))}
                  selected={item.get('from', '').length ? moment(item.get('from', '')).toDate() : null}
                  onChange={this.onChangeDateFrom}
                  isClearable={true}
                  placeholderText="Select Date..."
                />
              </div>
            </Col>
          </FormGroup>

          <FormGroup controlId="to" key="to">
            <Col as={ControlLabel} sm={3}>
              Expiration Date
            </Col>
            <Col sm={8}>
              <div className="pull-left" >
                <DatePicker
                  className="form-control"
                  dateFormat={toDateFnsFormat(getConfig('dateFormat', 'DD/MM/YYYY'))}
                  selected={item.get('to', '').length ? moment(item.get('to', '')).toDate() : null}
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
