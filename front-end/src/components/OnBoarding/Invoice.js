import React from 'react';
import Header from './assests/Header';
import htmlHeaderCustomer from './assests/header-customer.html';
import htmlHeaderSubscriber from './assests/header-subscriber.html';
import htmlSummaryCustomer from './assests/summary-customer.html';
import htmlSummarySubscription from './assests/summary-subscription.html';
import htmlLineItems from './assests/line-items.html';
import htmlFooter from './assests/footer.html';
import './assests/style/invoice.scss';
import './assests/style/font.scss';

const Invoice = () => (
  <div className="invoice-help">
    <Header page="1" total="2" />
    <div dangerouslySetInnerHTML={{ __html: htmlHeaderCustomer }} />
    <div dangerouslySetInnerHTML={{ __html: htmlSummaryCustomer }} />
    <div dangerouslySetInnerHTML={{ __html: htmlFooter }} />
    <hr className="page-separator" />
    <Header page="2" total="2" />
    <div dangerouslySetInnerHTML={{ __html: htmlHeaderSubscriber }} />
    <div dangerouslySetInnerHTML={{ __html: htmlSummarySubscription }} />
    <div dangerouslySetInnerHTML={{ __html: htmlLineItems }} />
    <div dangerouslySetInnerHTML={{ __html: htmlFooter }} />
  </div>
);

Invoice.defaultProps = {

};

Invoice.propTypes = {

};

export default Invoice;
