import React from 'react';
import Field from '@/components/Field';

const salutation_options = [
  { value: 'mr', label: 'Mr.' },
  { value: 'mrs', label: 'Mrs.' },
  { value: 'miss', label: 'Miss' },
  { value: 'dr', label: 'Dr.' },
  { value: 'sir', label: 'Sir' }
];

const Salutation = (props) => {
  const onChange = (value) => {
    const e = {target: {id: props.id, value}};
    props.onChange(e);
  };

  return (
    <Field
      fieldType="select"
      options={ salutation_options }
      onChange={ onChange }
      value={ props.value }
    />
  );
};

export default Salutation;
