import React from 'react';
import { getConfig } from '@/common/Util'


const LogoImg = getConfig(['env', 'billrunCloudLogo'], getConfig(['env', 'billrunLogo'], ''));
const LogoImgPath = `${process.env.PUBLIC_URL}/assets/img/${LogoImg}`;
const serverApiVersion = getConfig(['env', 'serverApiVersion'],'');


const About = () => (
  <div style={{ marginTop: 20, textAlign: 'left' }}>
    { LogoImg !== '' && (
      <img alt="BillRun Cloud" title="BillRun Cloud" src={LogoImgPath} style={{ height: 25, marginBottom: 20 }} />
    )}
    {serverApiVersion !== '' && (
      <h5>version <strong>{serverApiVersion}</strong></h5>
    )}
  </div>
);

export default About;
