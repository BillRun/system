import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import Field from '@/components/Field';
import { Actions, StateIcon } from '@/components/Elements';


const Play = ({
  play,
  index,
  isDefaultDisabled,
  showEnableAction,
  onChangeDefault,
  onEdit,
  onDisable,
  onEnable,
  onRemove,
}) => {
  const getListActions = [
    { type: 'edit', helpText: 'Edit', onClick: onEdit },
    { type: 'enable', helpText: 'Enable', onClick: onEnable, show: showEnableAction },
    { type: 'disable', helpText: 'Disable', onClick: onDisable, show: !showEnableAction },
    { type: 'remove', helpText: 'Remove', onClick: onRemove },
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

Play.defaultProps = {
  play: Immutable.Map(),
  isNameEditable: false,
  isDefaultDisabled: false,
  showEnableAction: true,
};

export default Play;
