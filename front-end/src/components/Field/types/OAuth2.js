import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Button, Modal, FormGroup, ControlLabel, HelpBlock } from 'react-bootstrap';
import Field from '../Field';

const OAuth2 = ({ value = Immutable.Map(), onChange, editable = true }) => {
    const [showModal, setShowModal] = useState(false);
    const [tempValue, setTempValue] = useState(value);
    const [tokenStatus, setTokenStatus] = useState(null);
    const [requestId, setRequestId] = useState(null);

    const REDIRECT_URI = "http://localhost:5000/callback";

    useEffect(() => {
        if (!requestId) return;

        const handleMessageReceived = (event) => {
            const { type, refresh_token, state } = event.data;

            if (state !== requestId) return;

            if (type === 'OAUTH_TOKEN_RECEIVED') {
                setTempValue(prev => prev.set('refresh_token', refresh_token));
                setTokenStatus('success');
                setRequestId(null);
            }
        };

        window.addEventListener('message', handleMessageReceived);
        return () => window.removeEventListener('message', handleMessageReceived);
    }, [requestId]);

    const openModal = () => {
        const val = Immutable.Map.isMap(value) ? value : Immutable.fromJS(value || {});
        setTempValue(val);
        setTokenStatus(null);
        setShowModal(true);
    };

    const closeModal = () => setShowModal(false);

    const saveChanges = () => {
        const fakeEvent = { target: { value: tempValue } };
        onChange(fakeEvent);
        closeModal();
    };

    const onFieldChange = (key, e) => {
        const val = (e && e.target) ? e.target.value : e;
        setTempValue(prev => prev.set(key, val));
    };

    const handleGetToken = () => {
        const authUrl = tempValue.get('auth_url', '').trim();
        const clientId = tempValue.get('client_id', '').trim();
        const configPath = tempValue.get('config_path', '').trim();
        const scope = tempValue.get('scope', '').trim();
        let extraParams = tempValue.get('extra_params');
        extraParams = extraParams.toJS();


        if (!clientId || !authUrl || !configPath) {
            alert("Error: Auth URL, Client ID, and Config Key are required.");
            return;
        }

        setTokenStatus('loading');
        setRequestId(configPath);

        let url = `${authUrl}?response_type=code&client_id=${clientId}&redirect_uri=${REDIRECT_URI}&scope=${encodeURIComponent(scope)}&state=${configPath}`;

        if (extraParams && typeof extraParams === 'object') {
            Object.keys(extraParams).forEach(key => {
                const val = extraParams[key];
                url += `&${key}=${encodeURIComponent(val)}`;
            });
        }

        window.open(url, 'OAuthPopup', "width=600,height=700,left=200,top=200");
    };

    return (
        <div className="oauth2-field">
            <Button onClick={openModal} disabled={!editable}>
                <i className="fa fa-key" /> Configure OAuth2
            </Button>

            <Modal show={showModal} onHide={closeModal} bsSize="large">
                <Modal.Header closeButton>
                    <Modal.Title>OAuth 2.0 Connection</Modal.Title>
                </Modal.Header>
                <Modal.Body>

                    <FormGroup>
                        <ControlLabel>Auth URL</ControlLabel>
                        <Field
                            value={tempValue.get('auth_url', '')}
                            onChange={(e) => onFieldChange('auth_url', e)}
                        />
                    </FormGroup>

                    <FormGroup>
                        <ControlLabel>Token URL</ControlLabel>
                        <Field
                            value={tempValue.get('token_url', '')}
                            onChange={(e) => onFieldChange('token_url', e)}
                        />
                    </FormGroup>

                    <FormGroup>
                        <ControlLabel>Client ID</ControlLabel>
                        <Field
                            value={tempValue.get('client_id', '')}
                            onChange={(e) => onFieldChange('client_id', e)}
                        />
                    </FormGroup>

                    <FormGroup>
                        <ControlLabel>Client Secret</ControlLabel>
                        <Field
                            fieldType="password"
                            value={tempValue.get('client_secret', '')}
                            onChange={(e) => onFieldChange('client_secret', e)}
                        />
                    </FormGroup>

                    <FormGroup validationState={!tempValue.get('config_path') ? 'error' : null}>
                        <ControlLabel>Database Config Key</ControlLabel>
                        <Field
                            value={tempValue.get('config_path', '')}
                            onChange={(e) => onFieldChange('config_path', e)}
                        />
                    </FormGroup>

                    <FormGroup>
                        <ControlLabel>Scope</ControlLabel>
                        <Field
                            value={tempValue.get('scope', '')}
                            onChange={(e) => onFieldChange('scope', e)}
                        />
                    </FormGroup>

                    <FormGroup>
                        <ControlLabel>Extra URL Parameters (JSON)</ControlLabel>
                        <Field
                            fieldType="json"
                            value={tempValue.get('extra_params') || {}}
                            onChange={(val) => onFieldChange('extra_params', val)}
                            editable={true}
                        />
                    </FormGroup>
                    <hr />

                    <FormGroup>
                        <ControlLabel>Refresh Token</ControlLabel>
                        <div style={{ display: 'flex', gap: '10px', alignItems: 'flex-start' }}>
                            <div style={{ flexGrow: 1 }}>
                                <Field
                                    value={tempValue.get('refresh_token', '')}
                                    disabled={true}
                                    placeholder="No token present"
                                />
                            </div>
                            <Button
                                bsStyle={tokenStatus === 'success' ? "success" : "primary"}
                                onClick={handleGetToken}
                                disabled={tokenStatus === 'loading'}
                            >
                                {tokenStatus === 'loading' ? 'Wait...' : (tokenStatus === 'success' ? 'Success!' : 'Get Token')}
                            </Button>
                        </div>
                    </FormGroup>

                </Modal.Body>
                <Modal.Footer>
                    <Button onClick={closeModal}>Cancel</Button>
                    <Button bsStyle="primary" onClick={saveChanges}>Save</Button>
                </Modal.Footer>
            </Modal>
        </div>
    );
};

OAuth2.propTypes = {
    value: PropTypes.instanceOf(Immutable.Map),
    onChange: PropTypes.func.isRequired,
    editable: PropTypes.bool,
};

export default OAuth2;