import React, { memo } from 'react';
import PropTypes from 'prop-types';
import classNames from 'classnames';
import Action from './Action';
import { ButtonGroup, DropdownButton } from 'react-bootstrap';

const Actions = ({
  actions,
  data,
  type,
  doropDownLabel,
  doropDownStyle,
  doropDownSize,
  doropDownClass,
}) => {
  if (type === 'dropdown') {
    return (
      <DropdownButton
        bsStyle={doropDownStyle === 'default' ? undefined : doropDownStyle}
        bsSize={doropDownSize}
        title={doropDownLabel}
        id="dropdown-size-extra-small"
        className={doropDownClass}
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
          <span key={action.type} className={actionClass} >
            <Action {...action} data={data} />
          </span>
        );
      })}
    </div>
  );
}

Actions.defaultProps = {
  actions: [],
  data: null,
  type: 'default',
  doropDownLabel: 'Actions',
  doropDownStyle: 'primary',
  doropDownSize: 'xsmall',
  doropDownClass: '',

};

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
