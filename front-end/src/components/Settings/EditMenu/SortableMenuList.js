import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Col } from 'react-bootstrap';
import { SortableContainer } from 'react-sortable-hoc';

const SortableMenuList = ({ data: { items, renderMenu, path } }) => (
  <Col lg={12} md={12} className="pr0">
    { items.map((item, i) => renderMenu(item, i, path)) }
  </Col>
);

SortableMenuList.propTypes = {
  data: PropTypes.instanceOf(Immutable.Record).isRequired,
};

export default SortableContainer(SortableMenuList);
