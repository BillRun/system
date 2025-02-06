import React, { PureComponent } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import uuid from 'uuid';
import { FormGroup, InputGroup, Button } from 'react-bootstrap';
import { CreateButton } from '@/components/Elements';
import Range from './Range';

class Ranges extends PureComponent {

  static propTypes = {
    id: PropTypes.string,
    value: PropTypes.instanceOf(Immutable.List),
    label: PropTypes.string,
    multi: PropTypes.bool,
    editable: PropTypes.bool,
    disabled: PropTypes.bool,
    removable: PropTypes.bool,
    onChange: PropTypes.func,
  };

  static defaultProps = {
    id: undefined,
    value: Immutable.List(),
    label: '',
    inputProps: {},
    multi: false,
    editable: true,
    removable: true,
    disabled: true,
    onChange: () => {},
  };

  constructor(props) {
    super(props);
    this.state = {
      id: props.id || uuid.v4(),
    };
  }


  onChange = (rangeValue, index) => {
    const { value } = this.props;
    this.props.onChange(value.set(index, rangeValue));
  }

  onAdd = () => {
    const { value } = this.props;
    this.props.onChange(value.push(Immutable.Map({ from: '', to: '' })));
  }

  onRemove = (index) => {
    const { value } = this.props;
    this.props.onChange(value.delete(index));
  }

  render() {
    const {
      onChange,
      value,
      editable,
      disabled,
      label,
      multi,
      removable,
      ...otherProps
    } = this.props;
    const { id } = this.state;

    if (!editable) {
      return (
        <span>
          {value.map((rangeValue, index) => (
            <span key={`range_${id}_${index}`}>
              {index > 0 && ", "}
              <Range {...otherProps} value={rangeValue} editable={false} />
            </span>
          ))}
        </span>
      );
    }
    const ranges = value.map((rangeValue, index) => {
      const onChangeRange = (v) => {
        this.onChange(v, index);
      };
      const onRemoveRange = () => {
        this.onRemove(index);
      };
      return (
        <FormGroup key={`range_${id}_${index}`} className="rangesField form-inner-edit-row mr0 ml0">
          <InputGroup style={{ width: '100%' }}>
            <Range
              {...otherProps}
              value={rangeValue}
              onChange={onChangeRange}
              editable={true}
              disabled={disabled}
            />
            { removable && (
              <InputGroup.Button>
                <Button onClick={onRemoveRange} disabled={disabled} >
                  <i className="fa fa-fw fa-trash-o danger-red" />
                </Button>
              </InputGroup.Button>
            ) }
          </InputGroup>
        </FormGroup>
      );
    });
    const hasEmptyValue = value.size > 0 && value.some(range => range.get('from', '') === '' || range.get('to', '') === '');
    return (
      <div>
        {ranges}
        {(multi || (!multi && value.size === 0)) && (
          <CreateButton
            buttonStyle={{ marginTop: 5, marginBottom: 10 }}
            onClick={this.onAdd}
            action="Add"
            label=""
            type={label}
            disabled={disabled || hasEmptyValue}
          />
        )}
      </div>
    );
  }

}

export default Ranges;
