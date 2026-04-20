import React, { memo, useCallback, useMemo } from 'react';
import PropTypes from 'prop-types';
import { Button, Dropdown } from 'react-bootstrap';
import classNames from 'classnames';
import { WithTooltip } from '@/components/Elements';


// Map Bootstrap 3 size names to Bootstrap 5 equivalents
const mapActionSize = size => {
  if (size === 'small') return 'sm';
  if (size === 'large') return 'lg';
  if (size === 'xsmall') return undefined;
  return size;
};

const Action = (props) => {
  const {
    type,
    label,
    data,
    actionStyle,
    showIcon = true,
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
  const actionLabel = typeof label === 'string' ? label : '';
  const iconLinkTypes = ['remove', 'edit', 'view', 'clone', 'enable', 'disable', 'collapse'];
  const effectiveActionStyle = typeof actionStyle === 'undefined' && iconLinkTypes.includes(type)
    ? 'link'
    : actionStyle;
  const shouldUseXsIconButton = typeof actionSize === 'undefined'
    && iconLinkTypes.includes(type)
    && effectiveActionStyle !== 'default';
  const effectiveActionSize = shouldUseXsIconButton
    ? 'xsmall'
    : actionSize;
  const mappedVariant = effectiveActionStyle === 'default' ? 'outline-secondary' : effectiveActionStyle;

  const showAction = useMemo(() => {
    if (typeof show === 'undefined') {
      return true;
    }
    if (typeof show === 'boolean') {
      return show;
    }
    if (typeof show === 'function') {
      return show(data, type);
    }
    return false;
  }, [show, data, type]);

  const isEnable = useMemo(() => {
    if (typeof enable === 'undefined') {
      return true;
    }
    return typeof enable === 'function' ? enable(data, type) : enable;
  }, [enable, data, type]);

  const isHelpText = useMemo(() => {
    if (typeof helpText === 'string') {
      return helpText;
    }
    if (typeof helpText === 'function') {
      return helpText(data, type);
    }
    return '';
  }, [helpText, data, type]);

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
    'fa-th-list': type === 'list',
    'fa-exclamation': type === 'tooltip',
    'fa-play': type === 'start',
    'fa-stop': type === 'stop',
    'fa-forward': type === 're-start',
    'fa-retweet': type === 'reset',
  }), [type]);
  const actionClassName = useMemo(() => classNames(actionClass, {
    'btn-xs': effectiveActionSize === 'xsmall',
  }), [actionClass, effectiveActionSize]);

  if (!showAction) {
    return null;
  }

  if (isDropdown) {
    const {onKeyDown, onSelect} = otherProps;
    return (
      <Dropdown.Item
        eventKey={index}
        onKeyDown={onKeyDown}
        onSelect={onSelect}
        onClick={onClickAction}
        disabled={!isEnable}
        variant={mappedVariant}
        size={mapActionSize(effectiveActionSize)}
        className={actionClassName}
      >
        { showIcon && <i className={iconClass} /> }
        { showIcon && actionLabel.length > 0 && <span>&nbsp;</span> }
        { actionLabel.length > 0 && actionLabel}
      </Dropdown.Item>
    );
  }


  return (
    <span className="action-button">
      <WithTooltip helpText={isHelpText}>
        { isCustomRender ? props.renderFunc(props)
          : (
            <Button
              onClick={onClickAction}
              variant={mappedVariant}
              size={mapActionSize(effectiveActionSize)}
              className={actionClassName}
              disabled={!isEnable}
            >
              { showIcon && <i className={iconClass} /> }
              { showIcon && actionLabel.length > 0 && <span>&nbsp;</span> }
              { actionLabel.length > 0 && actionLabel}
            </Button>
          )
        }
      </WithTooltip>
    </span>
  );
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
