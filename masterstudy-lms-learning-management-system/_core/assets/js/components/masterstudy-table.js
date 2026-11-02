"use strict";

function _regeneratorRuntime() { "use strict"; /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return exports; }; var exports = {}, Op = Object.prototype, hasOwn = Op.hasOwnProperty, defineProperty = Object.defineProperty || function (obj, key, desc) { obj[key] = desc.value; }, $Symbol = "function" == typeof Symbol ? Symbol : {}, iteratorSymbol = $Symbol.iterator || "@@iterator", asyncIteratorSymbol = $Symbol.asyncIterator || "@@asyncIterator", toStringTagSymbol = $Symbol.toStringTag || "@@toStringTag"; function define(obj, key, value) { return Object.defineProperty(obj, key, { value: value, enumerable: !0, configurable: !0, writable: !0 }), obj[key]; } try { define({}, ""); } catch (err) { define = function define(obj, key, value) { return obj[key] = value; }; } function wrap(innerFn, outerFn, self, tryLocsList) { var protoGenerator = outerFn && outerFn.prototype instanceof Generator ? outerFn : Generator, generator = Object.create(protoGenerator.prototype), context = new Context(tryLocsList || []); return defineProperty(generator, "_invoke", { value: makeInvokeMethod(innerFn, self, context) }), generator; } function tryCatch(fn, obj, arg) { try { return { type: "normal", arg: fn.call(obj, arg) }; } catch (err) { return { type: "throw", arg: err }; } } exports.wrap = wrap; var ContinueSentinel = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var IteratorPrototype = {}; define(IteratorPrototype, iteratorSymbol, function () { return this; }); var getProto = Object.getPrototypeOf, NativeIteratorPrototype = getProto && getProto(getProto(values([]))); NativeIteratorPrototype && NativeIteratorPrototype !== Op && hasOwn.call(NativeIteratorPrototype, iteratorSymbol) && (IteratorPrototype = NativeIteratorPrototype); var Gp = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(IteratorPrototype); function defineIteratorMethods(prototype) { ["next", "throw", "return"].forEach(function (method) { define(prototype, method, function (arg) { return this._invoke(method, arg); }); }); } function AsyncIterator(generator, PromiseImpl) { function invoke(method, arg, resolve, reject) { var record = tryCatch(generator[method], generator, arg); if ("throw" !== record.type) { var result = record.arg, value = result.value; return value && "object" == _typeof(value) && hasOwn.call(value, "__await") ? PromiseImpl.resolve(value.__await).then(function (value) { invoke("next", value, resolve, reject); }, function (err) { invoke("throw", err, resolve, reject); }) : PromiseImpl.resolve(value).then(function (unwrapped) { result.value = unwrapped, resolve(result); }, function (error) { return invoke("throw", error, resolve, reject); }); } reject(record.arg); } var previousPromise; defineProperty(this, "_invoke", { value: function value(method, arg) { function callInvokeWithMethodAndArg() { return new PromiseImpl(function (resolve, reject) { invoke(method, arg, resolve, reject); }); } return previousPromise = previousPromise ? previousPromise.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); } }); } function makeInvokeMethod(innerFn, self, context) { var state = "suspendedStart"; return function (method, arg) { if ("executing" === state) throw new Error("Generator is already running"); if ("completed" === state) { if ("throw" === method) throw arg; return doneResult(); } for (context.method = method, context.arg = arg;;) { var delegate = context.delegate; if (delegate) { var delegateResult = maybeInvokeDelegate(delegate, context); if (delegateResult) { if (delegateResult === ContinueSentinel) continue; return delegateResult; } } if ("next" === context.method) context.sent = context._sent = context.arg;else if ("throw" === context.method) { if ("suspendedStart" === state) throw state = "completed", context.arg; context.dispatchException(context.arg); } else "return" === context.method && context.abrupt("return", context.arg); state = "executing"; var record = tryCatch(innerFn, self, context); if ("normal" === record.type) { if (state = context.done ? "completed" : "suspendedYield", record.arg === ContinueSentinel) continue; return { value: record.arg, done: context.done }; } "throw" === record.type && (state = "completed", context.method = "throw", context.arg = record.arg); } }; } function maybeInvokeDelegate(delegate, context) { var methodName = context.method, method = delegate.iterator[methodName]; if (undefined === method) return context.delegate = null, "throw" === methodName && delegate.iterator["return"] && (context.method = "return", context.arg = undefined, maybeInvokeDelegate(delegate, context), "throw" === context.method) || "return" !== methodName && (context.method = "throw", context.arg = new TypeError("The iterator does not provide a '" + methodName + "' method")), ContinueSentinel; var record = tryCatch(method, delegate.iterator, context.arg); if ("throw" === record.type) return context.method = "throw", context.arg = record.arg, context.delegate = null, ContinueSentinel; var info = record.arg; return info ? info.done ? (context[delegate.resultName] = info.value, context.next = delegate.nextLoc, "return" !== context.method && (context.method = "next", context.arg = undefined), context.delegate = null, ContinueSentinel) : info : (context.method = "throw", context.arg = new TypeError("iterator result is not an object"), context.delegate = null, ContinueSentinel); } function pushTryEntry(locs) { var entry = { tryLoc: locs[0] }; 1 in locs && (entry.catchLoc = locs[1]), 2 in locs && (entry.finallyLoc = locs[2], entry.afterLoc = locs[3]), this.tryEntries.push(entry); } function resetTryEntry(entry) { var record = entry.completion || {}; record.type = "normal", delete record.arg, entry.completion = record; } function Context(tryLocsList) { this.tryEntries = [{ tryLoc: "root" }], tryLocsList.forEach(pushTryEntry, this), this.reset(!0); } function values(iterable) { if (iterable) { var iteratorMethod = iterable[iteratorSymbol]; if (iteratorMethod) return iteratorMethod.call(iterable); if ("function" == typeof iterable.next) return iterable; if (!isNaN(iterable.length)) { var i = -1, next = function next() { for (; ++i < iterable.length;) if (hasOwn.call(iterable, i)) return next.value = iterable[i], next.done = !1, next; return next.value = undefined, next.done = !0, next; }; return next.next = next; } } return { next: doneResult }; } function doneResult() { return { value: undefined, done: !0 }; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, defineProperty(Gp, "constructor", { value: GeneratorFunctionPrototype, configurable: !0 }), defineProperty(GeneratorFunctionPrototype, "constructor", { value: GeneratorFunction, configurable: !0 }), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, toStringTagSymbol, "GeneratorFunction"), exports.isGeneratorFunction = function (genFun) { var ctor = "function" == typeof genFun && genFun.constructor; return !!ctor && (ctor === GeneratorFunction || "GeneratorFunction" === (ctor.displayName || ctor.name)); }, exports.mark = function (genFun) { return Object.setPrototypeOf ? Object.setPrototypeOf(genFun, GeneratorFunctionPrototype) : (genFun.__proto__ = GeneratorFunctionPrototype, define(genFun, toStringTagSymbol, "GeneratorFunction")), genFun.prototype = Object.create(Gp), genFun; }, exports.awrap = function (arg) { return { __await: arg }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, asyncIteratorSymbol, function () { return this; }), exports.AsyncIterator = AsyncIterator, exports.async = function (innerFn, outerFn, self, tryLocsList, PromiseImpl) { void 0 === PromiseImpl && (PromiseImpl = Promise); var iter = new AsyncIterator(wrap(innerFn, outerFn, self, tryLocsList), PromiseImpl); return exports.isGeneratorFunction(outerFn) ? iter : iter.next().then(function (result) { return result.done ? result.value : iter.next(); }); }, defineIteratorMethods(Gp), define(Gp, toStringTagSymbol, "Generator"), define(Gp, iteratorSymbol, function () { return this; }), define(Gp, "toString", function () { return "[object Generator]"; }), exports.keys = function (val) { var object = Object(val), keys = []; for (var key in object) keys.push(key); return keys.reverse(), function next() { for (; keys.length;) { var key = keys.pop(); if (key in object) return next.value = key, next.done = !1, next; } return next.done = !0, next; }; }, exports.values = values, Context.prototype = { constructor: Context, reset: function reset(skipTempReset) { if (this.prev = 0, this.next = 0, this.sent = this._sent = undefined, this.done = !1, this.delegate = null, this.method = "next", this.arg = undefined, this.tryEntries.forEach(resetTryEntry), !skipTempReset) for (var name in this) "t" === name.charAt(0) && hasOwn.call(this, name) && !isNaN(+name.slice(1)) && (this[name] = undefined); }, stop: function stop() { this.done = !0; var rootRecord = this.tryEntries[0].completion; if ("throw" === rootRecord.type) throw rootRecord.arg; return this.rval; }, dispatchException: function dispatchException(exception) { if (this.done) throw exception; var context = this; function handle(loc, caught) { return record.type = "throw", record.arg = exception, context.next = loc, caught && (context.method = "next", context.arg = undefined), !!caught; } for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i], record = entry.completion; if ("root" === entry.tryLoc) return handle("end"); if (entry.tryLoc <= this.prev) { var hasCatch = hasOwn.call(entry, "catchLoc"), hasFinally = hasOwn.call(entry, "finallyLoc"); if (hasCatch && hasFinally) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } else if (hasCatch) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); } else { if (!hasFinally) throw new Error("try statement without catch or finally"); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } } } }, abrupt: function abrupt(type, arg) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc <= this.prev && hasOwn.call(entry, "finallyLoc") && this.prev < entry.finallyLoc) { var finallyEntry = entry; break; } } finallyEntry && ("break" === type || "continue" === type) && finallyEntry.tryLoc <= arg && arg <= finallyEntry.finallyLoc && (finallyEntry = null); var record = finallyEntry ? finallyEntry.completion : {}; return record.type = type, record.arg = arg, finallyEntry ? (this.method = "next", this.next = finallyEntry.finallyLoc, ContinueSentinel) : this.complete(record); }, complete: function complete(record, afterLoc) { if ("throw" === record.type) throw record.arg; return "break" === record.type || "continue" === record.type ? this.next = record.arg : "return" === record.type ? (this.rval = this.arg = record.arg, this.method = "return", this.next = "end") : "normal" === record.type && afterLoc && (this.next = afterLoc), ContinueSentinel; }, finish: function finish(finallyLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.finallyLoc === finallyLoc) return this.complete(entry.completion, entry.afterLoc), resetTryEntry(entry), ContinueSentinel; } }, "catch": function _catch(tryLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc === tryLoc) { var record = entry.completion; if ("throw" === record.type) { var thrown = record.arg; resetTryEntry(entry); } return thrown; } } throw new Error("illegal catch attempt"); }, delegateYield: function delegateYield(iterable, resultName, nextLoc) { return this.delegate = { iterator: values(iterable), resultName: resultName, nextLoc: nextLoc }, "next" === this.method && (this.arg = undefined), ContinueSentinel; } }, exports; }
function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && iter[Symbol.iterator] != null || iter["@@iterator"] != null) return Array.from(iter); }
function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }
/**
 * Reusable MasterStudy table primitive with server/client sorting, pagination,
 * DataTables-compatible adapters, and automatic card mode for tablet/mobile.
 *
 * Basic usage:
 * const table = new window.Masterstudy.Table({
 *     container: document.querySelector('[data-masterstudy-table="students"]'),
 *     adapter: 'datatables',
 *     columns: [
 *         { key: 'student', label: 'Student', width: '32%' },
 *         { key: 'progress', label: 'Progress', align: 'center' },
 *     ],
 *     api: {
 *         url: 'gradebook/students/list',
 *         method: 'POST',
 *         params: () => ({ course_id: selectedCourseId }),
 *     },
 *     dataMap: (row) => ({ student: row.name, progress: `${row.progress}%` }),
 * });
 * table.setParams({ search: 'John' }).fetch();
 *
 * Renderers are safe by default: strings/numbers are inserted as text, Node
 * values are appended directly, and raw HTML must use MasterstudyTable.trustedHtml().
 *
 * @typedef {Object} MasterstudyTableColumn
 * @property {string} [key] Stable column key used by renderers and mapped data.
 * @property {string} [label] Column heading text.
 * @property {'start'|'center'|'end'} [align='start'] Cell alignment.
 * @property {string|number} [width] CSS width for table cells.
 * @property {string|number} [minWidth] CSS min-width for table cells.
 * @property {boolean} [sortable=false] Whether the column can be sorted.
 * @property {string} [sortKey] Server-side sort key.
 * @property {boolean} [hidden=false] Hide the column in all layouts.
 * @property {boolean} [hideOnTablet=false] Hide the column in tablet table layout.
 * @property {boolean} [hideOnMobile=false] Hide the column in mobile/card layout.
 * @property {boolean} [hideOnTabletCard=false] Hide the column in tablet card layout.
 * @property {Function} [renderer] Cell renderer for table and card layouts.
 * @property {Function} [mobileRenderer] Cell renderer used only in card layout.
 * @property {Function} [skeletonRenderer] Skeleton renderer for table and card layouts.
 * @property {Function} [mobileSkeletonRenderer] Skeleton renderer used only in card layout.
 */
