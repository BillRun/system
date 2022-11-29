import React from 'react';
import { PageHeader } from 'react-bootstrap';
import { getConfig } from '@/common/Util'


const LogoImg = getConfig(['env', 'billrunCloudLogo'], getConfig(['env', 'billrunLogo'], ''));
const LogoImgPath = `${process.env.PUBLIC_URL}/assets/img/${LogoImg}`;
const supportMail = getConfig(['env', 'mailSupport'], '');


const Welcome = () => (
  <div style={{ marginTop: '10%', textAlign: 'center' }}>
    <PageHeader>
      Welcome to <img alt="BillRun Cloud" title="BillRun Cloud" src={LogoImgPath} style={{ height: 50, marginBottom: 20 }} />
    </PageHeader>
    {supportMail !== '' && (
      <p>For any support topics please contact<br /><a href={`mailto:${supportMail}`}>{supportMail}</a></p>
    )}
  </div>
);

export default Welcome;
