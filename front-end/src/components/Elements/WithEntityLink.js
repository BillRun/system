import React from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router';
import Immutable from 'immutable';
import { getItemId, getConfig } from '@/common/Util';


const WithEntityLink = (props) => {
  const { itemName, item, type } = props;
  if (!itemName.length || ['subscription'].includes(itemName)) {
    return props.children;
  }

  const itemType = getConfig(['systemItems', itemName, 'itemType'], '');
  const itemsType = getConfig(['systemItems', itemName, 'itemsType'], '');
  switch (type) {
    case 'list': {
      const revisionUrl = `/${itemsType}`;
      return (<Link to={revisionUrl}>{props.children}</Link>);
    }
    case 'edit': {
      const revisionUrl = `${itemsType}/${itemType}/${getItemId(item, '')}`;
      return (<Link to={revisionUrl}>{props.children}</Link>);
    }
    case 'clone': {
      const revisionUrl = `${itemsType}/${itemType}/${getItemId(item, '')}`;
      return (<Link to={revisionUrl} query={{ action: 'clone' }}>{props.children}</Link>);
    }
    default: return props.children;
  }
};

WithEntityLink.defaultProps = {
  children: null,
  item: Immutable.Map(),
  itemName: '',
  type: 'view', // 'list', 'edit', 'clone'
};

WithEntityLink.propTypes = {
  children: PropTypes.element,
  item: PropTypes.instanceOf(Immutable.Map),
  itemName: PropTypes.string,
  type: PropTypes.string,
};

export default WithEntityLink;
