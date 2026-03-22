import React, { useState } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Button, Modal, FormGroup, ControlLabel } from 'react-bootstrap';
import { apiBillRun } from '@/common/Api';
import Json from './Json';
import { showSuccess, showDanger } from '@/actions/alertsActions';
import { connect } from 'react-redux';

const ApiButton = (props) => {
    const { value: propValue, onChange, disabled, editable = true, dispatch } = props;
    const config = Immutable.Map.isMap(propValue) ? propValue.toJS() : (propValue || {});
    const buttonLabel = config.label || 'Run API';
    const modalTitle = config.modal_title || 'API Runner';
    const sendLabel = config.send_label || 'Send Request';

    const defaultTemplate = {
        api: "",
        action: "",
        params: []
    };

    const initialQuery = config.requestQuery || defaultTemplate;

    const [showModal, setShowModal] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [currentQuery, setCurrentQuery] = useState(initialQuery);
    const [response, setResponse] = useState(null);
    const [error, setError] = useState(null);

    const sendRequest = async () => {
        setIsLoading(true);
        setResponse(null);
        setError(null);

        try {
            // Internal request (Billrun backend)            
            if (currentQuery.api) {
                const internalQuery = { ...currentQuery };
                // Convet params to fit apiBillrun function
                if (internalQuery.params && !Array.isArray(internalQuery.params) && typeof internalQuery.params === 'object') {
                    internalQuery.params = Object.keys(internalQuery.params).map(key => ({
                        [key]: internalQuery.params[key]
                    }));
                }
                const success = await apiBillRun(internalQuery);
                setResponse(success.data);
                dispatch(showSuccess("Request Successful"));
            }

            else if (currentQuery.url) {
                let { url, method, headers, body, params } = currentQuery;

                // Append query params to URL
                if (params && Object.keys(params).length > 0) {
                    const queryString = Object.keys(params)
                        .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
                        .join('&');
                    const separator = url.includes('?') ? '&' : '?';
                    url = `${url}${separator}${queryString}`;
                }

                const options = {
                    method: method || 'GET',
                    headers: headers || { 'Content-Type': 'application/json' },
                };

                // Add body only for non GET requests
                if (body && ['POST', 'PUT', 'PATCH'].includes((method || 'GET').toUpperCase())) {
                    options.body = typeof body === 'object' ? JSON.stringify(body) : body;
                }

                const res = await fetch(url, options);

                const contentType = res.headers.get("content-type");
                const data = (contentType && contentType.includes("application/json"))
                    ? await res.json()
                    : await res.text();

                if (!res.ok) throw { status: res.status, statusText: res.statusText, data };
                setResponse(data);
                dispatch(showSuccess("Request Completed Successfully"));
            }

            else {
                throw new Error("Invalid JSON: Must contain 'api' (for Internal) or 'url' (for External).");
            }

        } catch (err) {
            setError(err);
            dispatch(showDanger(`Request Failed`));
        } finally {
            setIsLoading(false);
        }
    };

    const handleQueryChange = (newVisibleJson) => {
        const hiddenFields = config.hidden_fields || [];
        const hiddenData = {};
        hiddenFields.forEach(key => {
            if (currentQuery[key] !== undefined) {
                hiddenData[key] = currentQuery[key];
            }
        });
        const mergedQuery = { ...newVisibleJson, ...hiddenData };
        setCurrentQuery(mergedQuery);
        if (onChange && editable) {
            const newConfig = { ...config, requestQuery: mergedQuery };
            onChange({ target: { value: newConfig } });
        }
    };

    const getVisibleQuery = (query) => {
        const hiddenFields = config.hidden_fields || [];
        if (hiddenFields.length === 0) return query;
        const visible = { ...query };
        hiddenFields.forEach(field => delete visible[field]);
        return visible;
    };

    return (
        <div className="api-button-field">
            <Button
                bsStyle={config.bsStyle || 'primary'}
                onClick={() => setShowModal(true)}
                disabled={disabled}
            >
                {buttonLabel}
            </Button>

            <Modal show={showModal} onHide={() => setShowModal(false)}>
                <Modal.Header closeButton>
                    <Modal.Title>{modalTitle}</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    <FormGroup>
                        <ControlLabel>Request Configuration (JSON)</ControlLabel>
                        <Json
                            value={getVisibleQuery(currentQuery)}
                            onChange={handleQueryChange}
                            viewOnly={!editable}
                        />
                    </FormGroup>

                    <div style={{ textAlign: 'center' }}>
                        <Button
                            bsStyle="success"
                            onClick={sendRequest}
                            disabled={isLoading}
                            style={{ minWidth: '200px' }}
                        >
                            {isLoading ? 'Loading...' : sendLabel}
                        </Button>
                    </div>

                    <FormGroup>
                        <ControlLabel>Response</ControlLabel>
                        <Json
                            value={response || error || {}}
                            editable={false}
                        />
                    </FormGroup>

                </Modal.Body>
                <Modal.Footer>
                    <Button onClick={() => setShowModal(false)}>Close</Button>
                </Modal.Footer>
            </Modal>
        </div>
    );
};

ApiButton.propTypes = {
    value: PropTypes.oneOfType([PropTypes.object, PropTypes.instanceOf(Immutable.Map)]),
    onChange: PropTypes.func,
    disabled: PropTypes.bool,
    editable: PropTypes.bool,
};

ApiButton.defaultProps = {
    value: {},
    onChange: () => { },
    disabled: false,
    editable: true,
};

export default connect()(ApiButton);