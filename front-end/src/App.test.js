import React from 'react';
import ReactDOM from 'react-dom';

it.skip('renders without crashing', () => {
  const App = require('./components/App').default; // lazy require: do not load app graph when test is skipped
  const div = document.createElement('div');
  ReactDOM.render(<App />, div);
  ReactDOM.unmountComponentAtNode(div);
});
