"use strict";

function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _regeneratorRuntime() { "use strict"; /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return exports; }; var exports = {}, Op = Object.prototype, hasOwn = Op.hasOwnProperty, defineProperty = Object.defineProperty || function (obj, key, desc) { obj[key] = desc.value; }, $Symbol = "function" == typeof Symbol ? Symbol : {}, iteratorSymbol = $Symbol.iterator || "@@iterator", asyncIteratorSymbol = $Symbol.asyncIterator || "@@asyncIterator", toStringTagSymbol = $Symbol.toStringTag || "@@toStringTag"; function define(obj, key, value) { return Object.defineProperty(obj, key, { value: value, enumerable: !0, configurable: !0, writable: !0 }), obj[key]; } try { define({}, ""); } catch (err) { define = function define(obj, key, value) { return obj[key] = value; }; } function wrap(innerFn, outerFn, self, tryLocsList) { var protoGenerator = outerFn && outerFn.prototype instanceof Generator ? outerFn : Generator, generator = Object.create(protoGenerator.prototype), context = new Context(tryLocsList || []); return defineProperty(generator, "_invoke", { value: makeInvokeMethod(innerFn, self, context) }), generator; } function tryCatch(fn, obj, arg) { try { return { type: "normal", arg: fn.call(obj, arg) }; } catch (err) { return { type: "throw", arg: err }; } } exports.wrap = wrap; var ContinueSentinel = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var IteratorPrototype = {}; define(IteratorPrototype, iteratorSymbol, function () { return this; }); var getProto = Object.getPrototypeOf, NativeIteratorPrototype = getProto && getProto(getProto(values([]))); NativeIteratorPrototype && NativeIteratorPrototype !== Op && hasOwn.call(NativeIteratorPrototype, iteratorSymbol) && (IteratorPrototype = NativeIteratorPrototype); var Gp = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(IteratorPrototype); function defineIteratorMethods(prototype) { ["next", "throw", "return"].forEach(function (method) { define(prototype, method, function (arg) { return this._invoke(method, arg); }); }); } function AsyncIterator(generator, PromiseImpl) { function invoke(method, arg, resolve, reject) { var record = tryCatch(generator[method], generator, arg); if ("throw" !== record.type) { var result = record.arg, value = result.value; return value && "object" == _typeof(value) && hasOwn.call(value, "__await") ? PromiseImpl.resolve(value.__await).then(function (value) { invoke("next", value, resolve, reject); }, function (err) { invoke("throw", err, resolve, reject); }) : PromiseImpl.resolve(value).then(function (unwrapped) { result.value = unwrapped, resolve(result); }, function (error) { return invoke("throw", error, resolve, reject); }); } reject(record.arg); } var previousPromise; defineProperty(this, "_invoke", { value: function value(method, arg) { function callInvokeWithMethodAndArg() { return new PromiseImpl(function (resolve, reject) { invoke(method, arg, resolve, reject); }); } return previousPromise = previousPromise ? previousPromise.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); } }); } function makeInvokeMethod(innerFn, self, context) { var state = "suspendedStart"; return function (method, arg) { if ("executing" === state) throw new Error("Generator is already running"); if ("completed" === state) { if ("throw" === method) throw arg; return doneResult(); } for (context.method = method, context.arg = arg;;) { var delegate = context.delegate; if (delegate) { var delegateResult = maybeInvokeDelegate(delegate, context); if (delegateResult) { if (delegateResult === ContinueSentinel) continue; return delegateResult; } } if ("next" === context.method) context.sent = context._sent = context.arg;else if ("throw" === context.method) { if ("suspendedStart" === state) throw state = "completed", context.arg; context.dispatchException(context.arg); } else "return" === context.method && context.abrupt("return", context.arg); state = "executing"; var record = tryCatch(innerFn, self, context); if ("normal" === record.type) { if (state = context.done ? "completed" : "suspendedYield", record.arg === ContinueSentinel) continue; return { value: record.arg, done: context.done }; } "throw" === record.type && (state = "completed", context.method = "throw", context.arg = record.arg); } }; } function maybeInvokeDelegate(delegate, context) { var methodName = context.method, method = delegate.iterator[methodName]; if (undefined === method) return context.delegate = null, "throw" === methodName && delegate.iterator["return"] && (context.method = "return", context.arg = undefined, maybeInvokeDelegate(delegate, context), "throw" === context.method) || "return" !== methodName && (context.method = "throw", context.arg = new TypeError("The iterator does not provide a '" + methodName + "' method")), ContinueSentinel; var record = tryCatch(method, delegate.iterator, context.arg); if ("throw" === record.type) return context.method = "throw", context.arg = record.arg, context.delegate = null, ContinueSentinel; var info = record.arg; return info ? info.done ? (context[delegate.resultName] = info.value, context.next = delegate.nextLoc, "return" !== context.method && (context.method = "next", context.arg = undefined), context.delegate = null, ContinueSentinel) : info : (context.method = "throw", context.arg = new TypeError("iterator result is not an object"), context.delegate = null, ContinueSentinel); } function pushTryEntry(locs) { var entry = { tryLoc: locs[0] }; 1 in locs && (entry.catchLoc = locs[1]), 2 in locs && (entry.finallyLoc = locs[2], entry.afterLoc = locs[3]), this.tryEntries.push(entry); } function resetTryEntry(entry) { var record = entry.completion || {}; record.type = "normal", delete record.arg, entry.completion = record; } function Context(tryLocsList) { this.tryEntries = [{ tryLoc: "root" }], tryLocsList.forEach(pushTryEntry, this), this.reset(!0); } function values(iterable) { if (iterable) { var iteratorMethod = iterable[iteratorSymbol]; if (iteratorMethod) return iteratorMethod.call(iterable); if ("function" == typeof iterable.next) return iterable; if (!isNaN(iterable.length)) { var i = -1, next = function next() { for (; ++i < iterable.length;) if (hasOwn.call(iterable, i)) return next.value = iterable[i], next.done = !1, next; return next.value = undefined, next.done = !0, next; }; return next.next = next; } } return { next: doneResult }; } function doneResult() { return { value: undefined, done: !0 }; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, defineProperty(Gp, "constructor", { value: GeneratorFunctionPrototype, configurable: !0 }), defineProperty(GeneratorFunctionPrototype, "constructor", { value: GeneratorFunction, configurable: !0 }), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, toStringTagSymbol, "GeneratorFunction"), exports.isGeneratorFunction = function (genFun) { var ctor = "function" == typeof genFun && genFun.constructor; return !!ctor && (ctor === GeneratorFunction || "GeneratorFunction" === (ctor.displayName || ctor.name)); }, exports.mark = function (genFun) { return Object.setPrototypeOf ? Object.setPrototypeOf(genFun, GeneratorFunctionPrototype) : (genFun.__proto__ = GeneratorFunctionPrototype, define(genFun, toStringTagSymbol, "GeneratorFunction")), genFun.prototype = Object.create(Gp), genFun; }, exports.awrap = function (arg) { return { __await: arg }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, asyncIteratorSymbol, function () { return this; }), exports.AsyncIterator = AsyncIterator, exports.async = function (innerFn, outerFn, self, tryLocsList, PromiseImpl) { void 0 === PromiseImpl && (PromiseImpl = Promise); var iter = new AsyncIterator(wrap(innerFn, outerFn, self, tryLocsList), PromiseImpl); return exports.isGeneratorFunction(outerFn) ? iter : iter.next().then(function (result) { return result.done ? result.value : iter.next(); }); }, defineIteratorMethods(Gp), define(Gp, toStringTagSymbol, "Generator"), define(Gp, iteratorSymbol, function () { return this; }), define(Gp, "toString", function () { return "[object Generator]"; }), exports.keys = function (val) { var object = Object(val), keys = []; for (var key in object) keys.push(key); return keys.reverse(), function next() { for (; keys.length;) { var key = keys.pop(); if (key in object) return next.value = key, next.done = !1, next; } return next.done = !0, next; }; }, exports.values = values, Context.prototype = { constructor: Context, reset: function reset(skipTempReset) { if (this.prev = 0, this.next = 0, this.sent = this._sent = undefined, this.done = !1, this.delegate = null, this.method = "next", this.arg = undefined, this.tryEntries.forEach(resetTryEntry), !skipTempReset) for (var name in this) "t" === name.charAt(0) && hasOwn.call(this, name) && !isNaN(+name.slice(1)) && (this[name] = undefined); }, stop: function stop() { this.done = !0; var rootRecord = this.tryEntries[0].completion; if ("throw" === rootRecord.type) throw rootRecord.arg; return this.rval; }, dispatchException: function dispatchException(exception) { if (this.done) throw exception; var context = this; function handle(loc, caught) { return record.type = "throw", record.arg = exception, context.next = loc, caught && (context.method = "next", context.arg = undefined), !!caught; } for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i], record = entry.completion; if ("root" === entry.tryLoc) return handle("end"); if (entry.tryLoc <= this.prev) { var hasCatch = hasOwn.call(entry, "catchLoc"), hasFinally = hasOwn.call(entry, "finallyLoc"); if (hasCatch && hasFinally) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } else if (hasCatch) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); } else { if (!hasFinally) throw new Error("try statement without catch or finally"); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } } } }, abrupt: function abrupt(type, arg) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc <= this.prev && hasOwn.call(entry, "finallyLoc") && this.prev < entry.finallyLoc) { var finallyEntry = entry; break; } } finallyEntry && ("break" === type || "continue" === type) && finallyEntry.tryLoc <= arg && arg <= finallyEntry.finallyLoc && (finallyEntry = null); var record = finallyEntry ? finallyEntry.completion : {}; return record.type = type, record.arg = arg, finallyEntry ? (this.method = "next", this.next = finallyEntry.finallyLoc, ContinueSentinel) : this.complete(record); }, complete: function complete(record, afterLoc) { if ("throw" === record.type) throw record.arg; return "break" === record.type || "continue" === record.type ? this.next = record.arg : "return" === record.type ? (this.rval = this.arg = record.arg, this.method = "return", this.next = "end") : "normal" === record.type && afterLoc && (this.next = afterLoc), ContinueSentinel; }, finish: function finish(finallyLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.finallyLoc === finallyLoc) return this.complete(entry.completion, entry.afterLoc), resetTryEntry(entry), ContinueSentinel; } }, "catch": function _catch(tryLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc === tryLoc) { var record = entry.completion; if ("throw" === record.type) { var thrown = record.arg; resetTryEntry(entry); } return thrown; } } throw new Error("illegal catch attempt"); }, delegateYield: function delegateYield(iterable, resultName, nextLoc) { return this.delegate = { iterator: values(iterable), resultName: resultName, nextLoc: nextLoc }, "next" === this.method && (this.arg = undefined), ContinueSentinel; } }, exports; }
function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }
function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }
function _iterableToArrayLimit(arr, i) { var _i = null == arr ? null : "undefined" != typeof Symbol && arr[Symbol.iterator] || arr["@@iterator"]; if (null != _i) { var _s, _e, _x, _r, _arr = [], _n = !0, _d = !1; try { if (_x = (_i = _i.call(arr)).next, 0 === i) { if (Object(_i) !== _i) return; _n = !1; } else for (; !(_n = (_s = _x.call(_i)).done) && (_arr.push(_s.value), _arr.length !== i); _n = !0); } catch (err) { _d = !0, _e = err; } finally { try { if (!_n && null != _i["return"] && (_r = _i["return"](), Object(_r) !== _r)) return; } finally { if (_d) throw _e; } } return _arr; } }
function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }
document.addEventListener('DOMContentLoaded', function () {
  var _window$Masterstudy;
  var Table = ((_window$Masterstudy = window.Masterstudy) === null || _window$Masterstudy === void 0 ? void 0 : _window$Masterstudy.Table) || window.MasterstudyTable;
  var manageStudentsContainer = document.getElementById('masterstudy-manage-students');
  if (!manageStudentsContainer || typeof Table !== 'function') {
    return;
  }
  var tableContainer = manageStudentsContainer.querySelector('[data-masterstudy-table="manage-students"]');
  var countElement = manageStudentsContainer.querySelector('.masterstudy-manage-students__count-number');
  var alertPopup = document.querySelector('[data-id="masterstudy-manage-students-delete-student"]');
  var pageMain = document.getElementById('main');
  var queryParams = new URLSearchParams(window.location.search);
  var courseId = Number(queryParams.get('course_id') || 0);
  var manageStudentsConfig = window.manage_students_data || {};
  var columns = Array.isArray(manageStudentsConfig.columns) ? manageStudentsConfig.columns : [];
  var studentPublic = Boolean(manageStudentsConfig.student_public);
  var initialSearch = queryParams.get('s') || '';
  var progressText = manageStudentsConfig.progress_text || 'Course progress:';
  var progressCtaText = manageStudentsConfig.progress_cta || 'Progress';
  var searchValue = initialSearch;
  var pendingDelete = {
    studentId: 0,
    subscribedEmail: ''
  };
  var isAdjustingPage = false;
  if (!tableContainer || !courseId || !alertPopup) {
    return;
  }
  if (pageMain) {
    pageMain.classList.add('masterstudy-manage-students__main');
  }
  function setCount() {
    var value = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : '';
    if (countElement) {
      countElement.textContent = value;
    }
  }
  function createStudentNode(row) {
    var _row$student, _row$student2, _row$student3;
    var isMobile = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
    var name = (row === null || row === void 0 || (_row$student = row.student) === null || _row$student === void 0 ? void 0 : _row$student.login) || '';
    var email = (row === null || row === void 0 || (_row$student2 = row.student) === null || _row$student2 === void 0 ? void 0 : _row$student2.email) || '';
    var profileUrl = (row === null || row === void 0 || (_row$student3 = row.student) === null || _row$student3 === void 0 ? void 0 : _row$student3.url) || '';
    var wrapper = document.createElement(studentPublic && profileUrl ? 'a' : 'div');
    var info = document.createElement('div');
    var nameElement = document.createElement('span');
    var emailElement = document.createElement('span');
    wrapper.className = isMobile ? 'masterstudy-table__card-student-row' : 'masterstudy-manage-students__student-row';
    if (!isMobile) {
      wrapper.classList.add('masterstudy-manage-students__student-link');
    }
    if (!studentPublic || !profileUrl) {
      wrapper.classList.add('masterstudy-manage-students__student-link_disabled');
    } else if (wrapper.tagName === 'A') {
      wrapper.href = profileUrl;
    }
    info.className = isMobile ? 'masterstudy-table__card-student' : 'masterstudy-manage-students__student-row-info';
    nameElement.className = isMobile ? 'masterstudy-table__card-name' : 'masterstudy-manage-students__student-row-name';
    emailElement.className = isMobile ? 'masterstudy-table__card-email' : 'masterstudy-manage-students__student-row-email';
    nameElement.textContent = name;
    emailElement.textContent = email;
    info.appendChild(nameElement);
    info.appendChild(emailElement);
    wrapper.appendChild(info);
    return wrapper;
  }
  function createStudentSkeleton(tableInstance) {
    var wrapper = document.createElement('div');
    var avatar = tableInstance.createSkeletonBlock('40px', '40px');
    var info = document.createElement('div');
    var name = tableInstance.createSkeletonBlock('90px', '16px');
    var email = tableInstance.createSkeletonBlock('140px', '14px');
    wrapper.className = 'masterstudy-table__skeleton-student';
    avatar.classList.add('masterstudy-table__skeleton-avatar');
    info.className = 'masterstudy-table__skeleton-student-info';
    info.appendChild(name);
    info.appendChild(email);
    wrapper.appendChild(avatar);
    wrapper.appendChild(info);
    return wrapper;
  }
  function formatStarted() {
    var _row$start_time_forma, _row$start_time_forma2;
    var row = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
    var startDate = (row === null || row === void 0 || (_row$start_time_forma = row.start_time_formatted) === null || _row$start_time_forma === void 0 ? void 0 : _row$start_time_forma.date) || '';
    var startTime = (row === null || row === void 0 || (_row$start_time_forma2 = row.start_time_formatted) === null || _row$start_time_forma2 === void 0 ? void 0 : _row$start_time_forma2.time) || '';
    if (startDate && startTime) {
      return "".concat(startDate, " - ").concat(startTime);
    }
    return startDate || startTime || '';
  }
  function createStartedNode() {
    var row = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
    var value = document.createElement('span');
    value.className = 'masterstudy-manage-students__started';
    value.textContent = formatStarted(row);
    return value;
  }
  function createProgressNode() {
    var row = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
    var isMobile = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
    var subscribedTime = (row === null || row === void 0 ? void 0 : row.subscribed_time) || '';
    if (subscribedTime) {
      return document.createTextNode('');
    }
    var progressPercent = Number((row === null || row === void 0 ? void 0 : row.progress_percent) || 0);
    var wrapper = document.createElement('div');
    var bars = document.createElement('div');
    var barEmpty = document.createElement('span');
    var barFilled = document.createElement('span');
    var value = document.createElement('div');
    var valueTitle = document.createElement('span');
    var valueNumber = document.createElement('span');
    wrapper.className = 'masterstudy-manage-students__progress';
    bars.className = 'masterstudy-progress__bars';
    barEmpty.className = 'masterstudy-progress__bar-empty';
    barFilled.className = 'masterstudy-progress__bar-filled';
    value.className = 'masterstudy-manage-students__progress-value';
    valueTitle.className = 'masterstudy-manage-students__progress-value-title';
    valueNumber.className = 'masterstudy-manage-students__progress-value-number';
    if (isMobile) {
      wrapper.classList.add('masterstudy-manage-students__progress_mobile');
    }
    wrapper.classList.add('masterstudy-progress');
    barFilled.style.width = "".concat(progressPercent, "%");
    valueTitle.textContent = progressText;
    valueNumber.textContent = "".concat(progressPercent, "%");
    bars.appendChild(barEmpty);
    bars.appendChild(barFilled);
    value.appendChild(valueTitle);
    value.appendChild(valueNumber);
    wrapper.appendChild(bars);
    wrapper.appendChild(value);
    return wrapper;
  }
  function createButton(title, classes) {
    var href = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : '#';
    var data = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : {};
    var button = document.createElement('a');
    var label = document.createElement('span');
    button.href = href;
    button.className = "masterstudy-button ".concat(classes);
    label.className = 'masterstudy-button__title';
    label.textContent = title;
    Object.entries(data).forEach(function (_ref) {
      var _ref2 = _slicedToArray(_ref, 2),
        key = _ref2[0],
        value = _ref2[1];
      if (value !== undefined && value !== null && value !== '') {
        button.dataset[key] = value;
      }
    });
    button.appendChild(label);
    return button;
  }
  function createActionsNode() {
    var _row$student4, _row$student5;
    var row = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
    var wrapper = document.createElement('div');
    var progressLink = (row === null || row === void 0 ? void 0 : row.progress_link) || '#';
    var subscribedTime = (row === null || row === void 0 ? void 0 : row.subscribed_time) || '';
    var studentId = Number((row === null || row === void 0 ? void 0 : row.user_id) || (row === null || row === void 0 || (_row$student4 = row.student) === null || _row$student4 === void 0 ? void 0 : _row$student4.id) || 0);
    var deleteButton = createButton('', 'masterstudy-button_style-danger-light masterstudy-button_size-sm masterstudy-manage-students__delete-button', '#', {
      id: 'manage-students-delete',
      courseId: (row === null || row === void 0 ? void 0 : row.course_id) || courseId,
      studentId: studentId,
      subscribedEmail: subscribedTime ? (row === null || row === void 0 || (_row$student5 = row.student) === null || _row$student5 === void 0 ? void 0 : _row$student5.email) || '' : ''
    });
    wrapper.className = 'masterstudy-manage-students__actions';
    if (!subscribedTime) {
      wrapper.appendChild(createButton(progressCtaText, 'masterstudy-button_style-secondary masterstudy-button_size-sm', progressLink, {
        id: 'manage-students-view-progress'
      }));
    }
    wrapper.appendChild(deleteButton);
    return wrapper;
  }
  var table = new Table({
    container: tableContainer,
    autoFetch: false,
    columns: columns,
    api: {
      url: "students/".concat(courseId),
      method: 'GET'
    },
    requestAdapter: function requestAdapter(params, tableInstance) {
      var request = _objectSpread({
        page: tableInstance.currentPage,
        per_page: tableInstance.perPage,
        subscribed: true
      }, params);
      if (tableInstance.sortBy && tableInstance.sortOrder !== 'none') {
        var sortColumn = tableInstance.columns.find(function (column) {
          return column.key === tableInstance.sortBy;
        });
        request.orderby = (sortColumn === null || sortColumn === void 0 ? void 0 : sortColumn.sortKey) || tableInstance.sortBy;
        request.order = tableInstance.sortOrder;
      } else {
        delete request.orderby;
        delete request.order;
      }
      return request;
    },
    responseAdapter: function responseAdapter(json, tableInstance) {
      var total = Number((json === null || json === void 0 ? void 0 : json.total) || 0);
      var totalPages = Math.max(1, Number((json === null || json === void 0 ? void 0 : json.max_pages) || Math.ceil(total / tableInstance.perPage) || 1));
      return {
        rows: Array.isArray(json === null || json === void 0 ? void 0 : json.students) ? json.students : [],
        total: total,
        totalPages: totalPages
      };
    },
    dataMap: function dataMap(row) {
      var _row$student6;
      return {
        student: (row === null || row === void 0 || (_row$student6 = row.student) === null || _row$student6 === void 0 ? void 0 : _row$student6.login) || '',
        subscribed: (row === null || row === void 0 ? void 0 : row.subscribed) || '',
        started: formatStarted(row),
        progress: row !== null && row !== void 0 && row.subscribed_time ? '' : "".concat((row === null || row === void 0 ? void 0 : row.progress_percent) || 0, "%"),
        actions: ''
      };
    },
    renderers: {
      student: function student(_value, row) {
        return createStudentNode(row, false);
      },
      subscribed: function subscribed(value) {
        var subscribed = document.createElement('span');
        subscribed.className = 'masterstudy-manage-students__subscribed';
        subscribed.textContent = value || '';
        return subscribed;
      },
      started: function started(_value, row) {
        return createStartedNode(row);
      },
      progress: function progress(_value, row) {
        return createProgressNode(row, false);
      },
      actions: function actions(_value, row) {
        return createActionsNode(row);
      }
    },
    mobileRenderers: {
      student: function student(_value, row) {
        return createStudentNode(row, true);
      },
      progress: function progress(_value, row) {
        return createProgressNode(row, true);
      },
      actions: function actions(_value, row) {
        return createActionsNode(row);
      }
    },
    skeletonRenderers: {
      student: function student(_column, tableInstance) {
        return createStudentSkeleton(tableInstance);
      }
    },
    emptyText: manageStudentsConfig.empty_text || 'No Students Found.',
    errorText: manageStudentsConfig.error_text || 'Something went wrong. Please try again.',
    retryText: manageStudentsConfig.retry_text || 'Retry'
  });
  table.on('beforeFetch', function () {
    setCount('');
  });
  table.on('afterFetch', function (response) {
    var totalPages = Math.max(1, Number((response === null || response === void 0 ? void 0 : response.max_pages) || 1));
    setCount(table.total || 0);
    if (!isAdjustingPage && table.currentPage > totalPages) {
      isAdjustingPage = true;
      table.currentPage = totalPages;
      table.fetch();
      return;
    }
    isAdjustingPage = false;
  });
  table.setParams(searchValue ? {
    s: searchValue
  } : {}, false);
  table.fetch();
  document.addEventListener('msfieldEvent', function (event) {
    var _event$detail, _event$detail2, _event$detail3;
    if (((_event$detail = event.detail) === null || _event$detail === void 0 ? void 0 : _event$detail.name) !== 's') {
      return;
    }
    var nextSearchValue = ((_event$detail2 = event.detail) === null || _event$detail2 === void 0 ? void 0 : _event$detail2.value) || '';
    if (((_event$detail3 = event.detail) === null || _event$detail3 === void 0 ? void 0 : _event$detail3.event) === 'onload' && nextSearchValue === searchValue) {
      return;
    }
    searchValue = nextSearchValue;
    table.setParams({
      s: searchValue
    }, true).fetch();
  });
  document.addEventListener('msAddStudentEvent', function (event) {
    var _event$detail4;
    if (((_event$detail4 = event.detail) === null || _event$detail4 === void 0 ? void 0 : _event$detail4.progress) !== 100) {
      return;
    }
    table.currentPage = 1;
    table.fetch();
  });
  alertPopup.style.display = 'none';
  function closeAlertPopup(event) {
    if (event) {
      event.preventDefault();
    }
    alertPopup.classList.remove('masterstudy-alert_open');
    alertPopup.style.display = 'none';
  }
  function deleteStudent(_x2) {
    return _deleteStudent.apply(this, arguments);
  }
  function _deleteStudent() {
    _deleteStudent = _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee(event) {
      var deleteUrl, requestOptions, response, data;
      return _regeneratorRuntime().wrap(function _callee$(_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            event.preventDefault();
            deleteUrl = "".concat(ms_lms_resturl, "/student/").concat(courseId, "/").concat(pendingDelete.studentId);
            requestOptions = {
              method: 'DELETE',
              headers: {
                'X-WP-Nonce': ms_lms_nonce,
                'Content-Type': 'application/json'
              }
            };
            if (pendingDelete.subscribedEmail) {
              requestOptions.body = JSON.stringify({
                subscribed_email: pendingDelete.subscribedEmail
              });
            }
            _context.prev = 4;
            _context.next = 7;
            return fetch(deleteUrl, requestOptions);
          case 7:
            response = _context.sent;
            if (response.ok) {
              _context.next = 10;
              break;
            }
            throw new Error('Delete failed');
          case 10:
            _context.next = 12;
            return response.json();
          case 12:
            data = _context.sent;
            closeAlertPopup();
            if ((data === null || data === void 0 ? void 0 : data.status) === 'ok') {
              table.fetch();
            }
            _context.next = 20;
            break;
          case 17:
            _context.prev = 17;
            _context.t0 = _context["catch"](4);
            console.error(_context.t0);
          case 20:
          case "end":
            return _context.stop();
        }
      }, _callee, null, [[4, 17]]);
    }));
    return _deleteStudent.apply(this, arguments);
  }
  document.addEventListener('click', function (event) {
    var deleteTrigger = event.target.closest('[data-id="manage-students-delete"]');
    var closeTrigger = event.target.closest('[data-id="cancel"], .masterstudy-alert__header-close');
    var submitTrigger = event.target.closest('[data-id="submit"]');
    if (deleteTrigger) {
      event.preventDefault();
      pendingDelete = {
        studentId: Number(deleteTrigger.dataset.studentId || 0),
        subscribedEmail: deleteTrigger.dataset.subscribedEmail || ''
      };
      alertPopup.style.display = 'flex';
      alertPopup.classList.add('masterstudy-alert_open');
      return;
    }
    if (submitTrigger && alertPopup.contains(submitTrigger)) {
      deleteStudent(event);
      return;
    }
    if (closeTrigger && alertPopup.contains(closeTrigger)) {
      closeAlertPopup(event);
    }
  });
});