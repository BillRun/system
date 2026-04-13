import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Form, FormGroup, Col, Button, ControlLabel, HelpBlock} from 'react-bootstrap';

const UploadTransactionsFile = (props) => {
  const { item = Immutable.Map() } = props;
  const fileError = null;
  const isFileSelected = item.get('file_name', '') !== '';
  const onFileReset = (e) => {
    e.target.value = null;
    props.updateField('file', null);
    props.updateField('file_name', '');

  };
  const onUpload = (e) => {
    const { files } = e.target;
    props.updateField('file', files[0]);
    props.updateField('file_name', files[0].name);
  };
    return (
    <Form horizontal>
       <FormGroup validationState={fileError === null ? null : 'error'}>
            <Col sm={3} componentClass={ControlLabel}>Select File</Col>
            <Col sm={9}>
              <div style={{ paddingTop: 5 }} >
                { isFileSelected ? (
                  <p style={{ margin: 0 }}>
                    {item.get('file_name', '')}
                    <Button
                      bsStyle="link"
                      title="Remove file"
                      onClick={onFileReset}
                      style={{ padding: '0 0 0 10px', marginBottom: 1 }}
                    >
                      <i className="fa fa-minus-circle danger-red" />
                    </Button>
                  </p>
                ) : (
                  <input
                    type="file"
                    onChange={onUpload}
                    onClick={onFileReset}
                  />
                )}
                {fileError !== null && <HelpBlock>{fileError}.</HelpBlock>}
              </div>
            </Col>
          </FormGroup>
    </Form>
  );
}

UploadTransactionsFile.propTypes = {
  onChange: PropTypes.func.isRequired,
};

UploadTransactionsFile.defaultProps = {
};


export default UploadTransactionsFile;