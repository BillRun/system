import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Col } from 'react-bootstrap';
import { SortableFieldsContainer } from '@/components/Elements';

const SortableMenuList = ({ data: { items, renderMenu, path }, onSortEnd }) => (
  <Col lg={12} md={12} className="pr0">
    <SortableFieldsContainer
      collection={path.join('-')}
      items={items.map((item, i) => renderMenu(item, i, path)).toArray()}
      onSortEnd={onSortEnd}
    />
  </Col>
);

SortableMenuList.propTypes = {
  data: PropTypes.instanceOf(Immutable.Record).isRequired,
  onSortEnd: PropTypes.func,
};

SortableMenuList.defaultProps = {
  onSortEnd: () => {},
};

export default SortableMenuList;
