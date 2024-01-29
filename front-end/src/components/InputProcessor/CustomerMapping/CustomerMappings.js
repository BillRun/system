import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Form, Button } from 'react-bootstrap';
import classNames from 'classnames';
import CustomerMapping from './CustomerMapping';
import { addCustomerMapping, removeCustomerMapping } from '@/actions/inputProcessorActions';

class CustomerMappings extends Component {
  static propTypes = {
    dispatch: PropTypes.func.isRequired,
    settings: PropTypes.instanceOf(Immutable.Map),
    onSetCustomerMapping: PropTypes.func.isRequired,
    subscriberFields: PropTypes.instanceOf(Immutable.List),
  }

  static defaultProps = {
    settings: Immutable.Map(),
    subscriberFields: Immutable.List(),
  };

  onAddCustomerMapping = usaget => () => {
    this.props.dispatch(addCustomerMapping(usaget));
  }

  onRemoveCustomerMapping = (usaget, priority) => () => {
    this.props.dispatch(removeCustomerMapping(usaget, priority));
  }

  renderAddCustomerMappingButton = usaget => (
    <Button
      bsSize="xsmall"
      className="btn-primary"
      onClick={this.onAddCustomerMapping(usaget)}
    >
      <i className="fa fa-plus" />&nbsp;Add
    </Button>
  );

  renderRemoveCustomerMappingButton = (usaget, priority) => (
    <Button
      bsStyle="link"
      bsSize="xsmall"
      onClick={this.onRemoveCustomerMapping(usaget, priority)}
    >
      <i className="fa fa-fw fa-trash-o danger-red" />
    </Button>
  );

  render() {
    const { settings, subscriberFields } = this.props;
    const customerMappings = settings.get('customer_identification_fields', Immutable.Map());
    return (
      <Form horizontal className="customerMappings">
        <div className="form-group">
          <div className="col-lg-12">
            <h4>
              Customer identification
              <small> | Map customer identification field in record to BillRun field</small>
            </h4>
          </div>
        </div>
        {customerMappings.map((mappings, usaget) => (
          <div key={`customer-mapping-${usaget}`}>
            <div className="form-group">
              <div className="col-lg-3">
                <label htmlFor={usaget}>{ usaget }</label>
              </div>
              <div className="col-lg-9">
                <div className="col-lg-1" style={{ marginTop: 8 }}>
                  <i className="fa fa-long-arrow-right" />
                </div>
                {mappings.map((mapping, priority) => {
                  const lineClass = classNames('form-inner-edit-row', 'col-lg-9', {
                    'col-lg-offset-1': priority > 0,
                  });
                  return (
                    <div className={lineClass} key={`customer-mapping-${usaget}-${priority}`}>
                      <div className="row">
                        <div className="col-lg-11">
                          <CustomerMapping
                            usaget={usaget}
                            mapping={mapping}
                            priority={priority}
                            onSetCustomerMapping={this.props.onSetCustomerMapping}
                            subscriberFields={subscriberFields}
                            settings={settings}
                          />
                        </div>
                        <div className="col-lg-1">
                          {
                            priority > 0 &&
                            this.renderRemoveCustomerMappingButton(usaget, priority)
                          }
                        </div>
                      </div>
                    </div>
                  );
                })
                .toList()
                .toArray()
                }
                <div className="col-lg-9 col-lg-offset-1">
                  <div className="row">
                    <div className="col-lg-11">
                      <div className="col-lg-4">
                        { this.renderAddCustomerMappingButton(usaget) }
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        ))
        .toList()
        .toArray()
      }
      </Form>
    );
  }
}

export default connect()(CustomerMappings);