/**
 * @typedef {Object} MasterstudyTableApi
 * @property {string} url REST route or absolute URL.
 * @property {'GET'|'POST'} [method='POST'] Request method.
 * @property {Object|Function} [params] Extra params object or params factory.
 * @property {string} [dataKey='data'] Native adapter rows key.
 * @property {string} [totalKey='total'] Native adapter total key.
 * @property {string} [totalPagesKey='max_pages'] Native adapter total pages key.
 */
/**
 * @typedef {Object} MasterstudyTableOptions
 * @property {HTMLElement} container Element where the table should be mounted.
 * @property {string} [id] Optional table id, defaults to container data attribute.
 * @property {MasterstudyTableColumn[]} [columns=[]] Column definitions.
 * @property {MasterstudyTableApi} [api] Request configuration.
 * @property {'native'|'datatables'} [adapter='native'] Built-in adapter name.
 * @property {Function} [requestAdapter] Custom request adapter.
 * @property {Function} [responseAdapter] Custom response adapter.
 * @property {Function} [fetcher] Optional custom fetch function for tests/local data.
 * @property {Function} [dataMap] Maps each raw row into column-keyed display data.
 * @property {Object.<string, Function>} [renderers] Renderers keyed by column key.
 * @property {Object.<string, Function>} [mobileRenderers] Card renderers keyed by column key.
 * @property {Object.<string, Function>} [skeletonRenderers] Skeleton renderers keyed by column key.
 * @property {Object.<string, Function>} [mobileSkeletonRenderers] Card skeleton renderers keyed by column key.
 * @property {Function} [emptyStateRenderer] Renderer for empty state content.
 * @property {'server'|'client'} [sortMode='server'] Sort handling mode.
 * @property {'cards'|'table'} [responsiveLayout='cards'] Responsive layout below tablet/mobile breakpoints.
 * @property {string} [sortBy] Initial sorted column key.
 * @property {'asc'|'desc'|'none'} [sortOrder='none'] Initial sort direction.
 * @property {boolean} [pagination=true] Whether to show pagination.
 * @property {number[]} [perPageOptions=[10,25,50]] Built-in per-page options.
 * @property {number} [perPage=10] Initial rows per page.
 * @property {HTMLTemplateElement} [cardTemplate] Optional card layout template.
 * @property {HTMLElement} [perPageControl] External per-page control wrapper.
 * @property {boolean} [autoFetch=true] Fetch immediately after initialization.
 */
