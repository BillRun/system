import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import CSVField from './CSVField';


const CSVFields = (props) => {
  const { settings } = props;
  const fixed = settings.get('delimiter_type', '') === 'fixed';
  const fields = settings.get('unfiltered_fields', Immutable.List()).map((field, index) => (
    <div key={index}>
      <div className="form-group">
        <CSVField
          index={index}
          onRemoveField={props.onRemoveField}
          field={field.get('name', '')}
          onSetFieldWidth={props.onSetFieldWidth}
          fixed={fixed}
          isChecked={field.get('checked', true)}
          allowMoveUp={index !== 0}
          allowMoveDown={index !== settings.get('unfiltered_fields', Immutable.List()).size - 1}
          onMoveFieldDown={props.onMoveFieldDown}
          onMoveFieldUp={props.onMoveFieldUp}
          onChange={props.onChangeCSVField}
          width={settings.getIn(['field_widths', index], '')}
          onCheckedField={props.onCheckedField}
        />
      </div>
      <div className="separator" />
    </div>
  ));
  return (
    <div>{fields}</div>
  );
};

CSVFields.defaultProps = {
  settings: Immutable.Map(),
  onRemoveField: () => {},
  onSetFieldWidth: () => {},
  onMoveFieldUp: () => {},
  onMoveFieldDown: () => {},
  onChangeCSVField: () => {},
  onCheckedField: () => {},
};

CSVFields.propTypes = {
  settings: PropTypes.instanceOf(Immutable.Map),
  onRemoveField: PropTypes.func,
  onSetFieldWidth: PropTypes.func,
  onMoveFieldUp: PropTypes.func,
  onMoveFieldDown: PropTypes.func,
  onChangeCSVField: PropTypes.func,
  onCheckedField: PropTypes.func,
};

export default connect()(CSVFields);
