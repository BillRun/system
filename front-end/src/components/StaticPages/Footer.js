import React from 'react';
import { getConfig } from '@/common/Util';

const LogoImg = `${process.env.PUBLIC_URL}/assets/img/${getConfig(['env', 'billrunLogo'], '')}`;

const Footer = () => (
  <div id="footer">
    <div>
      <p className="muted credit">
        <span style={{ verticalAlign: 'middle', marginRight: 5 }}><small>Powered by</small></span>
        <a href="http://bill.run/" target="_blank" rel="noreferrer noopener powered-by">
          <img src={LogoImg} style={{ height: 20 }} alt="Logo" />
        </a>
      </p>
    </div>
  </div>
);

export default Footer;
