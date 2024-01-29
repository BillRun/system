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
} from '@/common/Util';


const InvoiceLine = ({ line, index, aid, editable, currency, onChange, onRemove }) => {
  const apiFormat = getConfig('apiDateTimeFormat', '');
  const currencySymbol = getSymbolFromCurrency(currency);
  const price = line.get('price', '');
  const volume = line.get('volume', '');
  const date = moment(line.get('date', null));

  const onChangeSubscriber = (sid) => {
    onChange([index, 'sid'], sid);
  }

  const onChangeRate = (key) => {
    onChange([index, 'rate'], key);
  }

  const onChangePrice = (e) => {
    const { value } = e.target;
    onChange([index, 'price'], value);
  }

  const onChangeVolume = (e) => {
    const { value } = e.target;
    const newVolume = isNumber(value) ? value : 1;
    onChange([index, 'volume'], newVolume);
  }

  const onChangeDate = (date) => {
    const isValidDate = moment.isMoment(date) && date.isValid();
    const newDate = isValidDate ? date : moment();
    onChange([index, 'date'], newDate.format(apiFormat));
  }

  const onRemoveLine = () => {
    onRemove(index);
  }

  return (
    <FormGroup className="form-inner-edit-row body">
        <Col sm={3} xsHidden>
          <SubscriberAsyncSearch
            aid={aid}
            onChange={onChangeSubscriber}
            editable={editable}
          />
        </Col>
        <Col sm={3}>
          <RateAsyncSearch
            onChange={onChangeRate}
            editable={editable}
          />
        </Col>
        <Col sm={2}>
          <Field
            fieldType="number"
            onChange={onChangePrice}
            value={price}
            suffix={currencySymbol}
            disabled={!editable}
          />
        </Col>
        <Col sm={2}>
          <Field
            fieldType="date"
            value={date}
            style={{ display: 'inline-block' }}
            onChange={onChangeDate}
            disabled={!editable}
          />
        </Col>
        <Col sm={1}>
          <Field
            fieldType="number"
            value={volume}
            onChange={onChangeVolume}
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