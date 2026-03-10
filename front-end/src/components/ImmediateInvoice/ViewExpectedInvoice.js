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
  const note = item.get('note', '');
  const invoiceUnixtime = item.get('invoice_unixtime', '');
  
  const downloadExpectedInvoiceUrl = buildRequestUrl(generateOneTimeInvoiceDownloadExpectedQuery(aid, lines, sendMail, note, invoiceUnixtime));
  const downloadInvoiceUrl = `${getConfig(['env','serverApiUrl'], '')}/api/accountinvoices?action=download&aid=${aid}&iid=${invoiceId}`;
  const advancedOptionsStyle = {textAlign: 'left', maxWidth: 250, margin: '0 auto'};
  
  const hasPaymentGateway = pg_4_digit !== '' && isNumber(pg_4_digit);
  const isInvoiceConfirmed = invoiceId !== '';
  const isRefund = isNumber(price) && price < 0;
  const displayAmount = isRefund ? Math.abs(price) : price;
  const invTypeLabel = isRefund ? getFieldName('type_refund', 'immediate_invoice') : getFieldName('type_charge', 'immediate_invoice');

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
    dispatch(generateOneTimeInvoice(aid, lines ,invoiceType, false, note, invoiceUnixtime))
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
            <p className="inline">Invoice total {isRefund === true ? 'refund' : 'charge'}: </p>
            <span>
              {isNumber(price) && (
                <input
                  type="text"
                  value={`${displayAmount}${getSymbolFromCurrency(currency)}`}
                  disabled={true}
                  size="5"
                  className='text-center ml5'
                />
              )}
              {!isNumber(price) && (
                <label className="text-danger"><strong> -</strong></label>
              )}
            </span>
            <form method="post" action={downloadExpectedInvoiceUrl} target="_blank" className='mt10 mb10 ml15'>
                <Button bsStyle='primary' type="submit" disabled={isInvoiceConfirmed || inConfirmProgress}>
                  <i className="fa fa-download" /> {getFieldName('btn_download_expected_invoice', 'immediate_invoice')}
                </Button>
              </form>
            <hr />
            <p>Advanced options:</p>
            <div style={advancedOptionsStyle}>
              <Field
                fieldType="radio"
                onChange={onChangeInvoiceOption}
                name="invoice_type"
                value="without_charge"
                label={getFieldName('select_invoice_without_charge', 'immediate_invoice', null, {type: invTypeLabel})}
                checked={invoiceType === "without_charge"}
                className="inline"
                labelStyle={{}}
                disabled={isInvoiceConfirmed || inConfirmProgress}
              />
              <Field
                fieldType="radio"
                onChange={onChangeInvoiceOption}
                name="invoice_type"
                value="charge"
                label={getFieldName('select_invoice_charge', 'immediate_invoice', null, {type: invTypeLabel})}
                checked={invoiceType === "charge"}
                className="inline"
                labelStyle={{}}
                disabled={!hasPaymentGateway || isInvoiceConfirmed || inConfirmProgress}
              />
              <Field
                fieldType="radio"
                onChange={onChangeInvoiceOption}
                name="invoice_type"
                value="successful_charge"
                label={getFieldName('select_invoice_successful_charge', 'immediate_invoice', null, {type: invTypeLabel})}
                checked={invoiceType === "successful_charge"}
                className="inline"
                labelStyle={{}}
                disabled={!hasPaymentGateway || isInvoiceConfirmed || inConfirmProgress}
              />
            </div>
            { !hasPaymentGateway && (
              <Label bsStyle="warning">{ getFieldName('no_pg_more_options_text', 'immediate_invoice')}</Label>
            )}
            <div style={advancedOptionsStyle} className='mt15'>
              <Field
                fieldType="checkbox"
                onChange={onChangeSendEmail}
                value={sendMail}
                className="inline"
                label={getFieldName('send_invoice_email', 'immediate_invoice')}
                disabled={isInvoiceConfirmed || inConfirmProgress}
              />
            </div>
            <hr className="mb5" />
            <Button onClick={onConfirmInvoice} bsStyle='success' className='mt10 mb10 ml15' disabled={isInvoiceConfirmed || inConfirmProgress}>
              <i className={iconClass} /> {getFieldName('btn_confirm_expected_invoice', 'immediate_invoice', null, {type: invTypeLabel})}
            </Button>
        </Col>
      </Row>
      {isInvoiceConfirmed && (
        <Row className="text-center expectedInvoicePopupContainer mt10">
          <Col sm={12} className="text-center">
          <hr className="mt0"/>
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

