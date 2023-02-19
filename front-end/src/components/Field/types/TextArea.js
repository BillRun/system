import React from 'react';

const TextArea = (props) => {
  let { onChange,
        id,
        value,
        placeholder = "",
        disabled,
        editable } = props;

  return editable
       ? (<textarea
	      id={ id }
	      className="form-control"
	      value={ value }
	      onChange={ onChange }
	      placeholder={ placeholder }
	      disabled={ disabled }></textarea>)
       : (<span>{ value }</span>);
};

export default TextArea;
