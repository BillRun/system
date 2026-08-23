import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Actions, StateIcon } from '@/components/Elements';

const Plugin = ({
  plugin = Immutable.Map(),
  index,
  showEnableAction = true,
  onEdit,
  onDisable,
  onEnable,
}) => {
  const getListActions = [
    { type: 'edit', helpText: 'Edit', onClick: onEdit, actionStyle: 'link', actionSize: 'xsmall' },
    { type: 'enable', helpText: 'Enable', onClick: onEnable, show: showEnableAction, actionStyle: 'link', actionSize: 'xsmall' },
    { type: 'disable', helpText: 'Disable', onClick: onDisable, show: !showEnableAction, actionStyle: 'link', actionSize: 'xsmall' },
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

export default Plugin;