var MasterstudyTable = /*#__PURE__*/function () {
  /**
   * @param {MasterstudyTableOptions} options Table configuration.
   */
  function MasterstudyTable() {
    var options = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
    _classCallCheck(this, MasterstudyTable);
    if (!options.container) {
      throw new Error('MasterstudyTable: "container" is required.');
    }
    var localizedDefaults = (typeof masterstudy_table_data === "undefined" ? "undefined" : _typeof(masterstudy_table_data)) === 'object' && masterstudy_table_data ? masterstudy_table_data : {};
    this.container = options.container;
    this.tableId = options.id || this.container.dataset.masterstudyTable || '';
    this.api = options.api || {};
    this.fetcher = typeof options.fetcher === 'function' ? options.fetcher : null;
    this.columns = this.normalizeColumns(options.columns || []);
    this.dataMap = typeof options.dataMap === 'function' ? options.dataMap : function (row) {
      return row;
    };
    this.renderers = options.renderers || {};
    this.mobileRenderers = options.mobileRenderers || {};
    this.skeletonRenderers = options.skeletonRenderers || {};
    this.mobileSkeletonRenderers = options.mobileSkeletonRenderers || {};
    this.emptyStateRenderer = typeof options.emptyStateRenderer === 'function' ? options.emptyStateRenderer : null;
    this.sortMode = options.sortMode || 'server';
    this.paginationEnabled = options.pagination !== false;
    this.paginationMaxVisiblePages = Number(options.paginationMaxVisiblePages || 5);
    this.paginationItemWidth = Number(options.paginationItemWidth || 40);
    this.perPageOptions = options.perPageOptions === false ? false : options.perPageOptions || [10, 25, 50];
    this.perPage = Number(options.perPage || 10);
    this.currentPage = 1;
    this.totalPages = 1;
    this.total = 0;
    this.sortBy = options.sortBy || '';
    this.sortOrder = ['asc', 'desc', 'none'].includes(options.sortOrder) ? options.sortOrder : 'none';
    this.serverParams = {};
    this.rawRows = [];
    this.callbacks = {};
    this.abortController = null;
    this.requestId = 0;
    this.isLoading = false;
    this.mobileBreakpoint = Number(options.mobileBreakpoint || 767.98);
    this.tabletBreakpoint = Number(options.tabletBreakpoint || 1023.98);
    this.mobileMediaQuery = window.matchMedia("(max-width: ".concat(this.mobileBreakpoint, "px)"));
    this.tabletMediaQuery = window.matchMedia("(max-width: ".concat(this.tabletBreakpoint, "px)"));
    this.responsiveLayout = ['cards', 'table'].includes(options.responsiveLayout) ? options.responsiveLayout : 'cards';
    this.isMobile = this.mobileMediaQuery.matches;
    this.isTablet = this.tabletMediaQuery.matches && !this.isMobile;
    this.isCardLayout = this.shouldUseCardLayout();
    this.cardTemplate = options.cardTemplate || this.findCardTemplate();
    this.emptyText = options.emptyText || localizedDefaults.empty_text || 'No data found.';
    this.errorText = options.errorText || localizedDefaults.error_text || 'Something went wrong. Please try again.';
    this.retryText = options.retryText || localizedDefaults.retry_text || 'Retry';
    this.perPageLabel = options.perPageLabel || localizedDefaults.per_page_label || 'Rows per page';
    this.perPageText = options.perPageText || localizedDefaults.per_page_text || 'per page';
    this.previousPageLabel = options.previousPageLabel || localizedDefaults.previous_page_label || 'Previous page';
    this.nextPageLabel = options.nextPageLabel || localizedDefaults.next_page_label || 'Next page';
    this.perPageControl = options.perPageControl || this.findPerPageControl();
    this.perPageControlPlaceholder = null;
    this.perPageControlDefaultDisplay = this.perPageControl ? this.perPageControl.style.display : '';
    this.perPageControlName = this.getPerPageControlName();
    this.adapterName = options.adapter || this.api.adapter || 'native';
    this.requestAdapter = this.getAdapterFunction(options.requestAdapter, 'request');
    this.responseAdapter = this.getAdapterFunction(options.responseAdapter, 'response');
    this.handleViewportChange = this.handleViewportChange.bind(this);
    this.handlePerPageEvent = this.handlePerPageEvent.bind(this);
    this.initStructure();
    this.bindEvents();
    if (options.autoFetch !== false) {
      this.fetch();
    }
  }
  _createClass(MasterstudyTable, [{
    key: "normalizeColumns",
    value: function normalizeColumns(columns) {
      return (Array.isArray(columns) ? columns : []).map(function (column, index) {
        var key = column.key || "column_".concat(index);
        var label = column.label || '';
        return _objectSpread(_objectSpread({}, column), {}, {
          key: key,
          label: label,
          align: column.align || 'start',
          sortable: Boolean(column.sortable),
          sortKey: column.sortKey || key,
          hidden: Boolean(column.hidden)
        });
      });
    }
  }, {
    key: "findCardTemplate",
    value: function findCardTemplate() {
      var template = this.container.querySelector('template[data-masterstudy-table-card], template[data-masterstudy-table-mobile]');
      return template ? template.cloneNode(true) : null;
    }
  }, {
    key: "findPerPageControl",
    value: function findPerPageControl() {
      if (!this.tableId) {
        return null;
      }
      return document.querySelector("[data-masterstudy-table-per-page=\"".concat(this.tableId, "\"]"));
    }
  }, {
    key: "getPerPageControlName",
    value: function getPerPageControlName() {
      if (!this.perPageControl) {
        return '';
      }
      var input = this.perPageControl.matches('input, select') ? this.perPageControl : this.perPageControl.querySelector('input, select');
      if (input && input.value) {
        this.perPage = Number(input.value);
      }
      return input ? input.name || input.id || '' : '';
    }
  }, {
    key: "getAdapterFunction",
    value: function getAdapterFunction(adapter, type) {
      if (typeof adapter === 'function') {
        return adapter;
      }
      var adapterName = typeof adapter === 'string' ? adapter : this.adapterName;
      var adapters = MasterstudyTable.adapters;
      return adapters[adapterName] && adapters[adapterName][type] || adapters["native"][type];
    }

    /**
     * Subscribe to a table lifecycle event.
     *
     * @param {string} eventName Event name.
     * @param {Function} callback Event callback.
     * @returns {MasterstudyTable}
     */
  }, {
    key: "on",
    value: function on(eventName, callback) {
      if (!this.callbacks[eventName]) {
        this.callbacks[eventName] = [];
      }
      this.callbacks[eventName].push(callback);
      return this;
    }
  }, {
    key: "emit",
    value: function emit(eventName, payload) {
      (this.callbacks[eventName] || []).forEach(function (callback) {
        try {
          callback(payload);
        } catch (error) {
          console.error(error);
        }
      });
    }

    /**
     * Merge additional request params. Useful for external filters/search.
     *
     * @param {Object} params Params merged into future requests.
     * @param {boolean} [resetPage=true] Reset current page to 1.
     * @returns {MasterstudyTable}
     */
  }, {
    key: "setParams",
    value: function setParams() {
      var params = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
      var resetPage = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : true;
      this.serverParams = _objectSpread(_objectSpread({}, this.serverParams), params);
      if (resetPage) {
        this.currentPage = 1;
      }
      return this;
    }

    /**
     * Get the latest raw rows returned by the response adapter.
     *
     * @returns {Array}
     */
  }, {
    key: "getData",
    value: function getData() {
      return this.rawRows;
    }

    /**
     * Clear rows, pagination totals, loading state, and current rendering.
     *
     * @param {{showEmpty?: boolean}} [options] Clear options.
     * @returns {MasterstudyTable}
     */
  }, {
    key: "clear",
    value: function clear() {
      var options = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
      if (this.abortController) {
        this.abortController.abort();
      }
      this.rawRows = [];
      this.total = 0;
      this.totalPages = 1;
      this.currentPage = 1;
      this.isLoading = false;
      this.hideLoading();
      this.errorState.style.display = 'none';
      this.togglePerPageControl(false);
      this.render();
      if (options.showEmpty === false) {
        this.emptyState.style.display = 'none';
      }
      return this;
    }

    /**
     * Abort active requests, remove listeners, and clear the container.
     *
     * @returns {void}
     */
  }, {
    key: "destroy",
    value: function destroy() {
      if (this.abortController) {
        this.abortController.abort();
      }
      this.removeMediaQueryListener(this.mobileMediaQuery, this.handleViewportChange);
      this.removeMediaQueryListener(this.tabletMediaQuery, this.handleViewportChange);
      document.removeEventListener('msfieldEvent', this.handlePerPageEvent);
      this.container.innerHTML = '';
    }
  }, {
    key: "initStructure",
    value: function initStructure() {
      this.container.innerHTML = '';
      this.root = document.createElement('div');
      this.root.className = 'masterstudy-table';
      this.wrapper = document.createElement('div');
      this.wrapper.className = 'masterstudy-table__wrapper';
      this.table = document.createElement('table');
      this.table.className = 'masterstudy-table__table';
      this.thead = document.createElement('thead');
      this.tbody = document.createElement('tbody');
      this.cardList = document.createElement('div');
      this.cardList.className = 'masterstudy-table__cards';
      this.emptyState = document.createElement('div');
      this.emptyState.className = 'masterstudy-table__empty';
      this.emptyState.style.display = 'none';
      this.errorState = document.createElement('div');
      this.errorState.className = 'masterstudy-table__error';
      this.errorState.style.display = 'none';
      this.footer = document.createElement('div');
      this.footer.className = 'masterstudy-table__footer';
      this.table.appendChild(this.thead);
      this.table.appendChild(this.tbody);
      this.wrapper.appendChild(this.table);
      this.wrapper.appendChild(this.cardList);
      this.wrapper.appendChild(this.emptyState);
      this.wrapper.appendChild(this.errorState);
      this.root.appendChild(this.wrapper);
      this.root.appendChild(this.footer);
      this.container.appendChild(this.root);
      this.render();
    }
  }, {
    key: "bindEvents",
    value: function bindEvents() {
      var _this = this;
      this.thead.addEventListener('click', function (event) {
        var button = event.target.closest('.masterstudy-table__sort-button');
        if (!button) {
          return;
        }
        var columnKey = button.dataset.columnKey;
        var nextSort = _this.getNextSortOrder(button.dataset.sort || 'none');
        _this.updateSort(columnKey, nextSort);
        if (_this.sortMode === 'client') {
          _this.render();
          return;
        }
        _this.fetch();
      });
      this.footer.addEventListener('click', function (event) {
        var button = event.target.closest('.masterstudy-pagination__button-prev, .masterstudy-pagination__button-next, .masterstudy-pagination__item-block');
        if (!button || button.classList.contains('masterstudy-pagination__button_disabled')) {
          return;
        }
        var page = Number(button.dataset.id || 1);
        if (button.classList.contains('masterstudy-pagination__button-prev')) {
          page = _this.currentPage - 1;
        }
        if (button.classList.contains('masterstudy-pagination__button-next')) {
          page = _this.currentPage + 1;
        }
        if (page > 0 && page !== _this.currentPage) {
          _this.currentPage = page;
          _this.fetch();
        }
      });
      this.footer.addEventListener('keydown', function (event) {
        if (!['Enter', ' '].includes(event.key)) {
          return;
        }
        var button = event.target.closest('.masterstudy-pagination__button-prev, .masterstudy-pagination__button-next, .masterstudy-pagination__item-block');
        if (!button) {
          return;
        }
        event.preventDefault();
        button.click();
      });
      this.footer.addEventListener('change', function (event) {
        if (!event.target.classList.contains('masterstudy-table__per-page-select')) {
          return;
        }
        _this.perPage = Number(event.target.value || _this.perPage);
        _this.currentPage = 1;
        _this.fetch();
      });
      this.addMediaQueryListener(this.mobileMediaQuery, this.handleViewportChange);
      this.addMediaQueryListener(this.tabletMediaQuery, this.handleViewportChange);
      if (this.perPageControlName) {
        document.addEventListener('msfieldEvent', this.handlePerPageEvent);
      }
    }
  }, {
    key: "handlePerPageEvent",
    value: function handlePerPageEvent(event) {
      var _event$detail;
      if (((_event$detail = event.detail) === null || _event$detail === void 0 ? void 0 : _event$detail.name) !== this.perPageControlName) {
        return;
      }
      var perPage = Number(event.detail.value || this.perPage);
      if (!perPage || perPage === this.perPage) {
        return;
      }
      this.perPage = perPage;
      this.currentPage = 1;
      this.fetch();
    }
  }, {
    key: "handleViewportChange",
    value: function handleViewportChange() {
      this.isMobile = this.mobileMediaQuery.matches;
      this.isTablet = this.tabletMediaQuery.matches && !this.isMobile;
      this.isCardLayout = this.shouldUseCardLayout();
      this.render();
    }
  }, {
    key: "shouldUseCardLayout",
    value: function shouldUseCardLayout() {
      return this.responsiveLayout !== 'table' && (this.isMobile || this.isTablet);
    }
  }, {
    key: "addMediaQueryListener",
    value: function addMediaQueryListener(mediaQuery, callback) {
      if (typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', callback);
        return;
      }
      mediaQuery.addListener(callback);
    }
  }, {
    key: "removeMediaQueryListener",
    value: function removeMediaQueryListener(mediaQuery, callback) {
      if (typeof mediaQuery.removeEventListener === 'function') {
        mediaQuery.removeEventListener('change', callback);
        return;
      }
      mediaQuery.removeListener(callback);
    }
  }, {
    key: "getNextSortOrder",
    value: function getNextSortOrder(current) {
      switch (current) {
        case 'asc':
          return 'desc';
        case 'desc':
          return 'none';
        case 'none':
        default:
          return 'asc';
      }
    }
  }, {
    key: "updateSort",
    value: function updateSort(columnKey, sortOrder) {
      this.sortBy = sortOrder === 'none' ? '' : columnKey;
      this.sortOrder = sortOrder;
      this.currentPage = 1;
      this.renderHead();
    }
  }, {
    key: "getTableColumns",
    value: function getTableColumns() {
      var _this2 = this;
      return this.columns.filter(function (column) {
        if (column.hidden) {
          return false;
        }
        if (_this2.isTablet && column.hideOnTablet) {
          return false;
        }
        return !(_this2.isMobile && column.hideOnMobile);
      });
    }
  }, {
    key: "getCardColumns",
    value: function getCardColumns() {
      var _this3 = this;
      return this.columns.filter(function (column) {
        if (column.hidden) {
          return false;
        }
        if (_this3.isTablet && column.hideOnTabletCard) {
          return false;
        }
        return !(_this3.isMobile && column.hideOnMobile);
      });
    }
  }, {
    key: "getCellClassName",
    value: function getCellClassName() {
      var column = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
      var classes = ['masterstudy-table__cell'];
      if (column.align) {
        classes.push("masterstudy-table__cell_".concat(column.align));
      }
      if (column.cellClass) {
        classes.push(column.cellClass);
      }
      return classes.join(' ');
    }
  }, {
    key: "getCellStyle",
    value: function getCellStyle() {
      var column = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
      var styles = {};
      if (column.width) {
        styles.width = typeof column.width === 'number' ? "".concat(column.width, "px") : column.width;
      }
      if (column.minWidth) {
        styles.minWidth = typeof column.minWidth === 'number' ? "".concat(column.minWidth, "px") : column.minWidth;
      }
      if (column.grow) {
        styles.width = "".concat(Number(column.grow) * 100, "px");
      }
      return styles;
    }
  }, {
    key: "applyStyles",
    value: function applyStyles(element, styles) {
      Object.keys(styles).forEach(function (property) {
        element.style[property] = styles[property];
      });
    }
  }, {
    key: "getRowData",
    value: function getRowData(rawRow) {
      try {
        var mapped = this.dataMap(rawRow);
        return mapped && _typeof(mapped) === 'object' ? mapped : {};
      } catch (error) {
        console.error(error);
        return {};
      }
    }
  }, {
    key: "getComparator",
    value: function getComparator(sortBy) {
      var _this4 = this;
      return function (first, second) {
        var firstValue = _this4.getSortableValue(first, sortBy);
        var secondValue = _this4.getSortableValue(second, sortBy);
        var firstNumber = Number(firstValue);
        var secondNumber = Number(secondValue);
        if (!Number.isNaN(firstNumber) && !Number.isNaN(secondNumber)) {
          return firstNumber - secondNumber;
        }
        var firstDate = Date.parse(firstValue);
        var secondDate = Date.parse(secondValue);
        if (!Number.isNaN(firstDate) && !Number.isNaN(secondDate)) {
          return firstDate - secondDate;
        }
        return String(firstValue || '').localeCompare(String(secondValue || ''));
      };
    }
  }, {
    key: "getSortableValue",
    value: function getSortableValue(row, key) {
      var _mapped$key;
      var mapped = this.getRowData(row);
      return (_mapped$key = mapped[key]) !== null && _mapped$key !== void 0 ? _mapped$key : '';
    }
  }, {
    key: "getRowsForRender",
    value: function getRowsForRender() {
      if (this.sortMode === 'client' && this.sortBy && this.sortOrder !== 'none') {
        var rows = _toConsumableArray(this.rawRows);
        rows.sort(this.getComparator(this.sortBy));
        if (this.sortOrder === 'desc') {
          rows.reverse();
        }
        return rows;
      }
      return this.rawRows;
    }
  }, {
    key: "render",
    value: function render() {
      this.root.classList.toggle('masterstudy-table_mobile', this.isMobile);
      this.root.classList.toggle('masterstudy-table_tablet', this.isTablet);
      this.root.classList.toggle('masterstudy-table_cards', this.isCardLayout);
      this.root.classList.toggle('masterstudy-table_responsive-table', this.responsiveLayout === 'table');
      this.renderHead();
      this.renderBody();
      this.renderFooter();
    }
  }, {
    key: "renderHead",
    value: function renderHead() {
      var _this5 = this;
      var row = document.createElement('tr');
      this.thead.innerHTML = '';
      this.getTableColumns().forEach(function (column) {
        var cell = document.createElement('th');
        cell.className = _this5.getCellClassName(column);
        cell.scope = 'col';
        _this5.applyStyles(cell, _this5.getCellStyle(column));
        if (column.sortable) {
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'masterstudy-table__sort-button';
          button.dataset.columnKey = column.key;
          button.dataset.sort = _this5.sortBy === column.key ? _this5.sortOrder : 'none';
          var title = document.createElement('span');
          title.className = 'masterstudy-table__cell-title';
          title.textContent = column.label;
          button.appendChild(title);
          button.appendChild(_this5.createSortIndicator(button.dataset.sort));
          cell.appendChild(button);
        } else {
          var _title = document.createElement('span');
          _title.className = 'masterstudy-table__cell-title';
          _title.textContent = column.label;
          cell.appendChild(_title);
        }
        row.appendChild(cell);
      });
      this.thead.appendChild(row);
    }
  }, {
    key: "createSortIndicator",
    value: function createSortIndicator(sort) {
      var indicator = document.createElement('span');
      var up = document.createElement('span');
      var down = document.createElement('span');
      indicator.className = 'masterstudy-sort-indicator';
      indicator.dataset.sort = sort || 'none';
      up.className = 'masterstudy-sort-indicator__up';
      down.className = 'masterstudy-sort-indicator__down';
      if (sort === 'asc') {
        down.classList.add('masterstudy-sort-indicator_is-hidden');
      } else if (sort === 'desc') {
        up.classList.add('masterstudy-sort-indicator_is-hidden');
      }
      indicator.appendChild(up);
      indicator.appendChild(down);
      return indicator;
    }
  }, {
    key: "renderBody",
    value: function renderBody() {
      var _this6 = this;
      var rows = this.getRowsForRender();
      this.tbody.innerHTML = '';
      this.cardList.innerHTML = '';
      if (this.isLoading) {
        this.renderSkeleton();
        return;
      }
      rows.forEach(function (rawRow) {
        _this6.tbody.appendChild(_this6.createTableRow(rawRow));
        _this6.cardList.appendChild(_this6.createCardRow(rawRow));
      });
      this.table.style.display = this.isCardLayout ? 'none' : '';
      this.cardList.style.display = this.isCardLayout && rows.length ? '' : 'none';
      this.renderEmptyState(rows);
    }
  }, {
    key: "renderEmptyState",
    value: function renderEmptyState(rows) {
      var shouldShowEmpty = !rows.length && this.errorState.style.display === 'none';
      this.emptyState.style.display = shouldShowEmpty ? 'flex' : 'none';
      if (!shouldShowEmpty) {
        return;
      }
      this.emptyState.innerHTML = '';
      if (this.emptyStateRenderer) {
        this.appendRenderedValue(this.emptyState, this.emptyStateRenderer({
          table: this,
          params: this.getRequestParams(),
          total: this.total,
          rows: rows
        }));
        return;
      }
      this.emptyState.textContent = this.emptyText;
    }
  }, {
    key: "createTableRow",
    value: function createTableRow(rawRow) {
      var _this7 = this;
      var rowData = this.getRowData(rawRow);
      var row = document.createElement('tr');
      this.getTableColumns().forEach(function (column) {
        var cell = document.createElement('td');
        cell.className = _this7.getCellClassName(column);
        _this7.applyStyles(cell, _this7.getCellStyle(column));
        _this7.applyCellValue(cell, column, rowData[column.key], rawRow, false);
        row.appendChild(cell);
      });
      return row;
    }
  }, {
    key: "createCardRow",
    value: function createCardRow(rawRow) {
      var _this8 = this;
      if (this.cardTemplate) {
        return this.createTemplateCardRow(rawRow);
      }
      var rowData = this.getRowData(rawRow);
      var card = document.createElement('div');
      card.className = 'masterstudy-table__card';
      this.getCardColumns().forEach(function (column) {
        var row = document.createElement('div');
        var label = document.createElement('span');
        var value = document.createElement('span');
        row.className = 'masterstudy-table__card-row';
        label.className = 'masterstudy-table__card-label';
        label.textContent = column.mobileLabel || column.label;
        value.className = 'masterstudy-table__card-value';
        _this8.applyCellValue(value, column, rowData[column.key], rawRow, true);
        row.appendChild(label);
        row.appendChild(value);
        card.appendChild(row);
      });
      return card;
    }
  }, {
    key: "getSkeletonRowCount",
    value: function getSkeletonRowCount() {
      var perPage = Math.max(1, Number(this.perPage || 1));
      if (this.total > 0) {
        var remaining = this.total - (this.currentPage - 1) * perPage;
        return Math.max(1, Math.min(perPage, remaining));
      }
      return perPage;
    }
  }, {
    key: "getSkeletonWidth",
    value: function getSkeletonWidth() {
      var column = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
      var widths = {
        lessons: '50px',
        quizzes: '50px',
        assignments: '50px',
        progress: '50px',
        started: '80px'
      };
      return widths[column.key] || '70px';
    }
  }, {
    key: "createSkeletonBlock",
    value: function createSkeletonBlock() {
      var width = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : '100%';
      var height = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '20px';
      var skeleton = document.createElement('span');
      skeleton.className = 'masterstudy-table__skeleton-cell';
      skeleton.style.width = width;
      skeleton.style.maxWidth = '100%';
      skeleton.style.height = height;
      return skeleton;
    }
  }, {
    key: "createSkeletonValue",
    value: function createSkeletonValue(column) {
      var isCard = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
      var renderer = this.getSkeletonRenderer(column, isCard);
      if (typeof renderer === 'function') {
        return this.createSkeletonRendererValue(renderer(column, this, isCard));
      }
      return this.createSkeletonBlock(this.getSkeletonWidth(column), isCard ? '18px' : '20px');
    }
  }, {
    key: "getSkeletonRenderer",
    value: function getSkeletonRenderer(column, isCard) {
      if (isCard && typeof column.mobileSkeletonRenderer === 'function') {
        return column.mobileSkeletonRenderer;
      }
      if (typeof column.skeletonRenderer === 'function') {
        return column.skeletonRenderer;
      }
      if (isCard && typeof this.mobileSkeletonRenderers[column.key] === 'function') {
        return this.mobileSkeletonRenderers[column.key];
      }
      return this.skeletonRenderers[column.key];
    }
  }, {
    key: "createSkeletonRendererValue",
    value: function createSkeletonRendererValue(renderedValue) {
      if (renderedValue instanceof Node) {
        return renderedValue;
      }
      var wrapper = document.createElement('span');
      if (renderedValue && renderedValue.__masterstudyTableTrustedHtml) {
        wrapper.innerHTML = renderedValue.html;
        return wrapper;
      }
      wrapper.textContent = renderedValue !== null && renderedValue !== void 0 ? renderedValue : '';
      return wrapper;
    }
  }, {
    key: "renderSkeleton",
    value: function renderSkeleton() {
      var count = this.getSkeletonRowCount();
      this.tbody.innerHTML = '';
      this.cardList.innerHTML = '';
      for (var rowIndex = 0; rowIndex < count; rowIndex += 1) {
        this.tbody.appendChild(this.createSkeletonTableRow());
        this.cardList.appendChild(this.createSkeletonCardRow());
      }
      this.table.style.display = this.isCardLayout ? 'none' : '';
      this.cardList.style.display = this.isCardLayout ? '' : 'none';
      this.emptyState.style.display = 'none';
    }
  }, {
    key: "createSkeletonTableRow",
    value: function createSkeletonTableRow() {
      var _this9 = this;
      var row = document.createElement('tr');
      this.getTableColumns().forEach(function (column) {
        var cell = document.createElement('td');
        cell.className = _this9.getCellClassName(column);
        _this9.applyStyles(cell, _this9.getCellStyle(column));
        cell.appendChild(_this9.createSkeletonValue(column));
        row.appendChild(cell);
      });
      return row;
    }
  }, {
    key: "createSkeletonCardRow",
    value: function createSkeletonCardRow() {
      var _this10 = this;
      if (this.cardTemplate) {
        return this.createTemplateSkeletonCardRow();
      }
      var card = document.createElement('div');
      card.className = 'masterstudy-table__card masterstudy-table__card_skeleton';
      this.getCardColumns().forEach(function (column) {
        var row = document.createElement('div');
        var label = _this10.createSkeletonBlock('70px', '14px');
        var value = _this10.createSkeletonValue(column, true);
        row.className = 'masterstudy-table__card-row';
        label.classList.add('masterstudy-table__card-label');
        value.classList.add('masterstudy-table__card-value');
        row.appendChild(label);
        row.appendChild(value);
        card.appendChild(row);
      });
      return card;
    }
  }, {
    key: "createTemplateSkeletonCardRow",
    value: function createTemplateSkeletonCardRow() {
      var _this11 = this;
      var fragment = this.cardTemplate.content.cloneNode(true);
      var card = document.createElement('div');
      card.className = 'masterstudy-table__card masterstudy-table__card_skeleton';
      card.appendChild(fragment);
      card.querySelectorAll('[data-masterstudy-table-key]').forEach(function (element) {
        var key = element.getAttribute('data-masterstudy-table-key') || '';
        var column = _this11.columns.find(function (item) {
          return item.key === key;
        });
        element.innerHTML = '';
        if (column) {
          element.appendChild(_this11.createSkeletonValue(column, true));
        }
      });
      return card;
    }
  }, {
    key: "createTemplateCardRow",
    value: function createTemplateCardRow(rawRow) {
      var _this12 = this;
      var rowData = this.getRowData(rawRow);
      var fragment = this.cardTemplate.content.cloneNode(true);
      var card = document.createElement('div');
      card.className = 'masterstudy-table__card';
      card.appendChild(fragment);
      card.querySelectorAll('[data-masterstudy-table-key]').forEach(function (element) {
        var key = element.getAttribute('data-masterstudy-table-key') || '';
        var column = _this12.columns.find(function (item) {
          return item.key === key;
        });
        if (!column) {
          return;
        }
        _this12.applyCellValue(element, column, rowData[key], rawRow, true);
      });
      return card;
    }
  }, {
    key: "applyCellValue",
    value: function applyCellValue(element, column, value, rawRow, isMobile) {
      var renderer = this.getRenderer(column, isMobile);
      var renderedValue = value;
      if (typeof renderer === 'function') {
        renderedValue = renderer(value, rawRow, column, this);
      }
      this.appendRenderedValue(element, renderedValue);
    }
  }, {
    key: "getRenderer",
    value: function getRenderer(column, isMobile) {
      if (isMobile && typeof column.mobileRenderer === 'function') {
        return column.mobileRenderer;
      }
      if (typeof column.renderer === 'function') {
        return column.renderer;
      }
      if (isMobile && typeof this.mobileRenderers[column.key] === 'function') {
        return this.mobileRenderers[column.key];
      }
      return this.renderers[column.key];
    }
  }, {
    key: "appendRenderedValue",
    value: function appendRenderedValue(element, renderedValue) {
      element.innerHTML = '';
      if (renderedValue instanceof Node) {
        element.appendChild(renderedValue);
        return;
      }
      if (renderedValue && renderedValue.__masterstudyTableTrustedHtml) {
        element.innerHTML = renderedValue.html;
        return;
      }
      element.textContent = renderedValue !== null && renderedValue !== void 0 ? renderedValue : '';
    }
  }, {
    key: "renderFooter",
    value: function renderFooter() {
      var _this13 = this;
      this.footer.innerHTML = '';
      this.footer.style.display = 'none';
      this.footer.classList.remove('masterstudy-table__footer_without-pagination');
      if (!this.paginationEnabled || this.total <= 0) {
        this.togglePerPageControl(false);
        return;
      }
      var hasPagination = this.totalPages > 1;
      var hasEnoughRowsForPerPage = this.total > this.getMinimumPerPageOption();
      var shouldRenderBuiltInPerPage = !this.perPageControl && hasEnoughRowsForPerPage && this.perPageOptions !== false && Array.isArray(this.perPageOptions) && this.perPageOptions.length;
      var shouldRenderExternalPerPage = Boolean(this.perPageControl && hasEnoughRowsForPerPage);
      if (!hasPagination && !shouldRenderBuiltInPerPage && !shouldRenderExternalPerPage) {
        this.togglePerPageControl(false);
        return;
      }
      if (hasPagination) {
        this.footer.appendChild(this.createPagination());
      } else {
        this.footer.classList.add('masterstudy-table__footer_without-pagination');
      }
      if (shouldRenderBuiltInPerPage) {
        var perPage = document.createElement('label');
        var select = document.createElement('select');
        var text = document.createElement('span');
        perPage.className = 'masterstudy-table__per-page';
        select.className = 'masterstudy-table__per-page-select';
        select.setAttribute('aria-label', this.perPageLabel);
        this.perPageOptions.forEach(function (value) {
          var option = document.createElement('option');
          option.value = String(value);
          option.textContent = String(value);
          option.selected = Number(value) === _this13.perPage;
          select.appendChild(option);
        });
        text.className = 'masterstudy-table__per-page-text';
        text.textContent = this.perPageText;
        perPage.appendChild(select);
        perPage.appendChild(text);
        this.footer.appendChild(perPage);
      }
      if (shouldRenderExternalPerPage) {
        this.appendExternalPerPageControl();
      }
      this.footer.style.display = 'flex';
      this.togglePerPageControl(shouldRenderExternalPerPage);
    }
  }, {
    key: "createPagination",
    value: function createPagination() {
      var pagination = document.createElement('div');
      var wrapper = document.createElement('div');
      var list = document.createElement('ul');
      var visiblePages = Math.min(this.paginationMaxVisiblePages, this.totalPages);
      pagination.className = 'masterstudy-pagination';
      pagination.dataset.maxVisiblePages = String(this.paginationMaxVisiblePages);
      pagination.dataset.totalPages = String(this.totalPages);
      pagination.dataset.currentPage = String(this.currentPage);
      pagination.dataset.itemWidth = String(this.paginationItemWidth);
      pagination.dataset.isQueryable = '0';
      wrapper.className = 'masterstudy-pagination__wrapper';
      wrapper.dataset.width = "".concat(visiblePages * this.paginationItemWidth, "px");
      wrapper.style.width = wrapper.dataset.width;
      list.className = 'masterstudy-pagination__list';
      list.style.left = "".concat(this.getPaginationListPosition(), "px");
      pagination.appendChild(this.createPaginationButton(this.previousPageLabel, 'masterstudy-pagination__button-prev', this.currentPage <= 1));
      for (var page = 1; page <= this.totalPages; page += 1) {
        var listItem = document.createElement('li');
        var button = this.createPaginationItem(page);
        listItem.className = 'masterstudy-pagination__item';
        listItem.classList.toggle('masterstudy-pagination__item_current', page === this.currentPage);
        listItem.appendChild(button);
        list.appendChild(listItem);
      }
      wrapper.appendChild(list);
      pagination.appendChild(wrapper);
      pagination.appendChild(this.createPaginationButton(this.nextPageLabel, 'masterstudy-pagination__button-next', this.currentPage >= this.totalPages));
      return pagination;
    }
  }, {
    key: "getMinimumPerPageOption",
    value: function getMinimumPerPageOption() {
      if (Array.isArray(this.perPageOptions) && this.perPageOptions.length) {
        var _options = this.perPageOptions.map(function (value) {
          return Number(value);
        }).filter(function (value) {
          return value > 0;
        });
        return _options.length ? Math.min.apply(Math, _toConsumableArray(_options)) : this.perPage;
      }
      if (!this.perPageControl) {
        return this.perPage;
      }
      var options = Array.from(this.perPageControl.querySelectorAll('option')).map(function (option) {
        return Number(option.value);
      }).filter(function (value) {
        return value > 0;
      });
      return options.length ? Math.min.apply(Math, _toConsumableArray(options)) : this.perPage;
    }
  }, {
    key: "appendExternalPerPageControl",
    value: function appendExternalPerPageControl() {
      if (!this.perPageControl) {
        return;
      }
      if (!this.perPageControlPlaceholder) {
        this.perPageControlPlaceholder = document.createComment('masterstudy-table-per-page');
        this.perPageControl.parentNode.insertBefore(this.perPageControlPlaceholder, this.perPageControl);
      }
      this.footer.appendChild(this.perPageControl);
    }
  }, {
    key: "togglePerPageControl",
    value: function togglePerPageControl(isVisible) {
      if (!this.perPageControl) {
        return;
      }
      this.perPageControl.style.display = isVisible ? this.perPageControlDefaultDisplay : 'none';
    }
  }, {
    key: "createPaginationButton",
    value: function createPaginationButton(label, className) {
      var disabled = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : false;
      var button = document.createElement('span');
      button.className = className;
      button.setAttribute('aria-label', label);
      button.classList.toggle('masterstudy-pagination__button_disabled', disabled);
      if (!disabled) {
        button.setAttribute('role', 'button');
        button.setAttribute('tabindex', '0');
      }
      return button;
    }
  }, {
    key: "createPaginationItem",
    value: function createPaginationItem(page) {
      var button = document.createElement('span');
      button.className = 'masterstudy-pagination__item-block';
      button.dataset.id = String(page);
      button.textContent = String(page);
      if (page !== this.currentPage) {
        button.setAttribute('role', 'button');
        button.setAttribute('tabindex', '0');
      }
      return button;
    }
  }, {
    key: "getPaginationListPosition",
    value: function getPaginationListPosition() {
      var maxPosition = Math.max(0, (this.totalPages - this.paginationMaxVisiblePages) * this.paginationItemWidth);
      var centeredPage = Math.round(this.paginationMaxVisiblePages / 2);
      if (this.currentPage <= centeredPage) {
        return 0;
      }
      if (this.currentPage > this.totalPages - centeredPage) {
        return -maxPosition;
      }
      return -((this.currentPage - centeredPage) * this.paginationItemWidth);
    }
  }, {
    key: "showLoading",
    value: function showLoading() {
      this.isLoading = true;
      this.wrapper.classList.add('masterstudy-table__wrapper_loading');
      this.errorState.style.display = 'none';
      this.renderBody();
      this.renderFooter();
    }
  }, {
    key: "hideLoading",
    value: function hideLoading() {
      this.isLoading = false;
      this.wrapper.classList.remove('masterstudy-table__wrapper_loading');
    }
  }, {
    key: "showError",
    value: function showError(error) {
      var _this14 = this;
      this.errorState.innerHTML = '';
      var message = document.createElement('span');
      var retry = document.createElement('button');
      message.className = 'masterstudy-table__error-message';
      message.textContent = error && error.message ? error.message : this.errorText;
      retry.type = 'button';
      retry.className = 'masterstudy-table__error-retry';
      retry.textContent = this.retryText;
      retry.addEventListener('click', function () {
        return _this14.fetch();
      });
      this.errorState.appendChild(message);
      this.errorState.appendChild(retry);
      this.errorState.style.display = 'flex';
      this.table.style.display = 'none';
      this.cardList.style.display = 'none';
      this.emptyState.style.display = 'none';
    }

    /**
     * Fetch rows from the configured API/fetcher and re-render the table.
     *
     * @returns {Promise<void>}
     */
  }, {
    key: "fetch",
    value: function () {
      var _fetch = _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee() {
        var currentRequestId, method, url, requestParams, json, data;
        return _regeneratorRuntime().wrap(function _callee$(_context) {
          while (1) switch (_context.prev = _context.next) {
            case 0:
              if (!(!this.api.url && !this.fetcher)) {
                _context.next = 2;
                break;
              }
              return _context.abrupt("return");
            case 2:
              currentRequestId = ++this.requestId;
              if (this.abortController) {
                this.abortController.abort();
              }
              this.abortController = new AbortController();
              method = (this.api.method || 'POST').toUpperCase();
              url = this.getApiUrl();
              requestParams = this.getRequestParams();
              this.emit('beforeFetch', requestParams);
              this.showLoading();
              _context.prev = 10;
              if (!this.fetcher) {
                _context.next = 17;
                break;
              }
              _context.next = 14;
              return this.fetcher(requestParams, this);
            case 14:
              _context.t0 = _context.sent;
              _context.next = 20;
              break;
            case 17:
              _context.next = 19;
              return this.fetchFromApi(url, method, requestParams);
            case 19:
              _context.t0 = _context.sent;
            case 20:
              json = _context.t0;
              data = this.responseAdapter(json, this);
              if (!(currentRequestId !== this.requestId)) {
                _context.next = 24;
                break;
              }
              return _context.abrupt("return");
            case 24:
              this.rawRows = Array.isArray(data.rows) ? data.rows : [];
              this.total = Number(data.total || this.rawRows.length || 0);
              this.totalPages = Math.max(1, Number(data.totalPages || Math.ceil(this.total / this.perPage)));
              this.emit('afterFetch', json);
              this.emit('beforeRender', this.rawRows);
              this.hideLoading();
              this.errorState.style.display = 'none';
              this.render();
              this.emit('afterRender', this.rawRows);
              _context.next = 38;
              break;
            case 35:
              _context.prev = 35;
              _context.t1 = _context["catch"](10);
              if (_context.t1.name !== 'AbortError') {
                console.error(_context.t1);
                this.hideLoading();
                this.showError(_context.t1);
                this.emit('fetchError', _context.t1);
              }
            case 38:
              _context.prev = 38;
              if (currentRequestId === this.requestId) {
                this.hideLoading();
              }
              return _context.finish(38);
            case 41:
            case "end":
              return _context.stop();
          }
        }, _callee, this, [[10, 35, 38, 41]]);
      }));
      function fetch() {
        return _fetch.apply(this, arguments);
      }
      return fetch;
    }()
  }, {
    key: "fetchFromApi",
    value: function () {
      var _fetchFromApi = _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee2(url, method, requestParams) {
        var fetchOptions, requestUrl, response;
        return _regeneratorRuntime().wrap(function _callee2$(_context2) {
          while (1) switch (_context2.prev = _context2.next) {
            case 0:
              fetchOptions = {
                method: method,
                signal: this.abortController.signal,
                headers: {
                  'Content-Type': 'application/json',
                  'X-WP-Nonce': typeof ms_lms_nonce !== 'undefined' ? ms_lms_nonce : ''
                }
              };
              requestUrl = url;
              if (method === 'GET') {
                requestUrl = "".concat(url).concat(url.includes('?') ? '&' : '?').concat(new URLSearchParams(requestParams).toString());
              } else {
                fetchOptions.body = JSON.stringify(requestParams);
              }
              _context2.next = 5;
              return fetch(requestUrl, fetchOptions);
            case 5:
              response = _context2.sent;
              if (response.ok) {
                _context2.next = 8;
                break;
              }
              throw new Error(this.errorText);
            case 8:
              return _context2.abrupt("return", response.json());
            case 9:
            case "end":
              return _context2.stop();
          }
        }, _callee2, this);
      }));
      function fetchFromApi(_x, _x2, _x3) {
        return _fetchFromApi.apply(this, arguments);
      }
      return fetchFromApi;
    }()
  }, {
    key: "getRequestParams",
    value: function getRequestParams() {
      var apiParams = typeof this.api.params === 'function' ? this.api.params() : this.api.params || {};
      var params = _objectSpread(_objectSpread({}, apiParams), this.serverParams);
      return this.requestAdapter(params, this);
    }
  }, {
    key: "getApiUrl",
    value: function getApiUrl() {
      if (/^https?:\/\//i.test(this.api.url)) {
        return this.api.url;
      }
      var base = (typeof ms_lms_resturl !== 'undefined' ? ms_lms_resturl : '').replace(/\/$/, '');
      var route = String(this.api.url || '').replace(/^\//, '');
      return "".concat(base, "/").concat(route);
    }
  }], [{
    key: "trustedHtml",
    value:
    /**
     * Explicit escape hatch for trusted HTML renderers.
     *
     * Prefer returning text or a Node. Use this only for HTML that has already
     * been escaped/sanitized by the caller.
     *
     * @param {string} html Trusted HTML string.
     * @returns {{__masterstudyTableTrustedHtml: boolean, html: string}}
     */
    function trustedHtml(html) {
      return {
        __masterstudyTableTrustedHtml: true,
        html: String(html || '')
      };
    }

    /**
     * Built-in request/response adapters.
     *
     * Native adapter uses page/per_page contracts. DataTables adapter preserves
     * start/length/order/columns requests and recordsTotal/data responses.
     *
     * @returns {Object}
     */
  }, {
    key: "adapters",
    get: function get() {
      return {
        "native": {
          request: function request(params, table) {
            var request = _objectSpread({
              page: table.currentPage,
              per_page: table.perPage
            }, params);
            if (table.sortBy && table.sortOrder !== 'none') {
              request.sort_by = table.sortBy;
              request.sort_order = table.sortOrder;
            }
            return request;
          },
          response: function response(json, table) {
            var dataKey = table.api.dataKey || 'data';
            var totalPagesKey = table.api.totalPagesKey || 'max_pages';
            var totalKey = table.api.totalKey || 'total';
            var rows = Array.isArray(json[dataKey]) ? json[dataKey] : [];
            var total = Number(json[totalKey] || json.recordsTotal || rows.length || 0);
            var totalPages = Number(json[totalPagesKey] || Math.max(1, Math.ceil(total / table.perPage)));
            return {
              rows: rows,
              total: total,
              totalPages: totalPages
            };
          }
        },
        datatables: {
          request: function request(params, table) {
            var columns = table.columns.map(function (column) {
              return {
                data: column.sortKey || column.key,
                name: column.key,
                searchable: Boolean(column.searchable),
                orderable: Boolean(column.sortable),
                search: {
                  value: '',
                  regex: false
                }
              };
            });
            var order = [];
            if (table.sortBy && table.sortOrder !== 'none') {
              var sortIndex = table.columns.findIndex(function (column) {
                return column.key === table.sortBy;
              });
              if (sortIndex >= 0) {
                order.push({
                  column: sortIndex,
                  dir: table.sortOrder
                });
              }
            }
            return _objectSpread({
              start: (table.currentPage - 1) * table.perPage,
              length: table.perPage,
              order: order,
              columns: columns
            }, params);
          },
          response: function response(json, table) {
            var rows = Array.isArray(json.data) ? json.data : [];
            var total = Number(json.recordsFiltered || json.recordsTotal || rows.length || 0);
            return {
              rows: rows,
              total: total,
              totalPages: Math.max(1, Math.ceil(total / table.perPage))
            };
          }
        }
      };
    }
  }]);
  return MasterstudyTable;
}();
window.Masterstudy = window.Masterstudy || {};
window.Masterstudy.Table = MasterstudyTable;
window.Masterstudy.TableAdapters = MasterstudyTable.adapters;