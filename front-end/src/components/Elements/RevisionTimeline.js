import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import classNames from 'classnames';
import { StateIcon, WithEntityLink } from '@/components/Elements';
import { getItemDateValue, isItemClosed, isItemReopened, getItemId } from '@/common/Util';


const RevisionTimeline = ({ revisions, size, item, start, itemName }) => {
  const revisionsToDisplay = Immutable.List().withMutations((listWithMutations) => {
    let nextRevision = Immutable.Map();
    revisions.forEach((revision) => {
      const dummyClosedRevision = Immutable.Map({
        closed: true,
        to: revision.get('to', ''),
      });
      if (isItemClosed(revision) || isItemReopened(nextRevision, revision)) {
        listWithMutations.push(dummyClosedRevision);
      }
      listWithMutations.push(revision);
      nextRevision = revision;
    });
  }).reverse();

  const index = start !== null
    ? start
    : revisionsToDisplay.findIndex(revision => getItemId(revision) === getItemId(item));
  const revisionsFromLeft = Math.floor((size - 1) / 2);
  let from = Math.max(index - revisionsFromLeft, 0);
  let end = from + size;
  if (end >= revisionsToDisplay.size) {
    end = revisionsToDisplay.size;
    from = Math.max(end - size, 0);
  }
  const moreAfter = revisionsToDisplay.size > size && (end < revisionsToDisplay.size);
  const moreBefore = revisionsToDisplay.size > size && from > 0;

  const renderMore = type => (
    <li key={`${getItemId(item, '')}-more-${type}`} className={`more ${type}`}>
      <div style={{ height: '22px' }}>&nbsp;</div>
      <div>
        <div>&nbsp;</div>
        <div>&nbsp;</div>
      </div>
    </li>
  );

  const renderClosedRevision = (closedRevision) => {
    const to = getItemDateValue(closedRevision, 'to');
    return (
      <li key={`${getItemId(closedRevision, '')}-closed`} className="closed">
        <div>
          <div><i style={{ fontSize: 19 }} className="fa fa-times-circle" /></div>
          <small className="date">
            { to.format('MMM DD')}
            <br />
            { to.format('YYYY')}
          </small>
        </div>
      </li>
    );
  };

  const renderRevision = (revision, key, list) => {
    if (revision.get('closed', false)) {
      return renderClosedRevision(revision);
    }
    const fromDate = getItemDateValue(revision, 'from');
    const isActive = getItemId(revision, '') === getItemId(item, '');
    const activeClass = classNames('revision', {
      active: isActive,
      first: key === 0,
      last: key === (list.size - 1),
    });
    return (
      <li key={`${getItemId(revision, '')}`} className={activeClass}>
        <div>
          <div>
            { isActive ? (
              <StateIcon status={revision.getIn(['revision_info', 'status'], '')} />
            ) : (
              <WithEntityLink item={revision} itemName={itemName} type="edit">
                <StateIcon status={revision.getIn(['revision_info', 'status'], '')} />
              </WithEntityLink>
            )}
          </div>
          <small className="date">
            { fromDate.format('MMM DD')}
            <br />
            { fromDate.format('YYYY')}
          </small>
        </div>
      </li>
    );
  };

  return (
    <ul className="revision-history-list">
      { moreBefore && renderMore('before') }
      { revisionsToDisplay
        .slice(from, end)
        .map(renderRevision)
      }
      { moreAfter && renderMore('after') }
    </ul>
  );
};


RevisionTimeline.defaultProps = {
  revisions: Immutable.List(),
  item: Immutable.Map(),
  itemName: '',
  size: 5,
  start: null,
};

RevisionTimeline.propTypes = {
  revisions: PropTypes.instanceOf(Immutable.List),
  item: PropTypes.instanceOf(Immutable.Map),
  itemName: PropTypes.string,
  size: PropTypes.number,
  start: PropTypes.number,
};

export default RevisionTimeline;
