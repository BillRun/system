import React, { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import isNumber from 'is-number';
import getSymbolFromCurrency from 'currency-symbol-map';
import moment from 'moment';
import uuid from 'uuid';
import { Col, Form, FormGroup, ControlLabel, Panel, Button, HelpBlock } from 'react-bootstrap';
import Field from '@/components/Field';
import { ActionButtons } from '@/components/Elements';
import ViewExpectedInvoice from '@/components/ImmediateInvoice/ViewExpectedInvoice';

import {
  getFieldName,
  getConfig,
} from '@/common/Util';
import {
  getAccountsQuery,
  searchProductsByKeyAndUsagetQuery,
  getAccountsInvoicesQuery,
} from '@/common/ApiQueries';

import {
  generateOneTimeInvoiceExpected,
  clearRefundInvoice,
  getRefundInvoiceCustomer,
  updateRefundInvoiceLines,
  updateRefundInvoiceCustomer,
  updateRefundInvoiceReason,
} from '@/actions/invoiceActions';
import {
  clearList,
  getList,
} from '@/actions/listActions';
import { showDanger } from '@/actions/alertsActions';
import {
  showConfirmModal,
  showFormModal,
} from '@/actions/guiStateActions/pageActions';

import {
  accountsOptionsSelector,
  accountsInvoicesOptionsSelector,
  creditProductsOptionsSelector,
} from '@/selectors/listSelectors';
import {
  currencySelector,
} from '@/selectors/settingsSelector';
import {
  itemSelector,
} from '@/selectors/entitySelector';


const RefundInvoiceSetup = ({
  accountsOptions, productsOptions, invoicesOptions,
  refundInvoice, currency,
  dispatch,
}) => {

  const [expectedInvoiceInProgress, setExpectedInvoiceInProgress] = useState(false);

  const apiFormat = getConfig('apiDateTimeFormat', '');
  const currencySymbol = getSymbolFromCurrency(currency);

  const aid = refundInvoice.getIn(['customer', 'aid'], '');
  const invoiceId = refundInvoice.get('id', '');
  const reason = refundInvoice.getIn(['note'], '');
  const pg_4_digit = refundInvoice.getIn(['customer', 'payment_gateway', 'active', 'four_digits'], '');
  const line = refundInvoice.getIn(['lines', 0], Immutable.Map({
      id: uuid.v4(),
      sid: 0,
      inv_id: '',
      rate: '',
      price: '',
      date: moment().format(apiFormat),
      volume: 1,
      type: 'credit',
  }));
  const inv_id = line.get('inv_id', '');
  const product = line.get('rate', '');
  const price = line.get('price', '');
  const isAccountSelected = aid !== '';
  const disableInvoiceSelect = !isAccountSelected;
  const disableAccountSelect = isAccountSelected;
  const disableProductSelect = !isAccountSelected;
  const disableRefundReason = !isAccountSelected;
  const disableRefundAmount = !isAccountSelected;
  const downloadInvoiceUrl = `${getConfig(['env','serverApiUrl'], '')}/api/accountinvoices?action=download&aid=${aid}&iid=${invoiceId}`;
  const isInvoiceConfirmed = invoiceId && invoiceId !== '';

  useEffect(() => {
    // component will mount in functional component.
    dispatch(getList('available_accounts', getAccountsQuery()));
    dispatch(getList('available_credit_products', searchProductsByKeyAndUsagetQuery('credit')));
    // component will unmount in functional component.
    return () => {
      dispatch(clearList('accounts_invoices'));
      dispatch(clearList('available_accounts'));
      dispatch(clearList('available_credit_products'));
      // TODO: find another way to not reset confirmed invoice - this is not working because 'isInvoiceConfirmed' is cashed and its always false
      if (!isInvoiceConfirmed) {
        // dispatch(clearRefundInvoice());
      }
    }
  }, []);

  useEffect(() => {
    // component will mount in functional component.
    if (aid !== '') {
      dispatch(getList('accounts_invoices', getAccountsInvoicesQuery(aid)));
    } else {
      dispatch(clearList('accounts_invoices'));
    }
  }, [aid]);

  const isRefundValid = () => {
    return aid !== '' && price !== '' && product !== '';
  }
  
  const onResetFormClick = () => {
    if (aid !== '' || price !== '' || product !== '') {
      const confirm = {
        message: getFieldName('confirm_reset_refund_inv', 'immediate_invoice'),
        onOk: () => dispatch(clearRefundInvoice()),
        type: 'delete',
        labelOk: getFieldName('reset_form', 'immediate_invoice'),
      };
      return dispatch(showConfirmModal(confirm));
    }
    dispatch(clearRefundInvoice())
  }

  const onChangeAccount = (aid, {option, action}) => {
    if (action === 'clear')
      dispatch(updateRefundInvoiceCustomer(Immutable.Map()));
    else
      dispatch(getRefundInvoiceCustomer(option.id));
  }

  const onChangeProduct = (key, {option, action}) => {
    if (action === 'clear') {
      return dispatch(updateRefundInvoiceLines(line.delete('rate').delete('rate_name')));
    } else {
      return dispatch(updateRefundInvoiceLines(line.set('rate', key).set('rate_name', option.label)));
    }
  }

  const onChangeInvoiceId = (id, {option, action}) => {
    if (action === 'clear') {
      dispatch(updateRefundInvoiceLines(line
        .delete('price')
        .delete('inv_id')
      ));
    } else {
      if (isNumber(option.amount_without_tax)) {
        dispatch(updateRefundInvoiceLines(line
          .set('inv_id', id)
          .set('price', -1 * parseFloat(option.amount_without_tax))
        ));
      } else {
        dispatch(updateRefundInvoiceLines(line.set('inv_id', id)));
      }
    }
  }

  const onChangeRefundReason = (e) => {
      const value = e.target.value;
      dispatch(updateRefundInvoiceReason(value));
  };

  const onChangeRefundAmount = (e) => {
    const { value } = e.target;
    const refundAmount = isNumber(value) ? -1 * parseFloat(value) : value;
    return dispatch(updateRefundInvoiceLines(line.set('price', refundAmount)));
  }

  const onViewExpectedInvoice = () => {
    if (!isRefundValid()) {
      return false;
    }
    const config = {
      title: getFieldName('popup_title', 'immediate_invoice'),
      labelCancel: getFieldName('close'),
      showOnOk: false,
      skipConfirmOnClose:true
    };
    setExpectedInvoiceInProgress(true);

    dispatch(generateOneTimeInvoiceExpected(aid, Immutable.List([line]) , reason))
    .then(success => {
      if (success.status !== 1) {
        throw new Error();
      }
      if (!isNumber(success?.data?.invoiceData?.totals?.after_vat_rounded)) {
        throw new Error();
      }
      const data = Immutable.Map({
        lines: Immutable.List([line]),
        aid,
        currency,
        price: success?.data?.invoiceData?.totals?.after_vat_rounded || '',
        pg_4_digit,
        note: reason,
      });
      dispatch(showFormModal(data, ViewExpectedInvoice, config));
    }).catch(error => {
      dispatch(showDanger(getFieldName('error_retrieving_invoice', 'immediate_invoice')));
    }).finally(() => {
      setExpectedInvoiceInProgress(false);
    })
  }

  return (
    <div className="refund-invoice-setup">

        <Col sm={12}>
          <FormGroup className="form-inner-edit-row">
            <Col componentClass={ControlLabel} sm={4} lg={3} className="mt10 text-right">
              {getFieldName('select_customer', 'immediate_invoice')}:
            </Col>
            <Col sm={6} lg={7}>
              <Field
                fieldType="select"
                value={aid}
                options={accountsOptions}
                onChange={onChangeAccount}
                placeholder={getFieldName('select_customer_help', 'immediate_invoice')}
                clearable={false}
                disabled={disableAccountSelect}
              />
            </Col>
            <Col sm={2} lg={2} className="text-right">
              <Button disabled={expectedInvoiceInProgress} type="submit" onClick={onResetFormClick} bsStyle="danger" className="ml10">
                <i className="danger-red fa fa-fw fa-trash-o" /> {getFieldName('reset_form', 'immediate_invoice')}
              </Button>
            </Col>
          </FormGroup>
        </Col>

      <Col sm={12} className="mt10">
        <Panel header={getFieldName('refund_details', 'immediate_invoice')}>
          <Form horizontal>

            <Col sm={12}>
              <FormGroup className="form-inner-edit-row">
                <Col componentClass={ControlLabel} sm={4} lg={3} className="mt10 text-right">
                  {getFieldName('product', 'immediate_invoice')}<span className="danger-red"> *</span>
                </Col>
                <Col sm={6} lg={7}>
                  <Field
                    fieldType="select"
                    value={product}
                    options={productsOptions.toJS()}
                    onChange={onChangeProduct}
                    placeholder={getFieldName('select_product_help', 'immediate_invoice')}
                    disabled={disableProductSelect}
                  />
                </Col>
              </FormGroup>
            </Col>

            <Col sm={12}>
              <FormGroup className="form-inner-edit-row">
                <Col componentClass={ControlLabel} sm={4} lg={3} className="mt10 text-right">
                  {getFieldName('select_invoice_id', 'immediate_invoice')} ({getFieldName('optional')})
                </Col>
                <Col sm={6} lg={7}>
                  <Field
                    fieldType="select"
                    value={inv_id}
                    options={invoicesOptions}
                    onChange={onChangeInvoiceId}
                    placeholder={getFieldName('select_invoice_help', 'immediate_invoice')}
                    disabled={disableInvoiceSelect}
                  />
                </Col>
                <Col smOffset={4} lgOffset={3} sm={6} lg={7}>
                  <HelpBlock>{getFieldName('select_invoice_id_help', 'immediate_invoice')}</HelpBlock>
                </Col>
              </FormGroup>
            </Col>

            <Col sm={12}>
              <FormGroup className="form-inner-edit-row">
                <Col componentClass={ControlLabel} sm={4} lg={3} className="mt10 text-right">
                  {getFieldName('refund_amount', 'immediate_invoice')}<span className="danger-red"> *</span>
                </Col>
                <Col sm={6} lg={7}>
                  <Field
                    fieldType="number"
                    onChange={onChangeRefundAmount}
                    value={isNumber(price) ? Math.abs(price) : price}
                    preffix={<small>{getFieldName('no_tax', 'immediate_invoice')}</small>}
                    suffix={currencySymbol}
                    disabled={disableRefundAmount}
                  />
                </Col>
              </FormGroup>
            </Col>

            <Col sm={12}><hr className="mb15 mt5"/></Col>

            <Col sm={12}>
              <FormGroup className="form-inner-edit-row">
                <Col componentClass={ControlLabel} sm={4} lg={3} className="mt10 text-right">
                  {getFieldName('refund_reason', 'immediate_invoice')} ({getFieldName('optional')})
                </Col>
                <Col sm={6} lg={7}>
                  <Field
                    fieldType="textarea"
                    value={reason}
                    onChange={onChangeRefundReason}
                    disabled={disableRefundReason}
                  />
                </Col>
              </FormGroup>
            </Col>
          </Form>
        </Panel>
        {!isInvoiceConfirmed && (
          <ActionButtons
            saveLabel={getFieldName('view_expected_invoice_btn', 'immediate_invoice')}
            onClickSave={onViewExpectedInvoice}
            disableSave={!isRefundValid()}
            hideCancel={true}
            progress={expectedInvoiceInProgress}
          />
        )}
        {isInvoiceConfirmed && (
          <form method="post" action={downloadInvoiceUrl} target="_blank" className="inline">
            <Button type="submit" bsStyle="primary">
              <i className="fa fa-download" /> {getFieldName('btn_download_invoice', 'immediate_invoice')}
            </Button>
          </form>
        )}
      </Col>
    </div>
  );
}

RefundInvoiceSetup.defaultProps = {
  currency: '',
  accountsOptions: [],
  invoicesOptions: [],
  productsOptions: Immutable.List(),
  refundInvoice: Immutable.Map(),
};

RefundInvoiceSetup.propTypes = {
  dispatch: PropTypes.func.isRequired,
  accountsOptions: PropTypes.array,
  invoicesOptions: PropTypes.array,
  productsOptions: PropTypes.instanceOf(Immutable.List),
  currency: PropTypes.string,
};

const mapStateToProps = (state, props) => ({
  accountsOptions: accountsOptionsSelector(state, props),
  invoicesOptions: accountsInvoicesOptionsSelector(state, props),
  productsOptions: creditProductsOptionsSelector(state, props),
  currency: currencySelector(state, props),
  refundInvoice: itemSelector(state, props, 'refund-invoice'),
});

export default connect(mapStateToProps)(RefundInvoiceSetup);
