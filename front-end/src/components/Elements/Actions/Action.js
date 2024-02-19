import React, { memo, useCallback, useMemo } from 'react';
import PropTypes from 'prop-types';
import { Button, MenuItem } from 'react-bootstrap';
import classNames from 'classnames';
import { WithTooltip } from '@/components/Elements';


const Action = (props) => {
  const {
    type,
    label,
    data,
    actionStyle,
    showIcon,
    actionSize,
    actionClass,
    show,
    enable,
    helpText,
    renderFunc,
    onClick,
    index,
    isDropdown,
    ...otherProps
  } = props;

  const showAction = useMemo(() => (
    (typeof show === 'boolean' && show)
    || (typeof show === 'function' && show(data, type))
  ), [show, data, type]);

  const isEnable = useMemo(() => (
    typeof enable === 'function' ? enable(data, type) : enable
  ), [enable, data, type]);

  const isHelpText = useMemo(() => (
    (typeof helpText === 'string') ? helpText : helpText(data, type)
  ), [helpText, data, type]);

  const isCustomRender = useMemo(() => (
    renderFunc !== null && typeof renderFunc === 'function'
  ), [renderFunc]);

  const onClickAction = useCallback(() => {
    onClick(data, type);
  }, [onClick, data, type]);

  const iconClass = useMemo(() => classNames('fa fa-fw', {
    'fa-list-alt': type === 'report',
    'fa-eye': type === 'view',
    'fa-pencil': type === 'edit',
    'fa-files-o': type === 'clone',
    'fa-file-excel-o': ['export_csv', 'export'].includes(type),
    'danger-red': ['enable', 'remove'].includes(type),
    'fa-trash-o': type === 'remove',
    'fa-toggle-off': type === 'enable',
    'fa-toggle-on': type === 'disable',
    'fa-plus': ['add', 'expand'].includes(type),
    'fa-calendar': type === 'move',
    'fa-repeat': type === 'reopen',
    'fa-cloud-upload': type === 'import',
    'fa-refresh': type === 'refresh',
    'fa-arrow-left': type === 'back',
    'fa-minus': type === 'collapse',
    'fa-cog': type === 'settings',
    'fa-exclamation': type === 'tooltip',
  }), [type]);

  if (!showAction) {
    return null;
  }

  if (isDropdown) {
    const {onKeyDown, onSelect} = otherProps;
    return (
      <MenuItem
        eventKey={index}
        onKeyDown={onKeyDown}
        onSelect={onSelect}
        onClick={onClickAction}
        disabled={!isEnable}
        bsStyle={actionStyle === 'default' ? undefined : actionStyle}
        bsSize={actionSize}
        className={actionClass}
      >
        { showIcon && <i className={iconClass} /> }
        { showIcon && label.length > 0 && <span>&nbsp;</span> }
        { label.length > 0 && label}
      </MenuItem>
    );
  }


  return (
    <span className="action-button">
      <WithTooltip helpText={isHelpText}>
        { isCustomRender ? props.renderFunc(props)
          : (
            <Button
              onClick={onClickAction}
              bsStyle={actionStyle === 'default' ? undefined : actionStyle}
              bsSize={actionSize}
              className={actionClass}
              disabled={!isEnable}
            >
              { showIcon && <i className={iconClass} /> }
              { showIcon && label.length > 0 && <span>&nbsp;</span> }
              { label.length > 0 && label}
            </Button>
          )
        }
      </WithTooltip>
    </span>
  );
};

Action.defaultProps = {
  type: '',
  data: null,
  index: '',
  label: '',
  helpText: '',
  actionStyle: 'link',
  actionSize: undefined,
  actionClass: '',
  showIcon: true,
  enable: true,
  show: true,
  isDropdown: false,
  renderFunc: null,
  onClick: () => {},
};

Action.propTypes = {
  type: PropTypes.string,
  data: PropTypes.any,
  index: PropTypes.any,
  label: PropTypes.string,
  showIcon: PropTypes.bool,
  actionStyle: PropTypes.oneOf(['primary', 'success', 'info', 'warning', 'danger', 'link', 'default']),
  actionSize: PropTypes.oneOf(['large', 'small', 'xsmall']),
  actionClass: PropTypes.string,
  helpText: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.func,
  ]),
  enable: PropTypes.oneOfType([
    PropTypes.bool,
    PropTypes.func,
  ]),
  show: PropTypes.oneOfType([
    PropTypes.bool,
    PropTypes.func,
  ]),
  isDropdown: PropTypes.bool,
  renderFunc: PropTypes.func,
  onClick: PropTypes.func,
};

export default memo(Action);
