import React from 'react';
import PropTypes from 'prop-types';

const Pager = (props) => {
  const { size, count, page, nextPage } = props;
  const offset = page * size;
  const prevClass = `previous${page === 0 ? ' disabled' : ''}`;
  const nextClass = `next${!nextPage ? ' disabled ' : ''}`;
  const showing = count === 0 ? 0 : `${offset + 1} to ${offset + count}`;
  const pageLabel = `Page ${page + 1}`;
  const sizeStyle = { paddingTop: 0, paddingBottom: 0, height: 25 };

  const onChangeSize = (e) => {
    const { value } = e.target;
    props.onChangeSize(value);
  };

  const handlePrevPage = (e) => {
    e.preventDefault();
    if (page > 0) {
      props.onChangePage(page - 1);
    }
  };

  const handleNextPageClick = (e) => {
    e.preventDefault();
    if (nextPage) {
      props.onChangePage(page + 1);
    }
  };

  return (
    <div className="col-12">
      <div className="col-6 pull-left">
        <ul className="pagination">
          <li className={prevClass}>
            <a onClick={handlePrevPage}>{/* eslint-disable-line jsx-a11y/anchor-is-valid */}
              <i className="fa fa-chevron-left" />
            </a>
          </li>
          <span className="detalis" style={{ padding: '0 10px' }}>{pageLabel}{showing !== 0 && ` | ${showing}`}</span>
          <li className={nextClass}>
            <a onClick={handleNextPageClick}>{/* eslint-disable-line jsx-a11y/anchor-is-valid */}
              <i className="fa fa-chevron-right" />
            </a>
          </li>
        </ul>
      </div>
      <div className="col-6 pull-right">
        { props.onChangeSize &&
          <select value={size} className="form-control" onChange={onChangeSize} style={sizeStyle}>
            <option value={5}>5</option>
            <option value={10}>10</option>
            <option value={15}>15</option>
            <option value={20}>20</option>
          </select>
        }
      </div>
    </div>
  );
};

Pager.propTypes = {
  page: PropTypes.number,
  nextPage: PropTypes.bool,
  size: PropTypes.number.isRequired,
  count: PropTypes.number.isRequired,
  onChangePage: PropTypes.func.isRequired,
  onChangeSize: PropTypes.func,
};

Pager.defaultProps = {
  page: 0,
  nextPage: false,
};

export default Pager;
