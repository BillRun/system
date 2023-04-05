import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Table } from 'react-bootstrap';
import Play from './PlayContainer';
import { CreateButton } from '@/components/Elements';


const Plays = ({ data, onChange, onRemove, onAdd }) => (
  <div className="Plays List row panel-body">
    <Table className="table table-hover table-striped table-bordered">
      <thead>
        <tr>
          <th className="state">Status</th>
          <th>Name</th>
          <th>Label</th>
          <th className="text-center list-status-col">Default</th>
          <th>&nbsp;</th>
        </tr>
      </thead>
      <tbody>
        { data.map((play, index) => (
          <Play
            key={`play_${index}`}
            index={index}
            play={play}
            onChange={onChange}
            onRemove={onRemove}
            plays={data}
          />
        )) }
      </tbody>
    </Table>
    <CreateButton onClick={onAdd} type="Play" action="Add" />
  </div>
);

Plays.propTypes = {
  data: PropTypes.instanceOf(Immutable.List),
  onChange: PropTypes.func.isRequired,
  onRemove: PropTypes.func.isRequired,
  onAdd: PropTypes.func.isRequired,
};

Plays.defaultProps = {
  data: Immutable.List(),
};

export default Plays;
