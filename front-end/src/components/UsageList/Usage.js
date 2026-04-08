import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import moment from 'moment';
import { Form, Col, Row, Button } from 'react-bootstrap';
import { ControlLabel, FormGroup, Panel } from '@/common/BootstrapCompat';
import changeCase from 'change-case';
import { getFieldName, getConfig } from '@/common/Util';

const Usage = ({
  line = Immutable.Map(), onClickCancel = () => {}, hiddenFields = ['_id', 'in_plan', 'over_plan', 'interconnect_aprice', 'out_plan', 'uf'], cancelLabel = 'Back', enableRemove = false, onClickRemove = () => {}, removeLabel = 'Remove',
}) => {
  const renderMainPanelTitle = () => (
    <div>{line.get('type', '')}
      <div className="pull-right">
        <Button className="btn-xs" variant="outline-secondary" onClick={onClickCancel}>{cancelLabel}</Button>
      </div>
    </div>
  );

  const renderRemove = () => (
    enableRemove &&
    <Panel className="panel-no-border">
      <Button onClick={onClickRemove} variant="outline-secondary" className="pull-right btn-xs" ><i className="fa fa-trash-o danger-red" />&nbsp;{removeLabel}</Button>
    </Panel>
  );

  const renderFields = (data, fieldsOrigin) => {
    const fields = [];
    data.forEach((value, key) => {
      let formattedValue = value;
      if (fieldsOrigin === 'billrunFields') {
        if (key === 'connection_type') {
          formattedValue = changeCase.upperCaseFirst(value);
        } else if (key === 'urt' || key === 'process_time' || key === 'rebalance') {
          formattedValue = moment.unix(value.get('sec')).format(getConfig('datetimeLongFormat', 'DD/MM/YYYY HH:mm:ss'));
        }
      }
      if (!hiddenFields.includes(key)) {
        fields.push(
          <FormGroup key={key}>
            <Col as={ControlLabel} sm={3} lg={2}>{ getFieldName(key, 'lines') }</Col>
            <Col sm={8} lg={9}>
              <input disabled className="form-control" value={(formattedValue === null) ? '' : formattedValue} />
            </Col>
          </FormGroup>
        );
      }
    });
    return fields;
  };

  const billrunFields = renderFields(line, 'billrunFields');
  const userFields = renderFields(line.get('uf', Immutable.Map()), 'userFields');

  return (
    <Row>
      <Col lg={12}>
        <Form>
          <Panel header={renderMainPanelTitle()}>
            { renderRemove() }
            <Panel header={<h3>BillRun fields</h3>}>
              { billrunFields }
            </Panel>
            {userFields.length > 0 ? (
              <Panel header={<h3>User fields</h3>}>
                { userFields }
              </Panel>
            ) : (
              <div className="panel panel-default">
                <div className="panel-heading">
                  <h3 className="panel-title">User fields</h3>
                </div>
              </div>
            )}
            <Button variant="outline-secondary" onClick={onClickCancel}>{cancelLabel}</Button>
          </Panel>
        </Form>
      </Col>
    </Row>
  );
};

Usage.propTypes = {
  line: PropTypes.instanceOf(Immutable.Map),
  hiddenFields: PropTypes.arrayOf(PropTypes.string),
  onClickCancel: PropTypes.func,
  cancelLabel: PropTypes.string,
  enableRemove: PropTypes.bool,
  onClickRemove: PropTypes.func,
  removeLabel: PropTypes.string,
};

export default Usage;
