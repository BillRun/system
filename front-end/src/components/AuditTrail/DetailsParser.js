import React, { useState } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Button } from 'react-bootstrap';
import { DiffModal } from '@/components/Elements';


const DetailsParser = ({ item }) => {

  const [showDiff, toggleDiff] = useState(false);

  const getActionLabel = (action) => {
    switch (action) {
      case 'closeandnew':
        return 'New revision';
      case 'update':
        return 'Updated';
      case 'delete':
        return 'Deleted';
      case 'create':
        return 'Created';
      case 'close':
        return 'Closed';
      case 'move':
        return 'Moved';
      case 'permanentchange':
        return 'Updated';
      case 'reopen':
        return 'Reopened';
      default:
        return '';
    }
  }

  const closeDiff = () => {
    toggleDiff(false);
  }

  const openDiff = () => {
    toggleDiff(true);
  }

  const renderDiff = () => {
    const dataNew = item.get('new', null);
    const dataOld = item.get('old', null);
    const itemNew = Immutable.Map.isMap(dataNew) ? dataNew.delete('_id').toJS() : '';
    const itemOld = Immutable.Map.isMap(dataOld) ? dataOld.delete('_id').toJS() : '';
    return (
      <DiffModal show={showDiff} onClose={closeDiff} inputNew={itemNew} inputOld={itemOld} />
    );
  }

  return (
    <div>
      {item.get('details', '') !== '' && (
        <p>{item.get('details', '')}</p>
      )}
      {['login'].includes(item.get('type', '')) && (
        <p>IP: {item.get('ip', '')}</p>
      )}
      {!['login'].includes(item.get('type', '')) && (
        <p>
          {getActionLabel(item.get('type', ''))}
          &nbsp;
          <Button bsStyle="link" onClick={openDiff} style={{ verticalAlign: 'bottom' }}>
            <i className="fa fa-compress" />
            &nbsp;
            {item.get('new', null) && item.get('old', null) ? 'Compare' : 'Details'}
          </Button>
          {renderDiff()}
        </p>
      )}
    </div>
  );
}

DetailsParser.defaultProps = {
  item: Immutable.Map(),
};

DetailsParser.propTypes = {
  item: PropTypes.instanceOf(Immutable.Map),
};

export default DetailsParser;
