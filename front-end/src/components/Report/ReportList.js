import React, { Component } from 'react';
import PropTypes from 'prop-types';
import Immutable from 'immutable';
import { HelpBlock } from 'react-bootstrap';
import List from '../List';
import Pager from '../EntityList/Pager';
import { ReportDescription } from '../../language/FieldDescriptions';
import { getConfig } from '@/common/Util';


class ReportList extends Component {

  static propTypes = {
    items: PropTypes.instanceOf(Immutable.List),
    fields: PropTypes.instanceOf(Immutable.List),
    size: PropTypes.number,
    page: PropTypes.number,
    nextPage: PropTypes.bool,
    onlyHeaders: PropTypes.bool,
    onChangePage: PropTypes.func,
    onChangeSize: PropTypes.func,
  }

  static defaultProps = {
    items: Immutable.List(),
    fields: Immutable.List(),
    size: getConfig('listDefaultItems', 10),
    page: 0,
    nextPage: false,
    onlyHeaders: false,
    onChangePage: () => {},
    onChangeSize: () => {},
  }

  shouldComponentUpdate(nextProps) {
    const { items, fields, page, nextPage, size, onlyHeaders } = this.props;
    return (
      !Immutable.is(items, nextProps.items)
      || !Immutable.is(fields, nextProps.fields)
      || size !== nextProps.size
      || page !== nextProps.page
      || nextPage !== nextProps.nextPage
      || onlyHeaders !== nextProps.onlyHeaders
    );
  }

  render() {
    const { items, size, page, nextPage, fields, onlyHeaders } = this.props;
    return (
      <div className="report-list">
        <List
          items={onlyHeaders ? null : items}
          fields={fields.toJS()}
        />
        {!onlyHeaders && (
          <Pager
            page={page}
            size={size}
            count={items.size}
            nextPage={nextPage}
            onChangePage={this.props.onChangePage}
            onChangeSize={this.props.onChangeSize}
          />
        )}
        {onlyHeaders && (<HelpBlock>{ReportDescription.block_preview}</HelpBlock>)}
      </div>
    );
  }

}

export default ReportList;
