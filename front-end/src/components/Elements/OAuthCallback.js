import React, { useEffect } from 'react';

const OAuthCallback = () => {
  useEffect(() => {
    // Parse Parameters (Supports standard URL or Hash Routing)
    let searchString = window.location.search;
    if (!searchString && window.location.hash.includes('?')) {
      searchString = window.location.hash.split('?')[1];
    }

    const params = new URLSearchParams(searchString);
    const refreshToken = params.get('refresh_token');
    const refreshDate = params.get('refresh_date');
    const state = params.get('state');

    if (window.opener) {
      window.opener.postMessage({
        type: 'OAUTH_TOKEN_RECEIVED',
        refresh_token: refreshToken,
        refresh_date: refreshDate,
        state: state
      }, "*");

      // Small delay ensures the message is sent before the window dies
      setTimeout(() => {
        window.close();
      }, 500);
    }
  }, []);

  return (
    <div style={{ padding: 40, textAlign: 'center' }}>
      <h3>Processing...</h3>
    </div>
  );
};

export default OAuthCallback;