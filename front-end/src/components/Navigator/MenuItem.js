import React from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router';
import classNames from 'classnames';

const MenuItem = ({ route, id, active, icon, title, onSetActive }) => {
  const setActive = () => {
    onSetActive(id);
  };
  const linkClass = classNames({ active });
  return (
    <Link
      to={`/${route}`}
      id={id}
      className={linkClass}
      onClick={setActive}
    >
      <i className={icon} /><span>{title}</span>
    </Link>
  );
};

MenuItem.defaultProps = {
  route: '',
  id: '',
  active: false,
  icon: '',
  title: '',
  onSetActive: () => {},
};

MenuItem.propTypes = {
  route: PropTypes.string,
  id: PropTypes.string,
  active: PropTypes.bool,
  icon: PropTypes.string,
  title: PropTypes.string,
  onSetActive: PropTypes.func,
};

export default MenuItem;
