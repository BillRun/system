import React from 'react';
import PropTypes from 'prop-types';
import { Tab, Panel } from 'react-bootstrap';
import { TabsWrapper } from '@/components/Elements';
import InvoiceReady from './InvoiceReady';

const EmailTemplates = ({ location }) => (
  <div>
    <TabsWrapper id="EmailTemplatesTab" location={location}>
      <Tab title="Invoice ready" eventKey={1}>
        <Panel style={{ borderTop: 'none' }}>
          <InvoiceReady />
        </Panel>
      </Tab>
    </TabsWrapper>
  </div>
);

EmailTemplates.propTypes = {
  location: PropTypes.object.isRequired,
};

export default EmailTemplates;
