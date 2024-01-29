import React from 'react';
import PropTypes from 'prop-types';
import logo from './img/logo.jpg';

const Header = ({ page, total }) => (
  <div className="section section-header">
    <div className="table">
      <table>
        <tbody>
          <tr>
            <td className="step-company-details-header">
              <img src={logo} alt="logo" style={{ width: 60, objectFit: 'contain' }} />&nbsp;&nbsp;Company Name
            </td>
            <td>
              <div className="paging">page <span className="page">{page}</span> of <span className="topage">{total}</span></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
);

Header.defaultProps = {
  page: 1,
  total: 1,
};

Header.propTypes = {
  page: PropTypes.node,
  total: PropTypes.node,
};

export default Header;
