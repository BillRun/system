import React from 'react';
// Exported from redux-devtools
import { createDevTools } from 'redux-devtools';
// Monitors are separate packages, and you can make a custom one
import LogMonitor from 'redux-devtools-log-monitor';
import DockMonitor from 'redux-devtools-dock-monitor';
import FilterMonitor from 'redux-devtools-filter-actions';


// Stripping big data which slows down DevTools Monitor
const actionsFilter = (action) => {
  // cut base64 image string
  if (action.type === 'UPDATE_SETTING' && action.category === 'files' && action.value.length > 50) {
    return { ...action, value: `${action.value.substring(0, 20)}...` };
  }
  return action;
};


// createDevTools takes a monitor and produces a DevTools component
const DevTools = createDevTools(
  // Monitors are individually adjustable with props.
  // Consult their repositories to learn about those props.
  // Here, we put LogMonitor inside a DockMonitor.
  // Note: DockMonitor is visible by default.
  <DockMonitor
    toggleVisibilityKey="ctrl-h"
    changePositionKey="ctrl-q"
    defaultIsVisible={false}
    defaultPosition="left"
  >
    <FilterMonitor actionsFilter={actionsFilter} >
      <LogMonitor theme="tomorrow" />
    </FilterMonitor>
  </DockMonitor>
);

export default DevTools;
