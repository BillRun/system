import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import isNumber from 'is-number';
import moment from 'moment';
import getSymbolFromCurrency from 'currency-symbol-map';
import { Col, FormGroup, Button } from 'react-bootstrap';
import Field from '@/components/Field';
import { SubscriberAsyncSearch, RateAsyncSearch } from '@/components/Elements';
import {
  getConfig,
  getFieldName,
} from '@/common/Util';


const InvoiceLine = ({ line, index, aid, editable, currency, onChange, onRemove }) => {
  const apiFormat = getConfig('apiDateTimeFormat', '');
  const currencySymbol = getSymbolFromCurrency(currency);
  const price = line.get('price', '');
  const volume = line.get('volume', '');
  const errors = line.get('errors', Immutable.Map());
  const date = moment(line.get('date', null));

  const onChangeSubscriber = (sid, option) => {
    onChange([index], line
      .set('sid', sid)
      .set('subscriber_name', option.label)
      .delete('errors')

    );
  }

  const onChangeRate = (key, option) => {
    onChange([index], line
      .set('rate', key)
      .set('rate_name', option.label)
      .delete('errors')
    );
  }

  const onChangePrice = (e) => {
    const { value } = e.target;
    onChange([index], line
      .set('price', value)
      .delete('errors')
    );
  }

  const onChangeVolume = (e) => {
    const { value } = e.target;
    const newVolume = isNumber(value) ? value : 1;
    onChange([index], line
      .set('volume', newVolume)
      .delete('errors')
    );
  }

  const onChangeDate = (date) => {
    const isValidDate = moment.isMoment(date) && date.isValid();
    const newDate = isValidDate ? date : moment();
    onChange([index], line
      .set('date', newDate.format(apiFormat))
      .delete('errors')
    );
  }

  const onRemoveLine = () => {
    onRemove(index);
  }

  return (
    <Col sm={12}>
      <FormGroup className="form-inner-edit-row row" validationState={errors.isEmpty() ? null: 'error'}>
        <Col sm={3}>
          <Col xsHidden={false} smHidden mdHidden lgHidden>
            <label htmlFor="subscriber" >{getFieldName('subscriber', 'immediate_invoice')}</label>
          </Col>
          <SubscriberAsyncSearch
            sid={line.get('sid')}
            aid={aid}
            label={line.get('subscriber_name')}
            onChange={onChangeSubscriber}
            editable={editable}
          />
        </Col>
        <Col sm={3}>
          <Col xsHidden={false} smHidden mdHidden lgHidden>
            <label htmlFor="product" >{getFieldName('product', 'immediate_invoice')}</label>
            <span className="danger-red"> *</span>
          </Col>
          <RateAsyncSearch
            rate={line.get('rate')}
            label={line.get('rate_name')}
            onChange={onChangeRate}
            editable={editable}
          />
        </Col>
        <Col sm={2}>
          <Col xsHidden={false} smHidden mdHidden lgHidden>
            <label htmlFor="date" >{getFieldName('date', 'immediate_invoice')}</label>
            <span className="danger-red"> *</span>
          </Col>
          <Field
            fieldType="date"
            value={date}
            style={{ display: 'inline-block' }}
            onChange={onChangeDate}
            disabled={!editable}
          />
        </Col>
        <Col sm={1}>
          <Col xsHidden={false} smHidden mdHidden lgHidden>
            <label htmlFor="volume" >{getFieldName('volume', 'immediate_invoice')}</label>
            <span className="danger-red"> *</span>
          </Col>
          <Field
            fieldType="number"
            value={volume}
            onChange={onChangeVolume}
            disabled={!editable}
          />
        </Col>
        <Col sm={2}>
          <Col xsHidden={false} smHidden mdHidden lgHidden>
            <label htmlFor="price" >{getFieldName('price', 'immediate_invoice')}</label>
          </Col>
          <Field
            fieldType="number"
            onChange={onChangePrice}
            value={price}
            suffix={currencySymbol}
            disabled={!editable}
          />
        </Col>
        <Col sm={1} className="actions">
          {editable && (
            <Button onClick={onRemoveLine} bsSize="small" className="pull-left">
              <i className="fa fa-trash-o danger-red" />
            </Button>
          )}
        </Col>
      </FormGroup>
    </Col>
  );
}

InvoiceLine.defaultProps = {
  line: Immutable.Map(),
  currency: '',
  editable: true,
};

InvoiceLine.propTypes = {
  line: PropTypes.instanceOf(Immutable.Map),
  aid: PropTypes.number,
  index: PropTypes.number.isRequired,
  editable: PropTypes.bool,
  currency: PropTypes.string,
  onChange: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
};

export default InvoiceLine;