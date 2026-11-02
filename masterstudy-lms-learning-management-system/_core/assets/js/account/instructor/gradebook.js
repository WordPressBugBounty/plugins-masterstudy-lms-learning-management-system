"use strict";

function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _regeneratorRuntime() { "use strict"; /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return exports; }; var exports = {}, Op = Object.prototype, hasOwn = Op.hasOwnProperty, defineProperty = Object.defineProperty || function (obj, key, desc) { obj[key] = desc.value; }, $Symbol = "function" == typeof Symbol ? Symbol : {}, iteratorSymbol = $Symbol.iterator || "@@iterator", asyncIteratorSymbol = $Symbol.asyncIterator || "@@asyncIterator", toStringTagSymbol = $Symbol.toStringTag || "@@toStringTag"; function define(obj, key, value) { return Object.defineProperty(obj, key, { value: value, enumerable: !0, configurable: !0, writable: !0 }), obj[key]; } try { define({}, ""); } catch (err) { define = function define(obj, key, value) { return obj[key] = value; }; } function wrap(innerFn, outerFn, self, tryLocsList) { var protoGenerator = outerFn && outerFn.prototype instanceof Generator ? outerFn : Generator, generator = Object.create(protoGenerator.prototype), context = new Context(tryLocsList || []); return defineProperty(generator, "_invoke", { value: makeInvokeMethod(innerFn, self, context) }), generator; } function tryCatch(fn, obj, arg) { try { return { type: "normal", arg: fn.call(obj, arg) }; } catch (err) { return { type: "throw", arg: err }; } } exports.wrap = wrap; var ContinueSentinel = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var IteratorPrototype = {}; define(IteratorPrototype, iteratorSymbol, function () { return this; }); var getProto = Object.getPrototypeOf, NativeIteratorPrototype = getProto && getProto(getProto(values([]))); NativeIteratorPrototype && NativeIteratorPrototype !== Op && hasOwn.call(NativeIteratorPrototype, iteratorSymbol) && (IteratorPrototype = NativeIteratorPrototype); var Gp = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(IteratorPrototype); function defineIteratorMethods(prototype) { ["next", "throw", "return"].forEach(function (method) { define(prototype, method, function (arg) { return this._invoke(method, arg); }); }); } function AsyncIterator(generator, PromiseImpl) { function invoke(method, arg, resolve, reject) { var record = tryCatch(generator[method], generator, arg); if ("throw" !== record.type) { var result = record.arg, value = result.value; return value && "object" == _typeof(value) && hasOwn.call(value, "__await") ? PromiseImpl.resolve(value.__await).then(function (value) { invoke("next", value, resolve, reject); }, function (err) { invoke("throw", err, resolve, reject); }) : PromiseImpl.resolve(value).then(function (unwrapped) { result.value = unwrapped, resolve(result); }, function (error) { return invoke("throw", error, resolve, reject); }); } reject(record.arg); } var previousPromise; defineProperty(this, "_invoke", { value: function value(method, arg) { function callInvokeWithMethodAndArg() { return new PromiseImpl(function (resolve, reject) { invoke(method, arg, resolve, reject); }); } return previousPromise = previousPromise ? previousPromise.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); } }); } function makeInvokeMethod(innerFn, self, context) { var state = "suspendedStart"; return function (method, arg) { if ("executing" === state) throw new Error("Generator is already running"); if ("completed" === state) { if ("throw" === method) throw arg; return doneResult(); } for (context.method = method, context.arg = arg;;) { var delegate = context.delegate; if (delegate) { var delegateResult = maybeInvokeDelegate(delegate, context); if (delegateResult) { if (delegateResult === ContinueSentinel) continue; return delegateResult; } } if ("next" === context.method) context.sent = context._sent = context.arg;else if ("throw" === context.method) { if ("suspendedStart" === state) throw state = "completed", context.arg; context.dispatchException(context.arg); } else "return" === context.method && context.abrupt("return", context.arg); state = "executing"; var record = tryCatch(innerFn, self, context); if ("normal" === record.type) { if (state = context.done ? "completed" : "suspendedYield", record.arg === ContinueSentinel) continue; return { value: record.arg, done: context.done }; } "throw" === record.type && (state = "completed", context.method = "throw", context.arg = record.arg); } }; } function maybeInvokeDelegate(delegate, context) { var methodName = context.method, method = delegate.iterator[methodName]; if (undefined === method) return context.delegate = null, "throw" === methodName && delegate.iterator["return"] && (context.method = "return", context.arg = undefined, maybeInvokeDelegate(delegate, context), "throw" === context.method) || "return" !== methodName && (context.method = "throw", context.arg = new TypeError("The iterator does not provide a '" + methodName + "' method")), ContinueSentinel; var record = tryCatch(method, delegate.iterator, context.arg); if ("throw" === record.type) return context.method = "throw", context.arg = record.arg, context.delegate = null, ContinueSentinel; var info = record.arg; return info ? info.done ? (context[delegate.resultName] = info.value, context.next = delegate.nextLoc, "return" !== context.method && (context.method = "next", context.arg = undefined), context.delegate = null, ContinueSentinel) : info : (context.method = "throw", context.arg = new TypeError("iterator result is not an object"), context.delegate = null, ContinueSentinel); } function pushTryEntry(locs) { var entry = { tryLoc: locs[0] }; 1 in locs && (entry.catchLoc = locs[1]), 2 in locs && (entry.finallyLoc = locs[2], entry.afterLoc = locs[3]), this.tryEntries.push(entry); } function resetTryEntry(entry) { var record = entry.completion || {}; record.type = "normal", delete record.arg, entry.completion = record; } function Context(tryLocsList) { this.tryEntries = [{ tryLoc: "root" }], tryLocsList.forEach(pushTryEntry, this), this.reset(!0); } function values(iterable) { if (iterable) { var iteratorMethod = iterable[iteratorSymbol]; if (iteratorMethod) return iteratorMethod.call(iterable); if ("function" == typeof iterable.next) return iterable; if (!isNaN(iterable.length)) { var i = -1, next = function next() { for (; ++i < iterable.length;) if (hasOwn.call(iterable, i)) return next.value = iterable[i], next.done = !1, next; return next.value = undefined, next.done = !0, next; }; return next.next = next; } } return { next: doneResult }; } function doneResult() { return { value: undefined, done: !0 }; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, defineProperty(Gp, "constructor", { value: GeneratorFunctionPrototype, configurable: !0 }), defineProperty(GeneratorFunctionPrototype, "constructor", { value: GeneratorFunction, configurable: !0 }), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, toStringTagSymbol, "GeneratorFunction"), exports.isGeneratorFunction = function (genFun) { var ctor = "function" == typeof genFun && genFun.constructor; return !!ctor && (ctor === GeneratorFunction || "GeneratorFunction" === (ctor.displayName || ctor.name)); }, exports.mark = function (genFun) { return Object.setPrototypeOf ? Object.setPrototypeOf(genFun, GeneratorFunctionPrototype) : (genFun.__proto__ = GeneratorFunctionPrototype, define(genFun, toStringTagSymbol, "GeneratorFunction")), genFun.prototype = Object.create(Gp), genFun; }, exports.awrap = function (arg) { return { __await: arg }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, asyncIteratorSymbol, function () { return this; }), exports.AsyncIterator = AsyncIterator, exports.async = function (innerFn, outerFn, self, tryLocsList, PromiseImpl) { void 0 === PromiseImpl && (PromiseImpl = Promise); var iter = new AsyncIterator(wrap(innerFn, outerFn, self, tryLocsList), PromiseImpl); return exports.isGeneratorFunction(outerFn) ? iter : iter.next().then(function (result) { return result.done ? result.value : iter.next(); }); }, defineIteratorMethods(Gp), define(Gp, toStringTagSymbol, "Generator"), define(Gp, iteratorSymbol, function () { return this; }), define(Gp, "toString", function () { return "[object Generator]"; }), exports.keys = function (val) { var object = Object(val), keys = []; for (var key in object) keys.push(key); return keys.reverse(), function next() { for (; keys.length;) { var key = keys.pop(); if (key in object) return next.value = key, next.done = !1, next; } return next.done = !0, next; }; }, exports.values = values, Context.prototype = { constructor: Context, reset: function reset(skipTempReset) { if (this.prev = 0, this.next = 0, this.sent = this._sent = undefined, this.done = !1, this.delegate = null, this.method = "next", this.arg = undefined, this.tryEntries.forEach(resetTryEntry), !skipTempReset) for (var name in this) "t" === name.charAt(0) && hasOwn.call(this, name) && !isNaN(+name.slice(1)) && (this[name] = undefined); }, stop: function stop() { this.done = !0; var rootRecord = this.tryEntries[0].completion; if ("throw" === rootRecord.type) throw rootRecord.arg; return this.rval; }, dispatchException: function dispatchException(exception) { if (this.done) throw exception; var context = this; function handle(loc, caught) { return record.type = "throw", record.arg = exception, context.next = loc, caught && (context.method = "next", context.arg = undefined), !!caught; } for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i], record = entry.completion; if ("root" === entry.tryLoc) return handle("end"); if (entry.tryLoc <= this.prev) { var hasCatch = hasOwn.call(entry, "catchLoc"), hasFinally = hasOwn.call(entry, "finallyLoc"); if (hasCatch && hasFinally) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } else if (hasCatch) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); } else { if (!hasFinally) throw new Error("try statement without catch or finally"); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } } } }, abrupt: function abrupt(type, arg) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc <= this.prev && hasOwn.call(entry, "finallyLoc") && this.prev < entry.finallyLoc) { var finallyEntry = entry; break; } } finallyEntry && ("break" === type || "continue" === type) && finallyEntry.tryLoc <= arg && arg <= finallyEntry.finallyLoc && (finallyEntry = null); var record = finallyEntry ? finallyEntry.completion : {}; return record.type = type, record.arg = arg, finallyEntry ? (this.method = "next", this.next = finallyEntry.finallyLoc, ContinueSentinel) : this.complete(record); }, complete: function complete(record, afterLoc) { if ("throw" === record.type) throw record.arg; return "break" === record.type || "continue" === record.type ? this.next = record.arg : "return" === record.type ? (this.rval = this.arg = record.arg, this.method = "return", this.next = "end") : "normal" === record.type && afterLoc && (this.next = afterLoc), ContinueSentinel; }, finish: function finish(finallyLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.finallyLoc === finallyLoc) return this.complete(entry.completion, entry.afterLoc), resetTryEntry(entry), ContinueSentinel; } }, "catch": function _catch(tryLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc === tryLoc) { var record = entry.completion; if ("throw" === record.type) { var thrown = record.arg; resetTryEntry(entry); } return thrown; } } throw new Error("illegal catch attempt"); }, delegateYield: function delegateYield(iterable, resultName, nextLoc) { return this.delegate = { iterator: values(iterable), resultName: resultName, nextLoc: nextLoc }, "next" === this.method && (this.arg = undefined), ContinueSentinel; } }, exports; }
function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
document.addEventListener('DOMContentLoaded', function () {
  var _window$Masterstudy;
  var Table = ((_window$Masterstudy = window.Masterstudy) === null || _window$Masterstudy === void 0 ? void 0 : _window$Masterstudy.Table) || window.MasterstudyTable;
  if (typeof Table !== 'function') {
    return;
  }
  var abortController = new AbortController();
  var courseId = null;
  var studentsCountStat = document.querySelector('.masterstudy-account-gradebook__stat-students-count');
  var avgProgressStat = document.querySelector('.masterstudy-account-gradebook__stat-avg-progress');
  var quizzesStat = document.querySelector('.masterstudy-account-gradebook__stat-quizzes');
  var lessonsStat = document.querySelector('.masterstudy-account-gradebook__stat-lessons');
  var assignmentsStat = document.querySelector('.masterstudy-account-gradebook__stat-assignments');
  var subsEnrollStat = document.querySelector('.masterstudy-account-gradebook__stat-subs-enroll');
  var gradebookTableContainer = document.querySelector('[data-masterstudy-table="gradebook-students"]');
  if (!gradebookTableContainer) {
    return;
  }
  var gradebookConfig = window.gradebook_data || {};
  function setStatValue(element) {
    var value = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '';
    if (element) {
      element.textContent = value;
    }
  }
  function clearStats() {
    setStatValue(studentsCountStat);
    setStatValue(avgProgressStat);
    setStatValue(quizzesStat);
    setStatValue(lessonsStat);
    setStatValue(subsEnrollStat);
    setStatValue(assignmentsStat);
  }
  function toggleStatLoading(element, isLoading) {
    if (element) {
      element.classList.toggle('loading', isLoading);
    }
  }
  function setIsStatsLoading(isLoading) {
    if (isLoading) {
      clearStats();
    }
    toggleStatLoading(studentsCountStat, isLoading);
    toggleStatLoading(avgProgressStat, isLoading);
    toggleStatLoading(quizzesStat, isLoading);
    toggleStatLoading(lessonsStat, isLoading);
    toggleStatLoading(subsEnrollStat, isLoading);
    toggleStatLoading(assignmentsStat, isLoading);
  }
  function renderCourseStats(stats) {
    setStatValue(studentsCountStat, stats.course_students || 0);
    setStatValue(avgProgressStat, "".concat(stats.course_average_progress || 0, "%"));
    setStatValue(quizzesStat, "".concat(stats.course_quizzes_procents || 0, "%"));
    setStatValue(lessonsStat, "".concat(stats.course_lessons_procents || 0, "%"));
    setStatValue(subsEnrollStat, stats.subscriptions || 0);
    setStatValue(assignmentsStat, "".concat(stats.course_assignments_procents || 0, "%"));
  }
  function fetchCourseStats(_x) {
    return _fetchCourseStats.apply(this, arguments);
  }
  function _fetchCourseStats() {
    _fetchCourseStats = _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee(selectedCourseId) {
      var _window$stm_lms_pro_n;
      var courseInfoNonce, url, response, data;
      return _regeneratorRuntime().wrap(function _callee$(_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            courseInfoNonce = (_window$stm_lms_pro_n = window.stm_lms_pro_nonces) === null || _window$stm_lms_pro_n === void 0 ? void 0 : _window$stm_lms_pro_n.stm_lms_get_course_info;
            if (!(!selectedCourseId || !window.stm_lms_ajaxurl || !courseInfoNonce)) {
              _context.next = 4;
              break;
            }
            setIsStatsLoading(false);
            return _context.abrupt("return");
          case 4:
            url = "".concat(window.stm_lms_ajaxurl, "?action=stm_lms_get_course_info&nonce=").concat(encodeURIComponent(courseInfoNonce), "&course_id=").concat(encodeURIComponent(selectedCourseId));
            setIsStatsLoading(true);
            _context.prev = 6;
            _context.next = 9;
            return fetch(url, {
              method: 'GET',
              signal: abortController.signal
            });
          case 9:
            response = _context.sent;
            if (response.ok) {
              _context.next = 12;
              break;
            }
            return _context.abrupt("return");
          case 12:
            _context.next = 14;
            return response.json();
          case 14:
            data = _context.sent;
            renderCourseStats(data);
            _context.next = 21;
            break;
          case 18:
            _context.prev = 18;
            _context.t0 = _context["catch"](6);
            if (_context.t0.name !== 'AbortError') {
              console.error(_context.t0);
            }
          case 21:
            _context.prev = 21;
            setIsStatsLoading(false);
            return _context.finish(21);
          case 24:
          case "end":
            return _context.stop();
        }
      }, _callee, null, [[6, 18, 21, 24]]);
    }));
    return _fetchCourseStats.apply(this, arguments);
  }
  function createStudentNode(row) {
    var _row$user_data, _row$user_data2, _row$user_data3;
    var isMobile = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
    var avatarUrl = (row === null || row === void 0 || (_row$user_data = row.user_data) === null || _row$user_data === void 0 ? void 0 : _row$user_data.avatar_url) || '';
    var name = (row === null || row === void 0 || (_row$user_data2 = row.user_data) === null || _row$user_data2 === void 0 ? void 0 : _row$user_data2.login) || '';
    var email = (row === null || row === void 0 || (_row$user_data3 = row.user_data) === null || _row$user_data3 === void 0 ? void 0 : _row$user_data3.email) || '';
    var wrapper = document.createElement('div');
    var image = document.createElement('img');
    var info = document.createElement('div');
    var nameElement = document.createElement('span');
    var emailElement = document.createElement('span');
    wrapper.className = isMobile ? 'masterstudy-table__card-student-row' : 'masterstudy-account-gradebook__student-row';
    image.className = isMobile ? 'masterstudy-table__card-avatar' : '';
    image.alt = name;
    if (avatarUrl) {
      image.src = avatarUrl;
    }
    info.className = isMobile ? 'masterstudy-table__card-student' : 'masterstudy-account-gradebook__student-row-info';
    nameElement.className = isMobile ? 'masterstudy-table__card-name' : 'masterstudy-account-gradebook__student-row-name';
    emailElement.className = isMobile ? 'masterstudy-table__card-email' : 'masterstudy-account-gradebook__student-row-email';
    nameElement.textContent = name;
    emailElement.textContent = email;
    info.appendChild(nameElement);
    info.appendChild(emailElement);
    wrapper.appendChild(image);
    wrapper.appendChild(info);
    return wrapper;
  }
  function createStudentSkeleton(table) {
    var wrapper = document.createElement('div');
    var avatar = table.createSkeletonBlock('40px', '40px');
    var info = document.createElement('div');
    var name = table.createSkeletonBlock('90px', '16px');
    var email = table.createSkeletonBlock('140px', '14px');
    wrapper.className = 'masterstudy-table__skeleton-student';
    avatar.classList.add('masterstudy-table__skeleton-avatar');
    info.className = 'masterstudy-table__skeleton-student-info';
    info.appendChild(name);
    info.appendChild(email);
    wrapper.appendChild(avatar);
    wrapper.appendChild(info);
    return wrapper;
  }
  var table = new Table({
    container: gradebookTableContainer,
    autoFetch: false,
    adapter: 'datatables',
    columns: gradebookConfig.columns || [],
    api: {
      url: 'gradebook/students/list',
      method: 'POST',
      params: function params() {
        return {
          course_id: courseId
        };
      }
    },
    dataMap: function dataMap(row) {
      var _row$user_data4, _row$lessons_progress, _row$curriculum, _row$quizzes_progress, _row$curriculum2, _row$assignments_prog, _row$curriculum3;
      return {
        student: (row === null || row === void 0 || (_row$user_data4 = row.user_data) === null || _row$user_data4 === void 0 ? void 0 : _row$user_data4.login) || '',
        lessons: "".concat((row === null || row === void 0 || (_row$lessons_progress = row.lessons_progress) === null || _row$lessons_progress === void 0 ? void 0 : _row$lessons_progress.count) || 0, "/").concat((row === null || row === void 0 || (_row$curriculum = row.curriculum) === null || _row$curriculum === void 0 ? void 0 : _row$curriculum.lessons) || 0),
        quizzes: "".concat((row === null || row === void 0 || (_row$quizzes_progress = row.quizzes_progress) === null || _row$quizzes_progress === void 0 ? void 0 : _row$quizzes_progress.count) || 0, "/").concat((row === null || row === void 0 || (_row$curriculum2 = row.curriculum) === null || _row$curriculum2 === void 0 ? void 0 : _row$curriculum2.quizzes) || 0),
        assignments: "".concat((row === null || row === void 0 || (_row$assignments_prog = row.assignments_progress) === null || _row$assignments_prog === void 0 ? void 0 : _row$assignments_prog.count) || 0, "/").concat((row === null || row === void 0 || (_row$curriculum3 = row.curriculum) === null || _row$curriculum3 === void 0 ? void 0 : _row$curriculum3.assignments) || 0),
        progress: "".concat((row === null || row === void 0 ? void 0 : row.progress_percent) || 0, "%"),
        started: (row === null || row === void 0 ? void 0 : row.start_date) || ''
      };
    },
    renderers: {
      student: function student(_value, row) {
        return createStudentNode(row, false);
      }
    },
    skeletonRenderers: {
      student: function student(_column, tableInstance) {
        return createStudentSkeleton(tableInstance);
      }
    }
  });
  table.clear({
    showEmpty: false
  });
  document.addEventListener('msfieldEvent', function (event) {
    var _event$detail;
    if (((_event$detail = event.detail) === null || _event$detail === void 0 ? void 0 : _event$detail.name) !== 'masterstudy-account-gradebook__course-select-input') {
      return;
    }
    abortController.abort();
    abortController = new AbortController();
    courseId = event.detail.value ? Number(event.detail.value) : null;
    if (!courseId) {
      table.clear({
        showEmpty: false
      });
      setIsStatsLoading(false);
      clearStats();
      return;
    }
    table.setParams({
      course_id: courseId
    }, true).fetch();
    fetchCourseStats(courseId);
  });
});