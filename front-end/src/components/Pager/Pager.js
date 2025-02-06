import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { connect } from 'react-redux';
import Immutable from 'immutable';


class Pager extends Component {

  static propTypes = {
    pager: PropTypes.instanceOf(Immutable.Map),
    size: PropTypes.number,
    count: PropTypes.number,
    onClick: PropTypes.func,
  }

  state = {
    page: 0,
  }

  handlePageClick = (e) => {
    e.preventDefault();
    const { pager } = this.props;
    const { id } = e.target;
    let { page } = this.state;

    if (id === 'next' && pager.get('nextPage', true)) {
      page += 1;
    } else if (id === 'previous' && page > 0) {
      page -= 1;
    } else {
      return;
    }
    this.setState({ page });
    this.props.onClick(page);
  }

  render() {
    const { size, count, pager } = this.props;
    const { page } = this.state;
    const offset = page * size;
    const prevClass = `previous${this.state.page === 0 ? ' disabled' : ''}`;
    const nextClass = `next${!pager.get('nextPage') ? ' disabled ' : ''}`;
    const showing = count === 0 ? 0 : `${offset + 1} to ${offset + count}`;
    const pageLabel = `Page ${page + 1}`;

    return (
      <div className="row">
        <div className="col-lg-12">
          <ul className="pagination">
            <li id="previous" className={prevClass}>
              <a id="previous" onClick={this.handlePageClick}>{/* eslint-disable-line jsx-a11y/anchor-is-valid */}
                <i id="previous" className="fa fa-chevron-left" />
              </a>
            </li>
            <span className="detalis" style={{ padding: '0 10px' }}>{pageLabel}{showing !== 0 && ` | ${showing}`}</span>
            <li id="next" className={nextClass}>
              <a id="next" onClick={this.handlePageClick}>{/* eslint-disable-line jsx-a11y/anchor-is-valid */}
                <i id="next" className="fa fa-chevron-right" />
              </a>
            </li>
          </ul>
        </div>
      </div>
    );
  }
}

const mapStateToProps = state => ({
  pager: state.pager,
});

export default connect(mapStateToProps)(Pager);
