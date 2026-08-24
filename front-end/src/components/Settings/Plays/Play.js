import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import Field from '@/components/Field';
import { Actions, StateIcon } from '@/components/Elements';

const Play = ({
  play = Immutable.Map(),
  index,
  isDefaultDisabled = false,
  showEnableAction = true,
  onChangeDefault,
  onEdit,
  onDisable,
  onEnable,
  onRemove,
}) => {
  const getListActions = [
    { type: 'edit', helpText: 'Edit', onClick: onEdit, actionStyle: 'link', actionSize: 'xsmall' },
    { type: 'enable', helpText: 'Enable', onClick: onEnable, show: showEnableAction, actionStyle: 'link', actionSize: 'xsmall' },
    { type: 'disable', helpText: 'Disable', onClick: onDisable, show: !showEnableAction, actionStyle: 'link', actionSize: 'xsmall' },
    { type: 'remove', helpText: 'Remove', onClick: onRemove, actionStyle: 'link', actionSize: 'xsmall' },
  ];
  return (
    <tr key={index}>
      <td>
        <StateIcon status={play.get('enabled', true) ? 'active' : 'expired'} />
      </td>
      <td>{play.get('name', '')}</td>
      <td>{play.get('label', '')}</td>

      <td className="text-center">
        <Field
          fieldType="radio"
          name="default-play"
          onChange={onChangeDefault}
          checked={play.get('default', false)}
          disabled={isDefaultDisabled}
        />
      </td>
      <td className="td-actions">
        <Actions actions={getListActions} data={play} />
      </td>
    </tr>
  );
};

Play.propTypes = {
  play: PropTypes.instanceOf(Immutable.Map),
  index: PropTypes.number.isRequired,
  isDefaultDisabled: PropTypes.bool,
  showEnableAction: PropTypes.bool,
  onChangeDefault: PropTypes.func.isRequired,
  onEdit: PropTypes.func.isRequired,
  onDisable: PropTypes.func.isRequired,
  onEnable: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
};

export default Play;
