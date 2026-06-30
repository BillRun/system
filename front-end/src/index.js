import React, { Suspense, lazy } from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux'
import Routes from './routes';
import configureStore from './configureStore'
import * as serviceWorker from './serviceWorker';
/* Styles */
import 'react-datepicker/dist/react-datepicker.css';
import 'font-awesome/css/font-awesome.min.css';
import './styles/css/normalize.css';
import './styles/css/sb-admin-2.css';
import './styles/css/yeti.css';
import './styles/scss/index.scss';
import './styles/css/index.css';

const DevTools = lazy(() => import('./components/DevTools'));

const store = configureStore()

const container = document.getElementById('root');
const root = createRoot(container);

root.render(
  <Provider store={store}>
    <Routes />
    {process.env.NODE_ENV === "development" && (
      <Suspense fallback={<div>Loading...</div>}>
        <DevTools />
      </Suspense>
    )}
  </Provider>
);

// If you want your app to work offline and load faster, you can change
// unregister() to register() below. Note this comes with some pitfalls.
// Learn more about service workers: http://bit.ly/CRA-PWA
serviceWorker.unregister();
