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
import { getList } from '@/actions/listActions';
import { showDanger } from '@/actions/alertsActions';
import {
  showConfirmModal,
  showFormModal,
} from '@/actions/guiStateActions/pageActions';
import {
  generateOneTimeInvoiceExpected,
  clearImmediateInvoice,
  getImmediateInvoiceCustomer,
  updateImmediateInvoiceLines,
  updateImmediateInvoiceCustomer,
} from '@/actions/invoiceActions';
import {
  getFieldName,
  getConfig,
} from '@/common/Util';
import { accountsOptionsSelector } from '@/selectors/listSelectors';
import {
  currencySelector,
} from '@/selectors/settingsSelector';
import {
  itemSelector,
} from '@/selectors/entitySelector';


const ImmediateInvoiceSetup = ({ accountsOptions, currency, immediateInvoice, dispatch }) => {

  const apiFormat = getConfig('apiDateTimeFormat', '');
  const aid = immediateInvoice.getIn(['customer', 'aid'], '');
  const pg_4_digit = immediateInvoice.getIn(['customer', 'payment_gateway', 'active', 'four_digits'], '');
  const lines = immediateInvoice.getIn(['lines'], Immutable.List());
  const invoiceId = immediateInvoice.getIn(['id'], '');
  const isInvoiceConfirmed = invoiceId && invoiceId !== '';
  const isEditable = !isInvoiceConfirmed;
  
  const [expectedInvoiceInProgress, setExpectedInvoiceInProgress] = useState(false);

  useEffect(() => {
    // component will mount in functional component.
    dispatch(getList('available_accounts', getAccountsQuery()));
    // component will unmount in functional component.
    return () => {
      // TODO: find another way to not reset confirmed invoice - this is not working because 'isInvoiceConfirmed' is cashed and its always false
      if (!isInvoiceConfirmed) {
        // dispatch(clearImmediateInvoice());
      }
    }
  }, []);

  const isLinesValid = () => {
    const res = lines.reduce((acc, line, idx) => {
      const lineValidation = isLineValid(line);
      if (lineValidation !== true) {
        return acc.set(idx, Immutable.Map({[lineValidation.field]: lineValidation.message}) );
      }
      return acc;
    } , Immutable.Map());

    if (res.isEmpty()) {
      return true;
    }
    const linesWithError = lines.map((line, idx) => {
      if (res.get(idx, false) !== false) {
        return line.set('errors', res.get(idx, Immutable.Map()))
      }
      return line;
    });
    dispatch(updateImmediateInvoiceLines(linesWithError));
    return false;
  };

  const isLineValid = (line) => {
    if (typeof line == 'undefined') {
      return true;
    }
    if (line.get('sid', '') === '') {
      return { field: 'subscriber', message: 'required'} 
    }
    if (line.get('rate', '') === '') {
      return { field: 'rate', message: 'required'} 
    }
    if (line.get('rate', '') === '') {
      return { field: 'rate', message: 'required'} 
    }
    if (line.get('date', '') === '') {
      return { field: 'date', message: 'required'} 
    }
    if (line.get('volume', '') === '') {
      return { field: 'volume', message: 'required'} 
    }
    return true;
  }

  const onChangeAccount = (aid, {option,action}) => {
    if (action === 'clear')
      dispatch(updateImmediateInvoiceCustomer(Immutable.Map()));
    else
      dispatch(getImmediateInvoiceCustomer(option.id));
  }

  const onChangeLine = (path, value) => {
    const newLines = lines.setIn(path, value);
    return dispatch(updateImmediateInvoiceLines(newLines));
  }

  const onRemoveLine = (idx) => {
    return dispatch(updateImmediateInvoiceLines(lines.delete(idx)));
  }

  const onAddLine = () => {
    if (!isLinesValid()) {
      return;
    }
    const newLine = Immutable.Map({
      id: uuid.v4(),
      sid: '',
      rate: '',
      price: '',
      date: moment().format(apiFormat),
      volume: 1,
      type: 'credit',
    });
    dispatch(updateImmediateInvoiceLines(lines.push(newLine)));
  }

  const onRemoveOk = () => {
    dispatch(updateImmediateInvoiceLines(Immutable.List()));
  }

  const onViewExpectedInvoice = () => {
    if (!isLinesValid()) {
      return false;
    }
    const config = {
      title: getFieldName('popup_title', 'immediate_invoice'),
      labelCancel: getFieldName('close'),
      showOnOk: false,
      skipConfirmOnClose:true
    };
    setExpectedInvoiceInProgress(true);

    dispatch(generateOneTimeInvoiceExpected(aid, lines))
    .then(success => {
      if (success.status !== 1) {
        throw new Error();
      }
      if (!isNumber(success?.data?.invoiceData?.totals?.after_vat_rounded)) {
        throw new Error();
      }
      const data = Immutable.Map({
        lines,
        aid,
        currency,
        price: success?.data?.invoiceData?.totals?.after_vat_rounded || '',
        pg_4_digit,
      });
      dispatch(showFormModal(data, ViewExpectedInvoice, config));
    }).catch(error => {
      dispatch(showDanger(getFieldName('error_retrieving_invoice', 'immediate_invoice')));
    }).finally(() => {
      setExpectedInvoiceInProgress(false);
    })
  }
  
  const onResetFormClick = () => {
    dispatch(clearImmediateInvoice())
  }

  const onRemoveClick = () => {
    const confirm = {
      message: getFieldName('confirm_reset_lines', 'immediate_invoice'),
      onOk: onRemoveOk,
      type: 'delete',
      labelOk: getFieldName('delete'),
    };
    dispatch(showConfirmModal(confirm));
  }

  const downloadInvoiceUrl = `${getConfig(['env','serverApiUrl'], '')}/api/accountinvoices?action=download&aid=${aid}&iid=${invoiceId}`;

  const actions = [
    { type: 'remove', showIcon: true, enable: !lines.isEmpty() && !isInvoiceConfirmed, onClick: onRemoveClick },
  ];

  const header = (
    <div>
      {getFieldName('list_title', 'immediate_invoice')}
      <div className="pull-right" style={{ marginTop: -5 }}>
        <Actions actions={actions} />
      </div>
    </div>
  );

  const linesRows = lines.map((line, idx) => [(
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
  ), (
    <Col key={`${line.get('id', uuid.v4())}_hr`} xsHidden={false} smHidden mdHidden lgHidden>
      <hr className="mt0 mb5"/>
    </Col>
  )]);

  return (
    <div className="immediate-invoice-setup">
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
              disabled={!lines.isEmpty()}
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
        <Panel header={header}>
          { !lines.isEmpty() && (
            <Col sm={12} className="form-inner-edit-rows">
              <FormGroup className="form-inner-edit-row header">
                <Col sm={3} xsHidden>
                  <label htmlFor="subscriber">{getFieldName('subscriber', 'immediate_invoice')}</label>
                  <span className="danger-red"> *</span>
                  <Help contents={getFieldName('subscriber_input_help', 'immediate_invoice')} />
                </Col>
                <Col sm={3} xsHidden>
                  <label htmlFor="product">{getFieldName('product', 'immediate_invoice')}</label>
                  <span className="danger-red"> *</span>
                  <Help contents={getFieldName('rate_input_help', 'immediate_invoice')} />
                </Col>
                <Col sm={2} xsHidden>
                  <label htmlFor="date">{getFieldName('date', 'immediate_invoice')}</label>
                  <span className="danger-red"> *</span>  
                </Col>
                <Col sm={1} xsHidden>
                  <label htmlFor="volume">{getFieldName('volume', 'immediate_invoice')}</label>
                  <span className="danger-red"> *</span>
                </Col>
                <Col sm={2} xsHidden>
                  <label htmlFor="price">{getFieldName('price', 'immediate_invoice')}</label>
                  <Help contents={getFieldName('price_input_help', 'immediate_invoice')} />
                </Col>
              </FormGroup>
              { linesRows }
            </Col>
          )}
          { lines.isEmpty() && (
            <Col sm={12} className="form-inner-edit-rows">
              <small>{getFieldName('empty_table_results', 'immediate_invoice')}</small>
            </Col>
          )}
          <Col sm={12} className="pl0 pr0">
            { isEditable && (
              <CreateButton
                onClick={onAddLine}
                label={getFieldName('add_line_btn', 'immediate_invoice')}
                disabled={!isNumber(aid) || expectedInvoiceInProgress}
              />
            )}
          </Col>
        </Panel>
        {!isInvoiceConfirmed && (
          <ActionButtons
            saveLabel={getFieldName('view_expected_invoice_btn', 'immediate_invoice')}
            onClickSave={onViewExpectedInvoice}
            disableSave={lines.isEmpty()}
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

ImmediateInvoiceSetup.defaultProps = {
  currency: '',
  accountsOptions: [],
  immediateInvoice: Immutable.Map(),
};

ImmediateInvoiceSetup.propTypes = {
  dispatch: PropTypes.func.isRequired,
  accountsOptions: PropTypes.array,
  currency: PropTypes.string,
  immediateInvoice: PropTypes.instanceOf(Immutable.Map),
};

const mapStateToProps = (state, props) => ({
  accountsOptions: accountsOptionsSelector(state, props),
  currency: currencySelector(state, props),
  immediateInvoice: itemSelector(state, props, 'immediate-invoice'),
});

export default connect(mapStateToProps)(ImmediateInvoiceSetup);
