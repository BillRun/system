webpackHotUpdate(0,{

/***/ 2580:
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});

	var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

	var _react = __webpack_require__(1);

	var _react2 = _interopRequireDefault(_react);

	var _reactRedux = __webpack_require__(179);

	var _immutable = __webpack_require__(271);

	var _immutable2 = _interopRequireDefault(_immutable);

	var _reactBootstrap = __webpack_require__(837);

	var _reactSelect = __webpack_require__(1093);

	var _reactSelect2 = _interopRequireDefault(_reactSelect);

	var _changeCase = __webpack_require__(407);

	var _Help = __webpack_require__(2491);

	var _Help2 = _interopRequireDefault(_Help);

	var _Field = __webpack_require__(1091);

	var _Field2 = _interopRequireDefault(_Field);

	var _Util = __webpack_require__(288);

	var _settingsActions = __webpack_require__(457);

	var _settingsSelector = __webpack_require__(451);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

	function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

	function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

	var ComputedRate = function (_Component) {
	  _inherits(ComputedRate, _Component);

	  function ComputedRate() {
	    var _ref;

	    var _temp, _this, _ret;

	    _classCallCheck(this, ComputedRate);

	    for (var _len = arguments.length, args = Array(_len), _key = 0; _key < _len; _key++) {
	      args[_key] = arguments[_key];
	    }

	    return _ret = (_temp = (_this = _possibleConstructorReturn(this, (_ref = ComputedRate.__proto__ || Object.getPrototypeOf(ComputedRate)).call.apply(_ref, [this].concat(args))), _this), _this.getConditionResultProjectOptions = function () {
	      return ['condition_result', 'hard_coded'].map(_Util.formatSelectOptions);
	    }, _this.getRateConditions = function () {
	      return (0, _Util.getConfig)(['rates', 'conditions'], _immutable2.default.Map()).map(function (condType) {
	        return {
	          value: condType.get('key', ''),
	          label: condType.get('title', '')
	        };
	      }).toArray();
	    }, _this.onChangeComputedLineKeyHardCodedKey = function (e) {
	      var value = e.target.value;

	      var callback = _this.props.onChangeComputedLineKey(['line_keys', 1, 'key']);
	      callback(value);
	    }, _temp), _possibleConstructorReturn(_this, _ret);
	  }

	  _createClass(ComputedRate, [{
	    key: 'componentWillMount',
	    value: function componentWillMount() {
	      this.props.dispatch((0, _settingsActions.getSettings)(['lines']));
	    }
	  }, {
	    key: 'render',
	    value: function render() {
	      var _props = this.props,
	          computedLineKey = _props.computedLineKey,
	          settings = _props.settings,
	          foreignFields = _props.foreignFields;

	      if (!computedLineKey) {
	        return null;
	      }
	      var regexHelper = 'In case you want to run a regular expression on the computed field before calculating the rate';
	      var mustMetHelper = 'This means than in case the condition is not met - a rate will not be found';
	      var additionalFields = foreignFields.filter(function (field) {
	        return field.get('available_from', '') === 'rate';
	      }).map(function (filteredField) {
	        var fieldName = filteredField.get('field_name', '');
	        var label = filteredField.get('title', (0, _changeCase.titleCase)(fieldName));
	        return { value: fieldName, label: label + ' (foreign field)' };
	      }).toArray().concat([{ value: 'type', label: 'Type' }, { value: 'usaget', label: 'Usage Type' }, { value: 'file', label: 'File name' }]);
	      var lineKeyOptions = (0, _Util.getAvailableFields)(settings, additionalFields).toJS();
	      var computedTypeRegex = computedLineKey.get('type', 'regex') === 'regex';
	      var operatorExists = computedLineKey.get('operator', '') === '$exists' || computedLineKey.get('operator', '') === '$existsFalse';
	      var checkboxStyle = { marginTop: 10 };
	      var conditionOption = this.getConditionResultProjectOptions().concat(lineKeyOptions);
	      return _react2.default.createElement(
	        _reactBootstrap.Form,
	        { horizontal: true },
	        _react2.default.createElement(
	          _reactBootstrap.FormGroup,
	          null,
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { componentClass: _reactBootstrap.ControlLabel, sm: 3 },
	            'Computation Type'
	          ),
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 3 },
	            _react2.default.createElement(
	              'div',
	              { className: 'inline' },
	              _react2.default.createElement(_Field2.default, {
	                fieldType: 'radio',
	                name: 'computed-type',
	                id: 'computed-type-regex',
	                value: 'regex',
	                checked: computedTypeRegex,
	                onChange: this.props.onChangeComputedLineKeyType,
	                label: 'Regex'
	              })
	            )
	          ),
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 3 },
	            _react2.default.createElement(
	              'div',
	              { className: 'inline' },
	              _react2.default.createElement(_Field2.default, {
	                fieldType: 'radio',
	                name: 'computed-type',
	                id: 'computed-type-condition',
	                value: 'condition',
	                checked: !computedTypeRegex,
	                onChange: this.props.onChangeComputedLineKeyType,
	                label: 'Condition'
	              })
	            )
	          )
	        ),
	        _react2.default.createElement('div', { className: 'separator' }),
	        _react2.default.createElement(
	          _reactBootstrap.FormGroup,
	          { key: 'computed-field-1' },
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 3, componentClass: _reactBootstrap.ControlLabel },
	            computedTypeRegex ? 'Field' : 'First Field'
	          ),
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 4 },
	            _react2.default.createElement(_reactSelect2.default, {
	              onChange: this.props.onChangeComputedLineKey(['line_keys', 0, 'key']),
	              value: computedLineKey.getIn(['line_keys', 0, 'key'], ''),
	              options: lineKeyOptions,
	              allowCreate: true
	            })
	          ),
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 5 },
	            _react2.default.createElement(_Field2.default, {
	              value: computedLineKey.getIn(['line_keys', 0, 'regex'], ''),
	              disabledValue: '',
	              onChange: this.props.onChangeComputedLineKey(['line_keys', 0, 'regex']),
	              disabled: computedLineKey.getIn(['line_keys', 0, 'key'], '') === '' || operatorExists,
	              label: _react2.default.createElement(
	                'span',
	                null,
	                'Regex',
	                _react2.default.createElement(_Help2.default, { contents: regexHelper })
	              ),
	              fieldType: 'toggeledInput'
	            })
	          )
	        ),
	        !computedTypeRegex && [_react2.default.createElement(
	          _reactBootstrap.FormGroup,
	          { key: 'computed-operator' },
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 3, componentClass: _reactBootstrap.ControlLabel },
	            'Operator'
	          ),
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 4 },
	            _react2.default.createElement(_reactSelect2.default, {
	              onChange: this.props.onChangeComputedLineKey(['operator']),
	              value: computedLineKey.get('operator', ''),
	              options: this.getRateConditions()
	            })
	          )
	        ), _react2.default.createElement(
	          _reactBootstrap.FormGroup,
	          { key: 'computed-field-2' },
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 3, componentClass: _reactBootstrap.ControlLabel },
	            'Second Field'
	          ),
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 4 },
	            computedLineKey.get('operator', '') === '$regex' ? _react2.default.createElement(_Field2.default, {
	              value: computedLineKey.getIn(['line_keys', 1, 'key'], ''),
	              onChange: this.onChangeComputedLineKeyHardCodedKey
	            }) : _react2.default.createElement(_reactSelect2.default, {
	              onChange: this.props.onChangeComputedLineKey(['line_keys', 1, 'key']),
	              value: computedLineKey.getIn(['line_keys', 1, 'key'], ''),
	              options: lineKeyOptions,
	              disabled: operatorExists
	            })
	          ),
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 5 },
	            _react2.default.createElement(_Field2.default, {
	              value: computedLineKey.getIn(['line_keys', 1, 'regex'], ''),
	              disabledValue: '',
	              onChange: this.props.onChangeComputedLineKey(['line_keys', 1, 'regex']),
	              disabled: computedLineKey.getIn(['line_keys', 1, 'key'], '') === '' || computedLineKey.get('operator', '') === '$regex',
	              label: _react2.default.createElement(
	                'span',
	                null,
	                'Regex',
	                _react2.default.createElement(_Help2.default, { contents: regexHelper })
	              ),
	              fieldType: 'toggeledInput'
	            })
	          )
	        ), _react2.default.createElement(
	          _reactBootstrap.FormGroup,
	          { key: 'computed-must-met' },
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { componentClass: _reactBootstrap.ControlLabel, sm: 3 },
	            'Must met?',
	            _react2.default.createElement(_Help2.default, { contents: mustMetHelper })
	          ),
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 3, style: checkboxStyle },
	            _react2.default.createElement(
	              'div',
	              { className: 'inline' },
	              _react2.default.createElement(_Field2.default, {
	                fieldType: 'checkbox',
	                id: 'computed-must-met',
	                value: computedLineKey.get('must_met', false),
	                onChange: this.props.onChangeComputedMustMet
	              })
	            )
	          )
	        ), _react2.default.createElement(
	          _reactBootstrap.FormGroup,
	          { key: 'computed-cond-project-true' },
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 3, componentClass: _reactBootstrap.ControlLabel },
	            'Value when True'
	          ),
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 4 },
	            _react2.default.createElement(_reactSelect2.default, {
	              onChange: this.props.onChangeComputedLineKey(['projection', 'on_true', 'key']),
	              value: computedLineKey.getIn(['projection', 'on_true', 'key'], 'condition_result'),
	              options: conditionOption
	            })
	          ),
	          ['hard_coded'].includes(computedLineKey.getIn(['projection', 'on_true', 'key'], '')) && _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 4 },
	            _react2.default.createElement(_Field2.default, {
	              value: computedLineKey.getIn(['projection', 'on_true', 'value'], ''),
	              onChange: this.props.onChangeHardCodedValue(['projection', 'on_true', 'value'])
	            })
	          ),
	          !['', 'hard_coded', 'condition_result'].includes(computedLineKey.getIn(['projection', 'on_true', 'key'], '')) && _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 4 },
	            _react2.default.createElement(_Field2.default, {
	              value: computedLineKey.getIn(['projection', 'on_true', 'regex'], ''),
	              disabledValue: '',
	              onChange: this.props.onChangeComputedLineKey(['projection', 'on_true', 'regex']),
	              disabled: computedLineKey.getIn(['projection', 'on_true', 'key'], '') === '',
	              label: 'Regex',
	              fieldType: 'toggeledInput'
	            })
	          )
	        ), _react2.default.createElement(
	          _reactBootstrap.FormGroup,
	          { key: 'computed-cond-project-false' },
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 3, componentClass: _reactBootstrap.ControlLabel },
	            'Value when False'
	          ),
	          _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 4 },
	            _react2.default.createElement(_reactSelect2.default, {
	              onChange: this.props.onChangeComputedLineKey(['projection', 'on_false', 'key']),
	              value: computedLineKey.getIn(['projection', 'on_false', 'key'], 'condition_result'),
	              options: conditionOption,
	              disabled: computedLineKey.get('must_met', false)
	            })
	          ),
	          ['hard_coded'].includes(computedLineKey.getIn(['projection', 'on_false', 'key'], '')) && _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 4 },
	            _react2.default.createElement(_Field2.default, {
	              value: computedLineKey.getIn(['projection', 'on_false', 'value'], ''),
	              onChange: this.props.onChangeHardCodedValue(['projection', 'on_false', 'value'])
	            })
	          ),
	          !['', 'hard_coded', 'condition_result'].includes(computedLineKey.getIn(['projection', 'on_false', 'key'], '')) && _react2.default.createElement(
	            _reactBootstrap.Col,
	            { sm: 4 },
	            _react2.default.createElement(_Field2.default, {
	              value: computedLineKey.getIn(['projection', 'on_false', 'regex'], ''),
	              disabledValue: '',
	              onChange: this.props.onChangeComputedLineKey(['projection', 'on_false', 'regex']),
	              disabled: computedLineKey.getIn(['projection', 'on_false', 'key'], '') === '',
	              label: 'Regex',
	              fieldType: 'toggeledInput'
	            })
	          )
	        )]
	      );
	    }
	  }]);

	  return ComputedRate;
	}(_react.Component);

	ComputedRate.propTypes = {
	  computedLineKey: _react.PropTypes.instanceOf(_immutable2.default.Map),
	  settings: _react.PropTypes.instanceOf(_immutable2.default.Map),
	  foreignFields: _react.PropTypes.instanceOf(_immutable2.default.List),
	  onChangeComputedLineKeyType: _react.PropTypes.func,
	  onChangeComputedLineKey: _react.PropTypes.func,
	  onChangeComputedMustMet: _react.PropTypes.func,
	  onChangeHardCodedValue: _react.PropTypes.func
	};
	ComputedRate.defaultProps = {
	  computedLineKey: _immutable2.default.Map(),
	  settings: _immutable2.default.Map(),
	  foreignFields: _immutable2.default.List(),
	  onChangeComputedLineKeyType: function onChangeComputedLineKeyType() {},
	  onChangeComputedLineKey: function onChangeComputedLineKey() {},
	  onChangeComputedMustMet: function onChangeComputedMustMet() {},
	  onChangeHardCodedValue: function onChangeHardCodedValue() {}
	};


	var mapStateToProps = function mapStateToProps(state, props) {
	  return {
	    foreignFields: (0, _settingsSelector.linesFieldsSelector)(state, props)
	  };
	};

	exports.default = (0, _reactRedux.connect)(mapStateToProps)(ComputedRate);

/***/ }

})