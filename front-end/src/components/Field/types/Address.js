import React from 'react';

const Address = ({ onChange, id, value, editable, disabled }) => {
  const input = editable
    ? (<input type="text" className="form-control address" id={id} value={value} onChange={onChange} disabled={disabled}/>)
    : (<span>{value}</span>);
  return (
    <div>{input}</div>
    );
}

export default Address;
