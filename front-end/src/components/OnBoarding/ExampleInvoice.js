import React from 'react';
import PropTypes from 'prop-types';
import { Button } from 'react-bootstrap';
import CloseOnEscape from 'react-close-on-escape';
import Invoice from './Invoice';
import { ActionButtons } from '@/components/Elements';


const ExampleInvoice = ({ onPause, onStop }) => (
  <CloseOnEscape onEscape={onPause}>
    <div className="ExampleInvoice">
      <div className="invoice-page-modal" />
      <div className="invoice-page-wrapper">
        <div className="clearfix" style={{ marginBottom: 25 }}>
          <div className="pull-left"><h4 style={{ margin: 0 }}>Example Invoice</h4></div>
          <div className="pull-right">
            <Button bsStyle="link" onClick={onPause} className="close">
              <i className="fa fa-times" style={{ color: '#222222', fontSize: 16, marginTop: -10, marginRight: -10 }} />
            </Button>
          </div>
        </div>
        <Invoice />
        <hr />
        <ActionButtons
          saveLabel="Pause"
          onClickSave={onPause}
          cancelLabel="End Tour"
          onClickCancel={onStop}
        />
      </div>
    </div>
  </CloseOnEscape>
);

ExampleInvoice.defaultProps = {
  onPause: () => {},
  onStop: () => {},
};

ExampleInvoice.propTypes = {
  onPause: PropTypes.func,
  onStop: PropTypes.func,
};

export default ExampleInvoice;
