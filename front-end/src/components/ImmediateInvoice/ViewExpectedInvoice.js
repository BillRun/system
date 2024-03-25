import React, { useState } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import classNames from 'classnames';
import isNumber from 'is-number';
import { Button, Col, Row, Label } from 'react-bootstrap';
import getSymbolFromCurrency from 'currency-symbol-map';
import { buildRequestUrl } from '@/common/Api';
import {
  generateOneTimeInvoiceDownloadExpectedQuery,
} from '@/common/ApiQueries';
import Field from '@/components/Field';
import {
  generateOneTimeInvoice,
  updateImmediateInvoiceId,
} from '@/actions/invoiceActions';
import { showDanger } from '@/actions/alertsActions';
import {
  getConfig,
  getFieldName,
} from '@/common/Util';


const ViewExpectedInvoice = ({ item, dispatch }) => {

  
  const [invoiceId, setInvoiceId] = useState('');
  const [inConfirmProgress, setInConfirmProgress] = useState(false);
  const [sendMail, setSendMail] = useState(false);
  const [invoiceType, setInvoiceType] = useState('without_charge');

  const aid = item.get('aid', '');
  const currency = item.get('currency', '');
  const price = item.get('price', []);
  const lines = item.get('lines', Immutable.List());
  const pg_4_digit = item.get('pg_4_digit', '');

  const downloadExpectedInvoiceUrl = buildRequestUrl(generateOneTimeInvoiceDownloadExpectedQuery(aid, lines ,invoiceType, sendMail));
  const downloadInvoiceUrl = `${getConfig(['env','serverApiUrl'], '')}/api/accountinvoices?action=download&aid=${aid}&iid=${invoiceId}`;
  
  const hasPaymentGateway = pg_4_digit !== '' && isNumber(pg_4_digit);
  const isInvoiceConfirmed = invoiceId !== '';


  const onChangeSendEmail = (e) => {
    const { value } = e.target;
    setSendMail(value);
  }

  const onChangeInvoiceOption = (e) => {
    const { value } = e.target;
    setInvoiceType(value);
  }

  const onConfirmInvoice = () => {
    setInConfirmProgress(true);
    dispatch(generateOneTimeInvoice(aid, lines ,invoiceType, sendMail))
    .then((success) => {
        const invoice_id = success?.data?.invoice_id;
        if (isNumber(invoice_id)) {
          setInvoiceId(invoice_id);
          return dispatch(updateImmediateInvoiceId(invoice_id));
        }
        throw new Error();
    }).catch(error => {
        dispatch(showDanger(getFieldName('error_generate_invoice', 'immediate_invoice')));
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
      <Row className="text-center expectedInvoicePopupContainer">  
        <Col sm={12} className="text-center">
            <p>Invoice is in <span className='text-danger'><strong>draft mode</strong></span>.</p>
            <hr />
            <p className="inline">Invoice total charge: </p>
            <span>
              {isNumber(price) && (
                <input
                  type="text"
                  value={`${price}${getSymbolFromCurrency(currency)}`}
                  disabled={true}
                  size="5"
                  className='text-center ml5'
                />
              )}
              {!isNumber(price) && (
                <label className="text-danger"><strong> -</strong></label>
              )}
            </span>
            <hr />
            <p>More advanced options:</p>
            <div>
              <Field
                fieldType="radio"
                onChange={onChangeInvoiceOption}
                name="invoice_type"
                value="without_charge"
                label={getFieldName('select_invoice_without_charge', 'immediate_invoice')}
                checked={invoiceType === "without_charge"}
                className="mr15 inline"
                disabled={isInvoiceConfirmed}
              />
              <Field
                fieldType="radio"
                onChange={onChangeInvoiceOption}
                name="invoice_type"
                value="charge"
                label={getFieldName('select_invoice_charge', 'immediate_invoice')}
                checked={invoiceType === "charge"}
                className="mr15 inline"
                disabled={!hasPaymentGateway || isInvoiceConfirmed}
              />
              <Field
                fieldType="radio"
                onChange={onChangeInvoiceOption}
                name="invoice_type"
                value="successful_charge"
                label={getFieldName('select_invoice_successful_charge', 'immediate_invoice')}
                checked={invoiceType === "successful_charge"}
                className="inline"
                disabled={!hasPaymentGateway || isInvoiceConfirmed}
              />
            </div>
            { !hasPaymentGateway && (
              <Label bsStyle="warning">{ getFieldName('no_pg_more_options_text', 'immediate_invoice')}</Label>
            )}
            <Field
              fieldType="checkbox"
              onChange={onChangeSendEmail}
              value={sendMail}
              className="inline ml10 mt5"
              label={getFieldName('send_invoice_email', 'immediate_invoice')}
              disabled={isInvoiceConfirmed}
            />
            <hr />
            <p>You can download and review it.</p>
            <form method="post" action={downloadExpectedInvoiceUrl} target="_blank" className='mt10 mb10 ml15'>
              <Button bsStyle='primary' type="submit" disabled={isInvoiceConfirmed}>
                <i className="fa fa-download" /> {getFieldName('btn_download_expected_invoice', 'immediate_invoice')}
              </Button>
            </form>
            <hr />
            <p>Do not send the expected invoice to customer.</p>
            <hr />
            <p>Once invoice is confirmed, please click confirm button.</p>
              <Button onClick={onConfirmInvoice} bsStyle='success' className='mt10 mb10 ml15' disabled={isInvoiceConfirmed}>
                <i className={iconClass} /> {getFieldName('btn_confirm_expected_invoice', 'immediate_invoice')}
              </Button>
            <hr />
            <p>The invoice will be added to the account receivable.</p>
        </Col>
      </Row>
      {isInvoiceConfirmed && (
        <Row className="mt10">
          <hr />
          <Col sm={12} className="text-center">
          <form method="post" action={downloadInvoiceUrl} target="_blank">
            <Button type="submit">
              <i className="fa fa-download" /> {getFieldName('btn_download_invoice', 'immediate_invoice')}
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
  currency: '',
};

ViewExpectedInvoice.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
  currency: PropTypes.string,
  dispatch: PropTypes.func.isRequired,
};

export default connect()(ViewExpectedInvoice);

