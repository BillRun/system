import React, { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import isNumber from 'is-number';
import moment from 'moment';
import uuid from 'uuid';
import { Col, FormGroup, ControlLabel, Panel, Button } from 'react-bootstrap';
import { CreateButton, Actions, ActionButtons } from '@/components/Elements';
import Help from '@/components/Help';
import Field from '@/components/Field';
import InvoiceLine from './InvoiceLine';
import ViewExpectedInvoice from './ViewExpectedInvoice';
import {
  getAccountsQuery,
} from '@/common/ApiQueries';
import {
  delay,
} from '@/common/Api';
import { getList } from '@/actions/listActions';
import { showDanger } from '@/actions/alertsActions';
import {
  showConfirmModal,
  showFormModal,
} from '@/actions/guiStateActions/pageActions';
import {
  getFieldName,
  getConfig,
} from '@/common/Util';
import { accountsOptionsSelector } from '@/selectors/listSelectors';
import {
  currencySelector,
} from '@/selectors/settingsSelector';


const ImmediateInvoiceSetup = ({ accountsOptions, currency, dispatch }) => {

  const [aid, setAid] = useState(null);
  const [invoiceId, setInvoiceId] = useState(null);
  const [lines, setLine] = useState(Immutable.List());
  const [expectedInvoiceInProgress, setExpectedInvoiceInProgress] = useState(false);

  useEffect(() => {
    dispatch(getList('available_accounts', getAccountsQuery()));
  }, [dispatch]);

  const apiFormat = getConfig('apiDateTimeFormat', '');

  const isInvoiceConfirmed = invoiceId !== null;
  const isEditable = !isInvoiceConfirmed;

  const onChangeAccount = (aid, { option }) => {
    setAid(aid);
  }

  const onChangeLine = (path, value) => {
    setLine(lines.setIn(path, value));
  }

  const onRemoveLine = (idx) => {
    setLine(lines.delete(idx));
  }

  const onAddLine = () => {
    const newLine = Immutable.Map({
      id: uuid.v4(),
      sid: '',
      rate: '',
      price: '',
      date: moment().format(apiFormat),
      volume: 1,
      type: 'credit',
    });
    setLine(lines.push(newLine));
  }

  const onRemoveOk = () => {
    setLine(Immutable.List());
    setAid(null);
    setInvoiceId(null);
  }

  const onExpectedInvoiceConfirm = (invoiceId) => {
    setInvoiceId(invoiceId);
  }

  const onViewExpectedInvoice = () => {
    const config = {
      title: 'Expected Invoice',
      labelCancel: 'Close',
      showOnOk: false,
    };
    setExpectedInvoiceInProgress(true);
    delay(1, true, {status: 'OK', details: [{price: 50}] })
    .then(
      success => {
      if (success.status !== 'OK') {
        throw new Error("Error retrieving invoice data");
      }
      const data = Immutable.Map({
        lines,
        aid,
        invoiceData: success.details,
        onConfirm: onExpectedInvoiceConfirm,
      });
      dispatch(showFormModal(data, ViewExpectedInvoice, config));
    }).catch(error => {
      dispatch(showDanger("Error, can not generate expected invoice"));
    }).finally(() => {
      setExpectedInvoiceInProgress(false);
    })
  }
  
  const onRemoveClick = () => {
    const confirm = {
      message: "Are you sure you want to reset invoice lines ?",
      onOk: onRemoveOk,
      type: 'delete',
      labelOk: 'Delete',
    };
    dispatch(showConfirmModal(confirm));
  }

  const downloadInvoiceUrl = `${getConfig(['env','serverApiUrl'], '')}/api/accountinvoices?action=download&aid=${aid}&iid=${invoiceId}`;

  const actions = [
    { type: 'remove', showIcon: true, enable: !lines.isEmpty(), onClick: onRemoveClick },
  ];

  const header = (
    <div>
      Invoice Lines
      <div className="pull-right" style={{ marginTop: -5 }}>
        <Actions actions={actions} />
      </div>
    </div>
  );

  const linesRows = lines.map((line, idx) => (
    <InvoiceLine
      key={line.get('id', uuid.v4())}
      index={idx}
      line={line}
      aid={aid}
      editable={isEditable}
      currency={currency}
      onChange={onChangeLine}
      onRemove={onRemoveLine}
    />
  ));

  return (
    <div className="immediate-invoice-setup">
      <Col sm={12}>
        <FormGroup className="form-inner-edit-row">
          <Col componentClass={ControlLabel} sm={3} lg={2} className="mt10">
            {getFieldName('select_customer', 'immediate_invoice')}:
          </Col>
          <Col sm={8} lg={9}>
            <Field
              fieldType="select"
              value={aid}
              options={accountsOptions}
              onChange={onChangeAccount}
              placeholder={getFieldName('select_customer_help', 'immediate_invoice')}
              disabled={!lines.isEmpty()}
            />
          </Col>
        </FormGroup>
      </Col>
      <Col sm={12} className="mt10">
        <Panel header={header}>
          { !lines.isEmpty() && (
            <Col sm={12} className="form-inner-edit-rows">
              <FormGroup className="form-inner-edit-row header">
                <Col sm={3} xsHidden>
                  <label htmlFor="subscriber">{getFieldName('subscriber', 'immediate_invoice')}</label><span className="danger-red"> *</span>
                  <Help contents="Type subscriber id, customer id, first name or last name to search" />
                </Col>
                <Col sm={3} xsHidden>
                  <label htmlFor="product">{getFieldName('product', 'immediate_invoice')}</label><span className="danger-red"> *</span>
                  <Help contents="Type a product key or title to search" />
                </Col>
                <Col sm={2} xsHidden>
                  <label htmlFor="price">{getFieldName('price', 'immediate_invoice')}</label><span className="danger-red"> *</span>
                </Col>
                <Col sm={2} xsHidden>
                  <label htmlFor="date">{getFieldName('date', 'immediate_invoice')}</label>
                </Col>
                <Col sm={2} xsHidden>
                  <label htmlFor="volume">{getFieldName('volume', 'immediate_invoice')}</label>
                </Col>
              </FormGroup>
              { linesRows }
            </Col>
          )}
          { lines.isEmpty() && (
            <Col sm={12} className="form-inner-edit-rows">
              <small>No lines</small>
            </Col>
          )}
          <Col sm={12} className="pl0 pr0">
            { isEditable && (
              <CreateButton
                onClick={onAddLine}
                label="Add Line"
                disabled={!isNumber(aid)}
              />
            )}
          </Col>
        </Panel>
        {!isInvoiceConfirmed && (
          <ActionButtons
            saveLabel="View Expected Invoice"
            onClickSave={onViewExpectedInvoice}
            disableSave={lines.isEmpty()}
            hideCancel={true}
            progress={expectedInvoiceInProgress}
          />
        )}
        {isInvoiceConfirmed && (
          <form method="post" action={downloadInvoiceUrl} target="_blank">
            <Button type="submit">
              <i className="fa fa-download" /> Download Invoice
            </Button>
          </form>
        )}
      </Col>
    </div>
  );
}

ImmediateInvoiceSetup.defaultProps = {
  currency: '',
  accountsOptions: [],
};

ImmediateInvoiceSetup.propTypes = {
  dispatch: PropTypes.func.isRequired,
  accountsOptions: PropTypes.array,
  currency: PropTypes.string,
};

const mapStateToProps = (state, props) => ({
  accountsOptions: accountsOptionsSelector(state, props),
  currency: currencySelector(state, props),

});

export default connect(mapStateToProps)(ImmediateInvoiceSetup);
