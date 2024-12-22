import React from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { Col } from 'react-bootstrap';
import { SortableElement } from 'react-sortable-hoc';
import EditMenuItem from './EditMenuItem';
import { DragHandle } from '@/components/Elements';

const SortableMenuItem = (props) => {
  const { item, onChangeField, subMenus, renderTree, newPath, editShow } = props.data;
  return (
    <Col md={12} className="pr0" style={{ borderTop: '1px solid #eee', borderBottom: '1px solid #eee', lineHeight: '35px' }} >
      <Col md={1}>
        <DragHandle />
      </Col>
      <Col md={11} className="pr0">
        <EditMenuItem item={item} onChangeField={onChangeField} editShow={editShow} />
        {subMenus.size > 0 && renderTree(subMenus, [...newPath, 'subMenus'], item.get('id'))}
      </Col>
    </Col>
  );
};

SortableMenuItem.propTypes = {
  data: PropTypes.instanceOf(Immutable.Record).isRequired,
};

export default SortableElement(SortableMenuItem);
