import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Actions, StateIcon } from '@/components/Elements';

const Plugin = ({
  plugin,
  index,
  showEnableAction,
  onEdit,
  onDisable,
  onEnable,
}) => {
  const getListActions = [
    { type: 'edit', helpText: 'Edit', onClick: onEdit },
    { type: 'enable', helpText: 'Enable', onClick: onEnable, show: showEnableAction },
    { type: 'disable', helpText: 'Disable', onClick: onDisable, show: !showEnableAction },
  ];
  return (
    <tr key={index}>
      <td>
        <StateIcon status={plugin.get('enabled', true) ? 'active' : 'expired'} />
      </td>
      <td>{plugin.get('label', '')}</td>
      <td className="td-actions">
        <Actions actions={getListActions} data={plugin} />
      </td>
    </tr>
  );
};

Plugin.propTypes = {
  plugin: PropTypes.instanceOf(Immutable.Map),
  index: PropTypes.number.isRequired,
  showEnableAction: PropTypes.bool,
  onEdit: PropTypes.func.isRequired,
  onDisable: PropTypes.func.isRequired,
  onEnable: PropTypes.func.isRequired,
};

Plugin.defaultProps = {
  plugin: Immutable.Map(),
  showEnableAction: true,
};

export default Plugin;
