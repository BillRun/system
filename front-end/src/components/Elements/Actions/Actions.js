import React, { memo } from 'react';
import PropTypes from 'prop-types';
import classNames from 'classnames';
import Action from './Action';
import { ButtonGroup, DropdownButton } from 'react-bootstrap';

// Map Bootstrap 3 size names to Bootstrap 5 equivalents
const mapSize = size => {
  if (size === 'small') return 'sm';
  if (size === 'large') return 'lg';
  if (size === 'xsmall') return undefined;
  return size;
};
const Actions = ({
  actions = [],
  data = null,
  type = 'default',
  doropDownLabel = 'Actions',
  doropDownStyle = 'primary',
  doropDownSize = 'xsmall',
  doropDownClass = '',
}) => {
  const doropDownClassName = classNames(doropDownClass, {
    'btn-xs': doropDownSize === 'xsmall',
  });

  if (type === 'dropdown') {
    return (
      <DropdownButton
        variant={doropDownStyle === 'default' ? undefined : doropDownStyle}
        size={mapSize(doropDownSize)}
        title={doropDownLabel}
        id="dropdown-size-extra-small"
        className={doropDownClassName}
      >
      { actions.map((action, idx) => (
        <Action {...action} data={data} key={idx} isDropdown={true} index={idx} />)
      )}
      </DropdownButton>
    )
  }

  if (type === 'group') {
    return (
      <ButtonGroup className="actions-buttons">
        { actions.map((action, idx) => (<Action {...action} data={data} key={idx} />))}
      </ButtonGroup>
    );
  }
  return (
    <div className="actions-buttons">
      { actions
      .filter((action) => {
        if (typeof action.show === 'undefined') {
          return true;
        }
        if (typeof action.show === 'function') {
          return action.show(data);
        }
        return action.show;
      })
      .map((action, idx, list) => {
        const isLast = idx === (list.length - 1);
        const actionClass = classNames({
          mr10: !isLast,
          mr0: isLast,
        });
        return (
          <span key={`${action.type}_${idx}`} className={actionClass} >
            <Action {...action} data={data} />
          </span>
        );
      })}
    </div>
  );
}

Actions.propTypes = {
  actions: PropTypes.arrayOf(PropTypes.object),
  data: PropTypes.any,
  isGroup: PropTypes.bool,
  inDoropDown: PropTypes.bool,
  type: PropTypes.oneOf(['group', 'dropdown', 'default']),
  doropDownLabel: PropTypes.string,
  doropDownStyle: PropTypes.oneOf(['primary', 'success', 'info', 'warning', 'danger', 'link', 'default']),
  doropDownSize: PropTypes.oneOf(['large', 'small', 'xsmall']),
  doropDownClass: PropTypes.string,
};

export default memo(Actions);
