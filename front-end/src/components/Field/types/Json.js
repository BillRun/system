import React, { Component } from 'react';
import PropTypes from 'prop-types';
import JSONInput from 'react-json-editor-ajrm';
import locale from 'react-json-editor-ajrm/locale/en';
import uuid from 'uuid';



class Json extends Component {

  static propTypes = {
    id: PropTypes.string,
    value: PropTypes.oneOfType([
      PropTypes.object,
    ]),
    required: PropTypes.bool,
    disabled: PropTypes.bool,
    editable: PropTypes.bool,
    tooltip: PropTypes.string,
    onChange: PropTypes.func,
  };

  static defaultProps = {
    value: null,
    id: undefined,
    required: false,
    disabled: false,
    editable: true,
    tooltip: '',
    onChange: () => {},
  };

  constructor(props) {
    super(props);
    this.state = {
      id: props.id || uuid.v4(),
    };
  }
  
  colors = {
    default: "#222222"
  };

  wrapperStyle = {
    height: 'auto',
    padding: '6px 4px'
  };

  onChangeJson = (val) => {
    const { onChange } = this.props;
    if (!val.error) {
      onChange(val.jsObject);
    } else {
      onChange(false);
    }
  }

  render() {
    const { value, editable, disabled } = this.props;
    const { id } = this.state;
    
    return (
      <div className="form-control" style={this.wrapperStyle}>
        <JSONInput
          id={id}
          height="auto"
          width="100%"
          theme="light_mitsuketa_tribute"
          viewOnly={!editable || disabled}
          colors={this.colors}
          placeholder={value}
          onChange={this.onChangeJson}
          locale={locale}
          confirmGood={false}
        />
      </div>
    );
  }

};



export default Json;
