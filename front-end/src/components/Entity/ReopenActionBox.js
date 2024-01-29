import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, HelpBlock, ControlLabel } from 'react-bootstrap';
import { ModalWrapper } from '@/components/Elements';
import { getConfig, getItemDateValue, toImmutableList } from '@/common/Util';
import Field from '@/components/Field';

class ReopenActionBox extends Component {

  static propTypes = {
    item: PropTypes.instanceOf(Immutable.Map),
    revisions: PropTypes.instanceOf(Immutable.List),
    itemName: PropTypes.string.isRequired,
    onReopenItem: PropTypes.func,
    onCancelReopenItem: PropTypes.func,
  }

  static defaultProps = {
    item: Immutable.Map(),
    revisions: Immutable.List(),
    onReopenItem: () => {},
    onCancelReopenItem: () => {},
  }

  state = {
    fromDate: null,
    validationErrors: Immutable.Map({
      from: '',
    }),
  }

  onClickOk = () => {
    const { fromDate, validationErrors } = this.state;
    const { item } = this.props;
    if (fromDate === null) {
      this.setState({ fromDate, validationErrors: validationErrors.set('from', 'required') });
    } else {
      this.props.onReopenItem(item, fromDate.toISOString());
    }
  }

  onClickCancel = () => {
    this.props.onCancelReopenItem();
  }

  onChangeFromDate = (fromDate) => {
    const { validationErrors } = this.state;
    const fromDateError = fromDate === null ? 'required' : '';
    this.setState({ fromDate, validationErrors: validationErrors.set('from', fromDateError) });
  }

  render() {
    const { item, revisions, itemName } = this.props;
    const { fromDate, validationErrors } = this.state;

    const lastRevision = revisions.first();
    const minFrom = getItemDateValue(lastRevision, 'to');
    const revisionBy = toImmutableList(getConfig(['systemItems', itemName, 'uniqueField'], Immutable.List())).get(0, '');
    const title = `${item.get(revisionBy, '')} - Reopen`;
    const formStyle = { padding: '35px 50px 0 50px' };
    const fromError = validationErrors.get('from', '');

    return (
      <ModalWrapper show={true} title={title} labelCancel="Close" onCancel={this.onClickCancel} onHide={this.onClickCancel} labelOk="Reopen" onOk={this.onClickOk}>
        <div className="text-center reopen-modal">
          <Form horizontal style={formStyle}>
            <FormGroup validationState={fromError.length > 0 ? 'error' : null}>
              <Col componentClass={ControlLabel} sm={4}>
                Reopen From Date
              </Col>
              <Col sm={6} className="pr0">
                <Field
                  fieldType="date"
                  value={fromDate}
                  minDate={minFrom}
                  onChange={this.onChangeFromDate}
                  isClearable={true}
                  placeholder="Select Reopen From Date..."
                />
                <HelpBlock>
                  { fromError.length > 0 ? fromError : null }
                </HelpBlock>
              </Col>
            </FormGroup>
          </Form>
        </div>
      </ModalWrapper>
    );
  }
}
export default ReopenActionBox;
