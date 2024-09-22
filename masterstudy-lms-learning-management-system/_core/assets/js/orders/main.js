"use strict";

function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _regeneratorRuntime() { "use strict"; /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return exports; }; var exports = {}, Op = Object.prototype, hasOwn = Op.hasOwnProperty, defineProperty = Object.defineProperty || function (obj, key, desc) { obj[key] = desc.value; }, $Symbol = "function" == typeof Symbol ? Symbol : {}, iteratorSymbol = $Symbol.iterator || "@@iterator", asyncIteratorSymbol = $Symbol.asyncIterator || "@@asyncIterator", toStringTagSymbol = $Symbol.toStringTag || "@@toStringTag"; function define(obj, key, value) { return Object.defineProperty(obj, key, { value: value, enumerable: !0, configurable: !0, writable: !0 }), obj[key]; } try { define({}, ""); } catch (err) { define = function define(obj, key, value) { return obj[key] = value; }; } function wrap(innerFn, outerFn, self, tryLocsList) { var protoGenerator = outerFn && outerFn.prototype instanceof Generator ? outerFn : Generator, generator = Object.create(protoGenerator.prototype), context = new Context(tryLocsList || []); return defineProperty(generator, "_invoke", { value: makeInvokeMethod(innerFn, self, context) }), generator; } function tryCatch(fn, obj, arg) { try { return { type: "normal", arg: fn.call(obj, arg) }; } catch (err) { return { type: "throw", arg: err }; } } exports.wrap = wrap; var ContinueSentinel = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var IteratorPrototype = {}; define(IteratorPrototype, iteratorSymbol, function () { return this; }); var getProto = Object.getPrototypeOf, NativeIteratorPrototype = getProto && getProto(getProto(values([]))); NativeIteratorPrototype && NativeIteratorPrototype !== Op && hasOwn.call(NativeIteratorPrototype, iteratorSymbol) && (IteratorPrototype = NativeIteratorPrototype); var Gp = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(IteratorPrototype); function defineIteratorMethods(prototype) { ["next", "throw", "return"].forEach(function (method) { define(prototype, method, function (arg) { return this._invoke(method, arg); }); }); } function AsyncIterator(generator, PromiseImpl) { function invoke(method, arg, resolve, reject) { var record = tryCatch(generator[method], generator, arg); if ("throw" !== record.type) { var result = record.arg, value = result.value; return value && "object" == _typeof(value) && hasOwn.call(value, "__await") ? PromiseImpl.resolve(value.__await).then(function (value) { invoke("next", value, resolve, reject); }, function (err) { invoke("throw", err, resolve, reject); }) : PromiseImpl.resolve(value).then(function (unwrapped) { result.value = unwrapped, resolve(result); }, function (error) { return invoke("throw", error, resolve, reject); }); } reject(record.arg); } var previousPromise; defineProperty(this, "_invoke", { value: function value(method, arg) { function callInvokeWithMethodAndArg() { return new PromiseImpl(function (resolve, reject) { invoke(method, arg, resolve, reject); }); } return previousPromise = previousPromise ? previousPromise.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); } }); } function makeInvokeMethod(innerFn, self, context) { var state = "suspendedStart"; return function (method, arg) { if ("executing" === state) throw new Error("Generator is already running"); if ("completed" === state) { if ("throw" === method) throw arg; return doneResult(); } for (context.method = method, context.arg = arg;;) { var delegate = context.delegate; if (delegate) { var delegateResult = maybeInvokeDelegate(delegate, context); if (delegateResult) { if (delegateResult === ContinueSentinel) continue; return delegateResult; } } if ("next" === context.method) context.sent = context._sent = context.arg;else if ("throw" === context.method) { if ("suspendedStart" === state) throw state = "completed", context.arg; context.dispatchException(context.arg); } else "return" === context.method && context.abrupt("return", context.arg); state = "executing"; var record = tryCatch(innerFn, self, context); if ("normal" === record.type) { if (state = context.done ? "completed" : "suspendedYield", record.arg === ContinueSentinel) continue; return { value: record.arg, done: context.done }; } "throw" === record.type && (state = "completed", context.method = "throw", context.arg = record.arg); } }; } function maybeInvokeDelegate(delegate, context) { var methodName = context.method, method = delegate.iterator[methodName]; if (undefined === method) return context.delegate = null, "throw" === methodName && delegate.iterator["return"] && (context.method = "return", context.arg = undefined, maybeInvokeDelegate(delegate, context), "throw" === context.method) || "return" !== methodName && (context.method = "throw", context.arg = new TypeError("The iterator does not provide a '" + methodName + "' method")), ContinueSentinel; var record = tryCatch(method, delegate.iterator, context.arg); if ("throw" === record.type) return context.method = "throw", context.arg = record.arg, context.delegate = null, ContinueSentinel; var info = record.arg; return info ? info.done ? (context[delegate.resultName] = info.value, context.next = delegate.nextLoc, "return" !== context.method && (context.method = "next", context.arg = undefined), context.delegate = null, ContinueSentinel) : info : (context.method = "throw", context.arg = new TypeError("iterator result is not an object"), context.delegate = null, ContinueSentinel); } function pushTryEntry(locs) { var entry = { tryLoc: locs[0] }; 1 in locs && (entry.catchLoc = locs[1]), 2 in locs && (entry.finallyLoc = locs[2], entry.afterLoc = locs[3]), this.tryEntries.push(entry); } function resetTryEntry(entry) { var record = entry.completion || {}; record.type = "normal", delete record.arg, entry.completion = record; } function Context(tryLocsList) { this.tryEntries = [{ tryLoc: "root" }], tryLocsList.forEach(pushTryEntry, this), this.reset(!0); } function values(iterable) { if (iterable) { var iteratorMethod = iterable[iteratorSymbol]; if (iteratorMethod) return iteratorMethod.call(iterable); if ("function" == typeof iterable.next) return iterable; if (!isNaN(iterable.length)) { var i = -1, next = function next() { for (; ++i < iterable.length;) if (hasOwn.call(iterable, i)) return next.value = iterable[i], next.done = !1, next; return next.value = undefined, next.done = !0, next; }; return next.next = next; } } return { next: doneResult }; } function doneResult() { return { value: undefined, done: !0 }; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, defineProperty(Gp, "constructor", { value: GeneratorFunctionPrototype, configurable: !0 }), defineProperty(GeneratorFunctionPrototype, "constructor", { value: GeneratorFunction, configurable: !0 }), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, toStringTagSymbol, "GeneratorFunction"), exports.isGeneratorFunction = function (genFun) { var ctor = "function" == typeof genFun && genFun.constructor; return !!ctor && (ctor === GeneratorFunction || "GeneratorFunction" === (ctor.displayName || ctor.name)); }, exports.mark = function (genFun) { return Object.setPrototypeOf ? Object.setPrototypeOf(genFun, GeneratorFunctionPrototype) : (genFun.__proto__ = GeneratorFunctionPrototype, define(genFun, toStringTagSymbol, "GeneratorFunction")), genFun.prototype = Object.create(Gp), genFun; }, exports.awrap = function (arg) { return { __await: arg }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, asyncIteratorSymbol, function () { return this; }), exports.AsyncIterator = AsyncIterator, exports.async = function (innerFn, outerFn, self, tryLocsList, PromiseImpl) { void 0 === PromiseImpl && (PromiseImpl = Promise); var iter = new AsyncIterator(wrap(innerFn, outerFn, self, tryLocsList), PromiseImpl); return exports.isGeneratorFunction(outerFn) ? iter : iter.next().then(function (result) { return result.done ? result.value : iter.next(); }); }, defineIteratorMethods(Gp), define(Gp, toStringTagSymbol, "Generator"), define(Gp, iteratorSymbol, function () { return this; }), define(Gp, "toString", function () { return "[object Generator]"; }), exports.keys = function (val) { var object = Object(val), keys = []; for (var key in object) keys.push(key); return keys.reverse(), function next() { for (; keys.length;) { var key = keys.pop(); if (key in object) return next.value = key, next.done = !1, next; } return next.done = !0, next; }; }, exports.values = values, Context.prototype = { constructor: Context, reset: function reset(skipTempReset) { if (this.prev = 0, this.next = 0, this.sent = this._sent = undefined, this.done = !1, this.delegate = null, this.method = "next", this.arg = undefined, this.tryEntries.forEach(resetTryEntry), !skipTempReset) for (var name in this) "t" === name.charAt(0) && hasOwn.call(this, name) && !isNaN(+name.slice(1)) && (this[name] = undefined); }, stop: function stop() { this.done = !0; var rootRecord = this.tryEntries[0].completion; if ("throw" === rootRecord.type) throw rootRecord.arg; return this.rval; }, dispatchException: function dispatchException(exception) { if (this.done) throw exception; var context = this; function handle(loc, caught) { return record.type = "throw", record.arg = exception, context.next = loc, caught && (context.method = "next", context.arg = undefined), !!caught; } for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i], record = entry.completion; if ("root" === entry.tryLoc) return handle("end"); if (entry.tryLoc <= this.prev) { var hasCatch = hasOwn.call(entry, "catchLoc"), hasFinally = hasOwn.call(entry, "finallyLoc"); if (hasCatch && hasFinally) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } else if (hasCatch) { if (this.prev < entry.catchLoc) return handle(entry.catchLoc, !0); } else { if (!hasFinally) throw new Error("try statement without catch or finally"); if (this.prev < entry.finallyLoc) return handle(entry.finallyLoc); } } } }, abrupt: function abrupt(type, arg) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc <= this.prev && hasOwn.call(entry, "finallyLoc") && this.prev < entry.finallyLoc) { var finallyEntry = entry; break; } } finallyEntry && ("break" === type || "continue" === type) && finallyEntry.tryLoc <= arg && arg <= finallyEntry.finallyLoc && (finallyEntry = null); var record = finallyEntry ? finallyEntry.completion : {}; return record.type = type, record.arg = arg, finallyEntry ? (this.method = "next", this.next = finallyEntry.finallyLoc, ContinueSentinel) : this.complete(record); }, complete: function complete(record, afterLoc) { if ("throw" === record.type) throw record.arg; return "break" === record.type || "continue" === record.type ? this.next = record.arg : "return" === record.type ? (this.rval = this.arg = record.arg, this.method = "return", this.next = "end") : "normal" === record.type && afterLoc && (this.next = afterLoc), ContinueSentinel; }, finish: function finish(finallyLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.finallyLoc === finallyLoc) return this.complete(entry.completion, entry.afterLoc), resetTryEntry(entry), ContinueSentinel; } }, "catch": function _catch(tryLoc) { for (var i = this.tryEntries.length - 1; i >= 0; --i) { var entry = this.tryEntries[i]; if (entry.tryLoc === tryLoc) { var record = entry.completion; if ("throw" === record.type) { var thrown = record.arg; resetTryEntry(entry); } return thrown; } } throw new Error("illegal catch attempt"); }, delegateYield: function delegateYield(iterable, resultName, nextLoc) { return this.delegate = { iterator: values(iterable), resultName: resultName, nextLoc: nextLoc }, "next" === this.method && (this.arg = undefined), ContinueSentinel; } }, exports; }
function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
(function ($) {
  $(document).ready(function () {
    perPageOrders();
    fetchOrders();
  });

  //Function to retrieve data via API
  function fetchOrders(_x) {
    return _fetchOrders.apply(this, arguments);
  } //Function to update the page count request
  function _fetchOrders() {
    _fetchOrders = _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee(perPage) {
      var currentPage,
        apiUrl,
        queryParams,
        ordersContainer,
        response,
        data,
        orders,
        orderHtml,
        _args = arguments;
      return _regeneratorRuntime().wrap(function _callee$(_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            currentPage = _args.length > 1 && _args[1] !== undefined ? _args[1] : 1;
            apiUrl = "".concat(ms_lms_resturl, "/orders");
            queryParams = [];
            if (perPage !== undefined) {
              queryParams.push("per_page=".concat(perPage));
            }
            if (currentPage !== undefined) {
              queryParams.push("current_page=".concat(currentPage));
            }
            if (queryParams.length > 0) {
              apiUrl += '?' + queryParams.join('&');
            }

            //Add animation for container
            ordersContainer = $(".masterstudy-orders-container");
            ordersContainer.addClass("orders-loading");
            _context.prev = 8;
            _context.next = 11;
            return fetch(apiUrl, {
              method: "GET",
              headers: {
                "X-WP-Nonce": ms_lms_nonce,
                "Content-Type": "application/json"
              }
            });
          case 11:
            response = _context.sent;
            if (response.ok) {
              _context.next = 14;
              break;
            }
            throw new Error("HTTP error! status: ".concat(response.status));
          case 14:
            _context.next = 16;
            return response.json();
          case 16:
            data = _context.sent;
            //Remove animation for container
            ordersContainer.css("height", "auto").removeClass("orders-loading");
            $(".masterstudy-orders .stm_lms_user_info_top h3").html(function (_, currentHtml) {
              return currentHtml.replace(/<span>.*<\/span>/, "") + " <span>" + data.total_orders + "</span>";
            });

            //Update pagination data
            updatePagination(data.pages, currentPage);
            orders = data.orders;
            if (orders && orders.length > 0) {
              if (Array.isArray(orders)) {
                orders.forEach(function (order) {
                  var template = document.getElementById("masterstudy-order-template");
                  var clone = template.content.cloneNode(true);
                  $(clone).find("[data-order-id]").text("#".concat(order.id));
                  $(clone).find("[data-order-status]").text("".concat(order.status)).addClass("".concat(order.status));
                  $(clone).find("[data-order-date]").text("".concat(order.date_formatted));
                  $(clone).find("[data-order-payment]").text(order.payment_code === 'wire_transfer' ? 'Wire transfer' : order.payment_code);
                  var _loop = function _loop(key) {
                    if (order.cart_items.hasOwnProperty(key)) {
                      var item = order.cart_items[key];
                      var matchingItem = order.items.find(function (i) {
                        return i.item_id === key;
                      });
                      var additionalInfo = "";
                      if (matchingItem) {
                        if (matchingItem.enterprise && matchingItem.enterprise !== "0") {
                          additionalInfo = "<span class=\"order-status\">enterprise</span>";
                        } else if (matchingItem.bundle && matchingItem.bundle !== "0") {
                          additionalInfo = "<span class=\"order-status\">bundle</span>";
                        }
                      }
                      var orderHtml = "\n              <div class=\"masterstudy-orders-table__body-row\">\n                <div class=\"masterstudy-orders-course-info\">\n                  <div class=\"masterstudy-orders-course-info__image\">".concat(item.image ? "<a href=\"".concat(item.link, "\">").concat(item.image, "</a>") : "<img src=\"".concat(item.placeholder, "\" alt=\"").concat(item.title, "\">"), "</div>\n                  <div class=\"masterstudy-orders-course-info__common\">\n                    <div class=\"masterstudy-orders-course-info__title\">").concat(item.title ? "<a href=\"".concat(item.link, "\">").concat(item.title, "</a>") : "<em>N/A</em>", " ").concat(additionalInfo, "</div>\n                    <div class=\"masterstudy-orders-course-info__category\">\n                    ").concat(item.enterprise_name ? "".concat(order.i18n.enterprise, " ").concat(item.enterprise_name) : " ".concat(item.terms.join(", ")), "\n                    ").concat(item.bundle_courses_count > 0 ? "".concat(item.bundle_courses_count, " ").concat(order.i18n.bundle) : "", "\n                    </div>\n                  </div>\n                  <div class=\"masterstudy-orders-course-info__price\">").concat(item.price_formatted, "</div>\n                </div>\n              </div>");
                      $(clone).find(".masterstudy-orders-table__body").append(orderHtml);
                    }
                  };
                  for (var key in order.cart_items) {
                    _loop(key);
                  }
                  $(clone).find("[data-order-total]").text("".concat(order.total));
                  $(".masterstudy-orders-container").append(clone);
                });
              }
            } else {
              orderHtml = "\n              <div class=\"masterstudy-orders-no-found__info\">\n                <div class=\"masterstudy-orders-no-found__info-icon\"><span class=\"stmlms-order\"></span></div>\n                <div class=\"masterstudy-orders-no-found__info-title\">".concat(data.i18n.no_order_title, "</div>\n                <div class=\"masterstudy-orders-no-found__info-description\">").concat(data.i18n.no_order_description, "</div>\n            </div>");
              $(".masterstudy-orders").append("".concat(orderHtml)).addClass("masterstudy-orders-no-found");
            }
            _context.next = 28;
            break;
          case 24:
            _context.prev = 24;
            _context.t0 = _context["catch"](8);
            console.error("Error fetching orders:", _context.t0);
            ordersContainer.css("height", "auto").removeClass("orders-loading");
          case 28:
          case "end":
            return _context.stop();
        }
      }, _callee, null, [[8, 24]]);
    }));
    return _fetchOrders.apply(this, arguments);
  }
  function perPageOrders(perPage) {
    $(".masterstudy-select__option, .masterstudy-select__clear").on("click", function () {
      $(".masterstudy-orders-container .masterstudy-orders-table").remove();
      var perPage = $(this).data("value");
      fetchOrders(perPage);
    });
  }

  //Function to update API data for pagination
  function updatePagination(totalPages, currentPage) {
    $.ajax({
      url: masterstudy_orders.ajaxurl,
      method: "POST",
      data: {
        action: "get_pagination",
        total_pages: totalPages,
        current_page: currentPage,
        _ajax_nonce: masterstudy_orders.nonce
      },
      success: function success(response) {
        if (response.success) {
          $(".masterstudy-orders-table-navigation__pagination").toggle(totalPages > 1);
          $(".masterstudy-orders-table-navigation__pagination").html(response.data.pagination);
          attachPaginationClickHandlers(totalPages);
          $(".masterstudy-pagination__button-next").toggleClass("masterstudy-pagination__button_disabled", currentPage >= totalPages);
          $(".masterstudy-pagination__button-prev").toggleClass("masterstudy-pagination__button_disabled", currentPage <= 1);
          updatePaginationView(totalPages, currentPage);
        } else {
          console.error("Error updating pagination:", response.data);
        }
      },
      error: function error(_error) {
        console.error("AJAX error:", _error);
      }
    });
  }

  //Function to update the page
  function attachPaginationClickHandlers(totalPages) {
    $(".masterstudy-pagination__item-block").on("click", function () {
      tableHeight();
      var currentPage = $(this).data("id");
      $(".masterstudy-orders-container .masterstudy-orders-table").remove();
      var perPage = $("#orders-per-page").val();
      updatePagination(totalPages, currentPage);
      fetchOrders(perPage, currentPage);
    });
    $(".masterstudy-pagination__button-prev").on("click", function () {
      tableHeight();
      var currentPageElement = $(".masterstudy-pagination__item_current .masterstudy-pagination__item-block");
      if (currentPageElement.length) {
        var currentPage = parseInt(currentPageElement.data("id"));
        currentPage -= 1;
        if (currentPage >= 1) {
          $(".masterstudy-orders-container .masterstudy-orders-table").remove();
          var perPage = $("#orders-per-page").val();
          updatePagination(totalPages, currentPage);
          fetchOrders(perPage, currentPage);
        }
      }
    });
    $(".masterstudy-pagination__button-next").on("click", function () {
      tableHeight();
      var currentPageElement = $(".masterstudy-pagination__item_current .masterstudy-pagination__item-block");
      if (currentPageElement.length) {
        var currentPage = parseInt(currentPageElement.data("id"));
        currentPage += 1;
        var _totalPages = $(".masterstudy-pagination__item-block").length;
        if (currentPage <= _totalPages) {
          $(".masterstudy-orders-container .masterstudy-orders-table").remove();
          var perPage = $("#orders-per-page").val();
          updatePagination(_totalPages, currentPage);
          fetchOrders(perPage, currentPage);
        }
      }
    });
  }

  //Animation when switching pagination
  function updatePaginationView(totalPages, currentPage) {
    $(".masterstudy-pagination__item").hide();
    var startPage = Math.max(1, currentPage - 1);
    var endPage = Math.min(totalPages, currentPage + 1);
    if (currentPage === 1 || startPage === 1) {
      endPage = Math.min(totalPages, startPage + 2);
    } else if (currentPage === totalPages || endPage === totalPages) {
      startPage = Math.max(1, endPage - 2);
    }
    for (var i = startPage; i <= endPage; i++) {
      $(".masterstudy-pagination__item:has([data-id=\"".concat(i, "\"])")).show();
    }
    $(".masterstudy-pagination__button-next").toggle(currentPage < totalPages);
    $(".masterstudy-pagination__button-prev").toggle(currentPage > 1);
  }

  //Function animation for container
  function tableHeight() {
    var ordersContainer = $(".masterstudy-orders-container");
    var containerHeight = ordersContainer.height();
    ordersContainer.css("height", containerHeight);
    ordersContainer.removeClass("orders-loading");
  }
})(jQuery);