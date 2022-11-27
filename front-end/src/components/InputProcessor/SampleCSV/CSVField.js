import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { HelpBlock, FormGroup, Col, Button, InputGroup, FormControl, OverlayTrigger, Tooltip } from 'react-bootstrap';
import { getConfig } from '@/common/Util';

const CSVField = (props) => {
  const { index, field, fixed, width, allowMoveUp, allowMoveDown, onCheckedField, isChecked } = props;
  const { errorMessages: { name: { allowedCharacters } } } = props;
  const errorFieldName = !getConfig('keyRegex', '').test(field) ? allowedCharacters : '';

  const removeField = () => {
    props.onRemoveField(index, field);
  };

  const onMoveFieldUp = () => {
    props.onMoveFieldUp(index);
  };

  const onMoveFieldDown = () => {
    props.onMoveFieldDown(index);
  };

  const onChange = (e) => {
    const { value } = e.target;
    props.onChange(index, value);
  };

  const CheckedField = (e) => {
    const { checked } = e.target;
    onCheckedField(index, checked, field);
  };

  const tooltip = (
    <Tooltip id="tooltip">
      Only checked fields will be available in next stages
    </Tooltip>
  );

  return (
    <Col lg={12} md={12}>
      <FormGroup className="mb0" validationState={errorFieldName.length > 0 ? 'error' : null} >
        <Col lg={4} md={4}>
          <InputGroup>
            <OverlayTrigger placement="top" overlay={tooltip}>
              <InputGroup.Addon>
                <input type="checkbox" checked={isChecked} aria-label={index} onChange={CheckedField} />
              </InputGroup.Addon>
            </OverlayTrigger>
            <FormControl type="text" onChange={onChange} value={field} />
          </InputGroup>
        </Col>
        <Col lg={1} md={1}>
          { fixed &&
            <input
              type="number"
              className="form-control"
              data-field={index}
              disabled={!fixed}
              min="0"
              onChange={props.onSetFieldWidth}
              value={width}
            />
          }
        </Col>
        <Col lg={5} md={5}>
          <Button bsSize="small" disabled={!allowMoveUp} onClick={onMoveFieldUp}>
            <i className="fa fa-arrow-up" /> Move up
          </Button>
          <Button bsSize="small" className="ml10" disabled={!allowMoveDown} onClick={onMoveFieldDown}>
            <i className="fa fa-arrow-down" /> Move down
          </Button>
          <Button bsSize="small" className="ml10" onClick={removeField}>
            <i className="fa fa-trash-o danger-red" /> Remove
          </Button>
        </Col>
        { errorFieldName.length > 0 && (
          <Col lg={10} md={10}><HelpBlock>{errorFieldName}</HelpBlock></Col>
        )}
      </FormGroup>
    </Col>
  );
};

CSVField.propTypes = {
  field: PropTypes.string,
  index: PropTypes.number,
  fixed: PropTypes.bool,
  errorMessages: PropTypes.object,
  allowMoveUp: PropTypes.bool,
  allowMoveDown: PropTypes.bool,
  width: PropTypes.oneOfType([
    PropTypes.string,
    PropTypes.number,
  ]),
  isChecked: PropTypes.bool,
  onChange: PropTypes.func,
  onRemoveField: PropTypes.func,
  onMoveFieldUp: PropTypes.func,
  onSetFieldWidth: PropTypes.func,
  onMoveFieldDown: PropTypes.func,
  onCheckedField: PropTypes.func,
};

CSVField.defaultProps = {
  field: '',
  index: 0,
  fixed: false,
  allowMoveUp: true,
  allowMoveDown: true,
  width: '',
  isChecked: true,
  errorMessages: {
    name: {
      allowedCharacters: 'Field name contains illegal characters, name should contain only alphabets, numbers and underscores (A-Z, a-z, 0-9, _)',
    },
  },
  onChange: () => {},
  onRemoveField: () => {},
  onMoveFieldUp: () => {},
  onSetFieldWidth: () => {},
  onMoveFieldDown: () => {},
  onCheckedField: () => {},
};

export default connect()(CSVField);
