import React from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import { Col, Button, InputGroup, FormControl, OverlayTrigger, Tooltip } from 'react-bootstrap';
import { FormGroup, HelpBlock } from '@/common/BootstrapCompat';
import { getConfig } from '@/common/Util';

const CSVField = (props) => {
  const { index, field, fixed, width, allowMoveUp, allowMoveDown, onCheckedField, isChecked } = props;
  // Guard: errorMessages may not be provided by all callers.
  const allowedCharacters = props.errorMessages?.name?.allowedCharacters ?? '';
  const errorFieldName = allowedCharacters && !getConfig('keyRegex', '').test(field) ? allowedCharacters : '';

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
              <InputGroup.Text>
                <input type="checkbox" checked={isChecked} aria-label={index} onChange={CheckedField} />
              </InputGroup.Text>
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
          <Button size="sm" variant="outline-secondary" disabled={!allowMoveUp} onClick={onMoveFieldUp}>
            <i className="fa fa-arrow-up" /> Move up
          </Button>
          <Button size="sm" variant="outline-secondary" className="ml10" disabled={!allowMoveDown} onClick={onMoveFieldDown}>
            <i className="fa fa-arrow-down" /> Move down
          </Button>
          <Button size="sm" variant="outline-secondary" className="ml10" onClick={removeField}>
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


export default connect()(CSVField);
