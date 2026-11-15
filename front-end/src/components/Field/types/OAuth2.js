import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';
import { Button, Modal, FormGroup } from 'react-bootstrap';
import { ControlLabel } from '@/common/BootstrapCompat';
import Field from '../Field';
import { apiBillRun } from '../../../common/Api';
import { saveOAuth2SettingsQuery } from '../../../common/ApiQueries';
import { showSuccess, showDanger } from '../../../actions/alertsActions';

const OAuth2 = (props) => {
    const { value: propValue, onChange, editable = true, dispatch, configPath = '' } = props;
    const value = Immutable.Map.isMap(propValue) ? propValue : Immutable.fromJS(propValue || {});
    const [showModal, setShowModal] = useState(false);
    const [tempValue, setTempValue] = useState(value);
    const [tokenStatus, setTokenStatus] = useState(null);
    const [requestId, setRequestId] = useState(null);
    const REDIRECT_URI = `${window.location.origin}/api/oauth2refresh`;
    const isDirty = !Immutable.is((value || Immutable.Map()).delete('config_path'), tempValue.delete('config_path'));

    useEffect(() => {
        if (!requestId) return;
        const handleMessageReceived = (event) => {
            const { type, refresh_token, refresh_date, state } = event.data;
            if (state !== requestId) return;
            if (type === 'OAUTH_TOKEN_RECEIVED') {
                setTempValue(prev => prev
                    .set('refresh_token', refresh_token)
                    .set('refresh_date', refresh_date)
                );
                setTokenStatus('success');
                setRequestId(null);
            }
        };
        window.addEventListener('message', handleMessageReceived);
        return () => window.removeEventListener('message', handleMessageReceived);
    }, [requestId]);

    const openModal = () => {
        let val = Immutable.Map.isMap(value) ? value : Immutable.fromJS(value || {});
        val = val.set('config_path', configPath || val.get('config_path', ''));
        setTempValue(val);
        setTokenStatus(null);
        setShowModal(true);
    };

    const closeModal = () => setShowModal(false);

    const saveChanges = () => {
        const settingsData = tempValue.toJS();

        if (!settingsData.config_path) {
            dispatch(showDanger('OAuth2 field is missing a config path. Cannot save.'));
            return;
        }

        const query = saveOAuth2SettingsQuery(settingsData);

        apiBillRun(query).then(
            (success) => {
                dispatch(showSuccess('OAuth settings saved successfully!'));
                const fakeEvent = { target: { value: tempValue } };
                onChange(fakeEvent);
                closeModal();
            },
            (failure) => {
                console.log('Save failed:', failure);
                dispatch(showDanger('Error saving OAuth settings'));
            }
        ).catch(
            (error) => {
                console.error('Network Error:', error);
                dispatch(showDanger('Network error - please try again'));
            }
        );
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
        extraParams = (extraParams && typeof extraParams.toJS === 'function')
            ? extraParams.toJS()
            : (extraParams || {});

        if (!clientId || !authUrl || !configPath) {
            dispatch(showDanger("Error: Auth URL, Client ID, and config path are required."));
            return;
        }
        const nonce = crypto.randomUUID();
        const state = `${configPath}|${nonce}`;
        setTokenStatus('loading');
        setRequestId(state);
        let url = `${authUrl}?response_type=code&client_id=${clientId}&redirect_uri=${REDIRECT_URI}&scope=${encodeURIComponent(scope)}&state=${encodeURIComponent(state)}`;

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
                    {isDirty && (
                        <div className="alert alert-warning" style={{ marginBottom: '15px', padding: '10px' }}>
                            <i className="fa fa-exclamation-triangle" /> You have unsaved changes
                        </div>
                    )}
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

                    <FormGroup>
                        <ControlLabel>Scope (Seperate by spaces)</ControlLabel>
                        <Field
                            value={tempValue.get('scope', '')}
                            onChange={(e) => onFieldChange('scope', e)}
                        />
                    </FormGroup>

                    <FormGroup>
                        <ControlLabel>Extra URL Parameters</ControlLabel>
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
                                {tempValue.get('refresh_date') && (
                                    <span className="help-block">
                                        Last refresh: {tempValue.get('refresh_date')}
                                    </span>
                                )}
                            </div>
                            <Button
                                bsStyle={tokenStatus === 'success' ? "success" : "primary"}
                                onClick={handleGetToken}
                                disabled={tokenStatus === 'loading' || isDirty}
                                title={isDirty ? "Save changes first" : ""}
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
    configPath: PropTypes.string,
    dispatch: PropTypes.func.isRequired,
};

export default connect()(OAuth2);