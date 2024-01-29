import React, { useState } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import classNames from 'classnames';
import { Button, Col, Row } from 'react-bootstrap';
import Field from '@/components/Field';
import { generateOneTimeInvoice } from '@/actions/invoiceActions';
import {
  getConfig,
} from '@/common/Util';


const ViewExpectedInvoice = ({ item, dispatch }) => {

  const [invoiceId, setInvoiceId] = useState(null);
  const [inConfirmProgress, setInConfirmProgress] = useState(false);
  const [sendMail, setSendMail] = useState(false);

  const aid = item.get('aid', '');
  const onConfirm = item.get('onConfirm', null);
  const invoiceData = item.get('invoiceData', []);
  const lines = item.get('lines', Immutable.List());
  const price = invoiceData[0].price;
  const downloadExpectedInvoiceUrl = `${getConfig(['env','serverApiUrl'], '')}/api/accountinvoices?action=download&aid=${aid}`;
  const downloadInvoiceUrl = `${getConfig(['env','serverApiUrl'], '')}/api/accountinvoices?action=download&aid=${aid}&iid=${invoiceId}`;

  const onChangeSendEmail = (e) => {
    const { value } = e.target;
    setSendMail(value);
  }

  const onConfirmInvoice = () => {
    setInConfirmProgress(true);
    dispatch(generateOneTimeInvoice(aid, lines ,sendMail))
    .then((success) => {
        // TODO: get the Invoice ID
        const invoiceId = success.details[0].invoiceId;
        setInvoiceId(invoiceId)
        if (onConfirm) {
          onConfirm(invoiceId);
        }
    }).finally(() => {
      setInConfirmProgress(false);
    })
  }

  const iconClass = classNames('fa', {
    'fa-spinner': inConfirmProgress,
    'fa-pulse': inConfirmProgress,
    'fa-check': !inConfirmProgress,
  });

  return (
    <>
      <Row>  
        <Col sm={12}>
          <p>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit,
            sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
            Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
            Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
            Excepteur sint occaecat cupidatat non proident,
            sunt in culpa qui officia deserunt mollit anim id est laborum
          </p>
          <p className="text-center">Invoice total charge: {price}</p>
        </Col>
      </Row>
      {invoiceId === null && (
        <Row className="mt10">
          <Col xs={12} className="text-center">
            <Field
              fieldType="checkbox"
              onChange={onChangeSendEmail}
              value={sendMail}
              className="inline mr10"
              label="Send Email"
            />
          </Col>
          <Col xs={6}>
          <form method="post" action={downloadExpectedInvoiceUrl} target="_blank">
            <Button bsStyle='primary' type="submit">
              <i className="fa fa-download" /> Download Expected Invoice
            </Button>
          </form>
          </Col>
          <Col xs={6} className="text-right">
            <Button
              onClick={onConfirmInvoice}
              bsStyle='success'
            >
              <i className={iconClass} /> Confirm Expected Invoice
            </Button>
          </Col>
        </Row>
      )}
      {invoiceId !== null && (
        <Row className="mt10">
          <Col sm={12} className="text-center">
          <form method="post" action={downloadInvoiceUrl} target="_blank">
            <Button type="submit">
              { inConfirmProgress && (
                <span><i className="fa fa-spinner fa-pulse" />&nbsp;&nbsp;</span>
              )}
              <i className="fa fa-download" /> Download Invoice
            </Button>
          </form>
          </Col>
        </Row>
      )}
    </>
  );
}

ViewExpectedInvoice.defaultProps = {
  item: Immutable.Map(),
};

ViewExpectedInvoice.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
  dispatch: PropTypes.func.isRequired,
};

export default connect()(ViewExpectedInvoice);

