import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { OverlayTrigger, Tooltip } from 'react-bootstrap';
import classNames from 'classnames';


const SubMenu = (props) => {
  const { id, open, active, icon, title, collapse, children } = props;
  const onClick = (e) => {
    e.preventDefault();
    props.onClick(id);
  };
  const liClass = classNames('sub-menu', {
    open,
  });
  const aClass = classNames({
    active: active !== false,
  });
  const link = (
    <a href={`#id`} id={id} onClick={onClick} className={aClass} key="main_link">
      <i className={icon} /><span>{title}</span>
      <span className="fa arrow" />
    </a>
  );
  const subLinks = (<ul className="nav nav-second-level">{children}</ul>);
  const showTooltip = collapse || (!open && active !== false);

  if (!showTooltip) {
    return (<li className={liClass}>{link}{subLinks}</li>);
  }

  const tooltip = active === false
    ? <Tooltip id={id}><i className={`fa ${icon} fa-fw`} /> {title}</Tooltip>
    : (
      <Tooltip id={`${id}_${active.get('id', 'open')}`}>
        <span>
          <i className={`fa ${icon} fa-fw`} /> {title}&nbsp;
          <i className="fa fa-angle-right fa-fw" /> {active.get('title', '')}
        </span>
      </Tooltip>
    );
  return (
    <li className={liClass}>
      <OverlayTrigger placement={collapse ? 'right' : 'top'} overlay={tooltip}>
        {link}
      </OverlayTrigger>
      {subLinks}
    </li>
  );
};

SubMenu.defaultProps = {
  children: null,
  id: '',
  title: '',
  icon: '',
  open: false,
  active: false,
  collapse: false,
  onClick: () => {},
};

SubMenu.propTypes = {
  children: PropTypes.node,
  id: PropTypes.string,
  title: PropTypes.string,
  icon: PropTypes.string,
  open: PropTypes.bool,
  collapse: PropTypes.bool,
  active: PropTypes.oneOfType([
    PropTypes.instanceOf(Immutable.Map),
    PropTypes.bool,
  ]),
  onClick: PropTypes.func,
};

export default SubMenu;
