import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Table } from 'react-bootstrap';
import Plugin from './PluginContainer';

const Plugins = ({ data, onChange }) => (
  <div className="plugins List row panel-body">
    <Table className="table table-hover table-striped table-bordered">
      <thead>
        <tr>
          <th className="state">Status</th>
          <th>Label</th>
          <th>&nbsp;</th>
        </tr>
      </thead>
      <tbody>
        { data.map((plugin, index) => (
          <Plugin
            key={`plugin_${index}`}
            index={index}
            plugin={plugin}
            onChange={onChange}
            plugins={data}
          />
        )) }
      </tbody>
    </Table>
  </div>
);

Plugins.propTypes = {
  data: PropTypes.instanceOf(Immutable.List),
  onChange: PropTypes.func.isRequired,
};

Plugins.defaultProps = {
  data: Immutable.List(),
};

export default Plugins;
