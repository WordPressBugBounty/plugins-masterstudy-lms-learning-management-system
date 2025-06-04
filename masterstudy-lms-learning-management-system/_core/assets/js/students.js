(function(){function r(e,n,t){function o(i,f){if(!n[i]){if(!e[i]){var c="function"==typeof require&&require;if(!f&&c)return c(i,!0);if(u)return u(i,!0);var a=new Error("Cannot find module '"+i+"'");throw a.code="MODULE_NOT_FOUND",a}var p=n[i]={exports:{}};e[i][0].call(p.exports,function(r){var n=e[i][1][r];return o(n||r)},p,p.exports,r,e,n,t)}return n[i].exports}for(var u="function"==typeof require&&require,i=0;i<t.length;i++)o(t[i]);return o}return r})()({1:[function(require,module,exports){
"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.attachPaginationClickHandlers = attachPaginationClickHandlers;
exports.bindPerPageHandler = bindPerPageHandler;
exports.renderPagination = renderPagination;
exports.updatePaginationView = updatePaginationView;
window.$ = jQuery;
function updatePaginationView(totalPages, currentPage) {
  $(".masterstudy-pagination__item").removeClass('masterstudy-pagination__item_current').hide();
  var start = Math.max(1, currentPage - 1);
  var end = Math.min(totalPages, currentPage + 1);
  if (currentPage === 1 || start === 1) end = Math.min(totalPages, start + 2);
  if (currentPage === totalPages || end === totalPages) start = Math.max(1, end - 2);
  for (var i = start; i <= end; i++) {
    $(".masterstudy-pagination__item:has([data-id=\"".concat(i, "\"])")).show();
  }
  $(".masterstudy-pagination__item-block[data-id=\"".concat(currentPage, "\"]")).parent().addClass('masterstudy-pagination__item_current');
  $(".masterstudy-pagination__button-next").toggle(currentPage < totalPages);
  $(".masterstudy-pagination__button-prev").toggle(currentPage > 1);
}
function attachPaginationClickHandlers(totalPages, onPageChange, getPerPageSelector) {
  $(".masterstudy-pagination__item-block").off("click").on("click", function () {
    if ($(this).parent().hasClass('masterstudy-pagination__item_current')) {
      return;
    }
    var page = $(this).data("id");
    onPageChange($(getPerPageSelector()).val(), page);
  });
  $(".masterstudy-pagination__button-prev").off("click").on("click", function () {
    var current = $(".masterstudy-pagination__item_current .masterstudy-pagination__item-block").data("id");
    if (current > 1) onPageChange($(getPerPageSelector()).val(), current - 1);
  });
  $(".masterstudy-pagination__button-next").off("click").on("click", function () {
    var current = $(".masterstudy-pagination__item_current .masterstudy-pagination__item-block").data("id");
    var total = $(".masterstudy-pagination__item-block").length;
    if (current < total) onPageChange($(getPerPageSelector()).val(), current + 1);
  });
}
function bindPerPageHandler(containerSelector, perPage, fetchFn) {
  $(".masterstudy-select__option, .masterstudy-select__clear", perPage).off("click").on("click", function () {
    $(containerSelector).remove();
    fetchFn($(this).data("value"));
  });
}
function renderPagination(_ref) {
  var ajaxurl = _ref.ajaxurl,
    nonce = _ref.nonce,
    totalPages = _ref.totalPages,
    currentPage = _ref.currentPage,
    paginationContainer = _ref.paginationContainer,
    onPageChange = _ref.onPageChange,
    getPerPageSelector = _ref.getPerPageSelector;
  $.post(ajaxurl, {
    action: "get_pagination",
    total_pages: totalPages,
    current_page: currentPage,
    _ajax_nonce: nonce
  }, function (response) {
    if (response.success) {
      var $nav = $(paginationContainer);
      $nav.toggle(totalPages > 1).html(response.data.pagination);
      attachPaginationClickHandlers(totalPages, onPageChange, getPerPageSelector);
      updatePaginationView(totalPages, currentPage);
    }
  });
}

},{}],2:[function(require,module,exports){
"use strict";

var _utils = require("../enrolled-quizzes/modules/utils.js");
(function ($) {
  var config = {
      selectors: {
        container: '.masterstudy-table-list-items',
        loading: 'items-loading',
        no_found: '.masterstudy-table-list-no-found__info',
        row: '.masterstudy-table-list__row',
        search_input: '.masterstudy-form-search__input',
        checkboxAll: '#masterstudy-table-list-checkbox',
        checkbox: 'input[name="student[]"]',
        per_page: '#items-per-page',
        navigation: '.masterstudy-table-list-navigation',
        pagination: '.masterstudy-table-list-navigation__pagination',
        perPage: '.masterstudy-table-list-navigation__per-page',
        "export": '[data-id="export-students-to-csv"]',
        selectByCourse: '.filter-students-by-courses',
        deleteBtn: '[data-id="masterstudy-students-delete"]',
        modalDelete: '[data-id="masterstudy-delete-students"]'
      },
      templates: {
        no_found: 'masterstudy-table-list-no-found-template',
        row: 'masterstudy-table-list-row-template'
      },
      endpoints: {
        students: '/students/',
        deleting: '/students/delete/',
        courses: '/courses',
        exportStudents: '/export/students/'
      },
      apiBase: ms_lms_resturl,
      nonce: ms_lms_nonce
    },
    totalPages = 1,
    courseId = '';
  $(document).ready(function () {
    if ($('.masterstudy-students-list').length) {
      init();
    }
  });
  function init() {
    (0, _utils.bindPerPageHandler)($(config.selectors.row, config.selectors.container), config.selectors.perPage, fetchItems);
    fetchItems();
    initSearch();
    checkAll();
    deleteStudents();
    searchByCourse();
    exportStudents();
    dateFilter();
    itemsSort();
  }
  function fetchItems() {
    var perPage = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : undefined;
    var currentPage = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 1;
    var orderby = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : '';
    var order = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : '';
    var url = config.apiBase + config.endpoints.students;
    var query = [];
    var $input = $(config.selectors.search_input);
    var searchQuery = $input.length ? $input.val().trim() : '';
    var dateFrom = getDateFrom();
    var dateTo = getDateTo();
    query.push("show_all_enrolled=1");
    if (searchQuery) query.push("s=".concat(encodeURIComponent(searchQuery)));
    if (perPage) query.push("per_page=".concat(perPage));
    if (currentPage) query.push("page=".concat(currentPage));
    if (courseId) query.push("course_id=".concat(courseId));
    if (dateFrom) query.push("date_from=".concat(dateFrom));
    if (dateTo) query.push("date_to=".concat(dateTo));
    if (orderby) query.push("orderby=".concat(orderby));
    if (order) query.push("order=".concat(order));
    if (query.length) url += "?".concat(query.join("&"));
    (0, _utils.updatePaginationView)(totalPages, currentPage);
    var $container = $(config.selectors.container);
    $("".concat(config.selectors.row, ", ").concat(config.selectors.no_found), config.selectors.container).remove();
    $container.addClass(config.selectors.loading);
    $(config.selectors.navigation).hide();
    fetch(url, {
      headers: {
        "X-WP-Nonce": config.nonce,
        "Content-Type": "application/json"
      }
    }).then(function (res) {
      return res.json();
    }).then(function (data) {
      $container.css("height", "auto").removeClass(config.selectors.loading);
      $("".concat(config.selectors.row, ", ").concat(config.selectors.no_found), config.selectors.container).remove();
      updatePagination(data.pages, currentPage);
      if (!data.students || data.students.length === 0) {
        var template = document.getElementById(config.templates.no_found);
        if (template) {
          var clone = template.content.cloneNode(true);
          $(config.selectors.navigation).hide();
          $container.append(clone);
        }
        return;
      }
      $(config.selectors.navigation).show();
      totalPages = data.pages;
      (data.students || []).forEach(function (item) {
        var html = renderItemTemplate(item);
        $container.append(html);
      });
    })["catch"](function (err) {
      console.error("Error fetching items:", err);
      $container.css("height", "auto").removeClass(config.selectors.loading);
    });
  }
  function renderItemTemplate(item) {
    var template = document.getElementById(config.templates.row);
    if (!template) return '';
    var clone = template.content.cloneNode(true);
    var url = new URL(item.url, window.location.origin);
    clone.querySelector('[name="student[]"]').value = item.user_id;
    if (clone.querySelector('.masterstudy-table-list__row--link')) {
      clone.querySelector('.masterstudy-table-list__row--link').href = url.toString();
    }
    clone.querySelector('.masterstudy-table-list__td--name').textContent = item.display_name;
    clone.querySelector('.masterstudy-table-list__td--email').textContent = item.email;
    clone.querySelector('.masterstudy-table-list__td--joined').textContent = item.registered;
    clone.querySelector('.masterstudy-table-list__td--enrolled').textContent = item.enrolled;
    if (clone.querySelector('.masterstudy-table-list__td--points')) {
      clone.querySelector('.masterstudy-table-list__td--points').textContent = item.points;
    }
    return clone;
  }
  function updatePagination(totalPages, currentPage) {
    (0, _utils.renderPagination)({
      ajaxurl: stm_lms_ajaxurl,
      nonce: config.nonce,
      totalPages: totalPages,
      currentPage: currentPage,
      paginationContainer: config.selectors.pagination,
      onPageChange: fetchItems,
      getPerPageSelector: function getPerPageSelector() {
        return config.selectors.per_page;
      }
    });
  }
  function initSearch() {
    var $input = $(config.selectors.search_input);
    if (!$input.length) return;
    var timer;
    var lastQuery = '';
    $input.off("input").on("input", function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        var query = $input.val().trim();
        if (query !== lastQuery) {
          lastQuery = query;
          fetchItems($(config.selectors.per_page).val(), 1);
        }
      }, 300);
    });
  }
  function checkAll() {
    var $selectAll = $(config.selectors.checkboxAll);
    var $deleteBtn = $(config.selectors.deleteBtn);
    if (!$selectAll.length) return;
    function updateDeleteBtn() {
      var anyChecked = $(config.selectors.checkbox).filter(':checked').length > 0;
      $deleteBtn.prop('disabled', !anyChecked);
    }
    $selectAll.on('change', function () {
      var isChecked = this.checked;
      $(config.selectors.checkbox).prop('checked', isChecked).trigger('change');
    });
    $(document).on('change', config.selectors.checkbox, function () {
      var $all = $(config.selectors.checkbox);
      var checkedCnt = $all.filter(':checked').length;
      $selectAll.prop('checked', checkedCnt === $all.length);
      updateDeleteBtn();
    });
    updateDeleteBtn();
  }
  function deleteStudents() {
    var url = config.apiBase + config.endpoints.deleting;
    var _config$selectors = config.selectors,
      checkboxAll = _config$selectors.checkboxAll,
      deleteBtn = _config$selectors.deleteBtn,
      modalDelete = _config$selectors.modalDelete,
      container = _config$selectors.container,
      row = _config$selectors.row,
      no_found = _config$selectors.no_found,
      loading = _config$selectors.loading,
      checkbox = _config$selectors.checkbox,
      per_page = _config$selectors.per_page;
    var students = [];
    $(deleteBtn).on('click', function (e) {
      e.preventDefault();
      students = $('input[name="student[]"]:checked').map(function () {
        return this.value;
      }).get();
      if (students.length) {
        $(modalDelete).addClass('masterstudy-alert_open');
      }
    });
    $(modalDelete).on('click', "[data-id='cancel'], .masterstudy-alert__header-close", function (e) {
      e.preventDefault();
      $(modalDelete).removeClass('masterstudy-alert_open');
    });
    $(modalDelete).on('click', "[data-id='submit']", function (e) {
      e.preventDefault();
      if (!students.length) return;
      $(container).find("".concat(row, ", ").concat(no_found)).remove();
      $(container).addClass(loading);
      $(modalDelete).removeClass('masterstudy-alert_open');
      $(checkbox).prop('checked', false);
      $(config.selectors.navigation).hide();
      $(checkboxAll).prop('checked', false);
      fetch(url, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': config.nonce,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          students: students
        })
      }).then(function (res) {
        students = [];
        if (!res.ok) throw new Error("Server responded with ".concat(res.status));
        return fetchItems($(per_page).val(), 1);
      })["catch"](console.error);
    });
  }
  function searchByCourse() {
    var $input = $(config.selectors.selectByCourse);
    var $parent = $input.parent();
    var apiBase = config.apiBase,
      endpoints = config.endpoints,
      nonce = config.nonce;
    var URL = apiBase + endpoints.courses;
    var PER_PAGE = 20;
    var staticCourses = [];
    var staticTotalPages = 0;
    function fetchCourses() {
      var term = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : '';
      var page = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 1;
      return $.ajax({
        url: URL,
        method: 'GET',
        dataType: 'json',
        headers: {
          'X-WP-Nonce': nonce
        },
        data: {
          s: term,
          per_page: PER_PAGE,
          current_user: 1,
          page: page
        }
      });
    }
    function truncateWithEllipsis(text) {
      return text.length > 23 ? text.slice(0, 23) + '…' : text;
    }
    fetchCourses().done(function (response) {
      staticCourses = response.courses || [];
      staticTotalPages = parseInt(response.pages, 10) || 0;
    }).always(initSelect2);
    function initSelect2() {
      $parent.removeClass('filter-students-by-courses-default');
      $input.select2({
        dropdownParent: $parent,
        placeholder: $input.data('placeholder'),
        allowClear: true,
        minimumInputLength: 0,
        templateSelection: function templateSelection(data) {
          return truncateWithEllipsis(data.text);
        },
        escapeMarkup: function escapeMarkup(markup) {
          return markup;
        },
        ajax: {
          transport: function transport(params, success, failure) {
            var term = params.data.term || '';
            var page = parseInt(params.data.page, 10) || 1;
            if (!term && page === 1) {
              return success({
                results: staticCourses.map(function (item) {
                  return {
                    id: item.ID,
                    text: item.post_title
                  };
                }),
                pagination: {
                  more: staticTotalPages > 1
                }
              });
            }
            fetchCourses(term, page).done(function (response) {
              return success({
                results: (response.courses || []).map(function (item) {
                  return {
                    id: item.ID,
                    text: item.post_title
                  };
                }),
                pagination: {
                  more: page < (parseInt(response.pages, 10) || 0)
                }
              });
            }).fail(failure);
          },
          delay: 250,
          processResults: function processResults(data) {
            return data;
          },
          cache: true
        }
      });
      $input.on('select2:select select2:clear', function (e) {
        if (e.type === 'select2:select') {
          courseId = e.params.data.id;
        } else if (e.type === 'select2:clear') {
          courseId = null;
        }
        fetchItems($(config.selectors.per_page).val(), 1);
      });
    }
  }
  function exportStudents() {
    $(config.selectors["export"]).on('click', function () {
      var url = config.apiBase + config.endpoints.exportStudents;
      var query = [];
      var $selectByCourse = $(config.selectors.selectByCourse);
      var courseId = $selectByCourse.length ? $selectByCourse.val().trim() : '';
      var $inputSearch = $(config.selectors.search_input);
      var searchQuery = $inputSearch.length ? $inputSearch.val().trim() : '';
      var dateFrom = getDateFrom();
      var dateTo = getDateTo();
      query.push("show_all_enrolled=1");
      query.push("s=".concat(encodeURIComponent(searchQuery)));
      query.push("course_id=".concat(courseId));
      query.push("date_from=".concat(dateFrom));
      query.push("date_to=".concat(dateTo));
      if (query.length) url += "?".concat(query.join("&"));
      fetch(url, {
        headers: {
          "X-WP-Nonce": config.nonce,
          "Content-Type": "application/json"
        }
      }).then(function (res) {
        return res.json();
      }).then(function (data) {
        downloadCSV(data);
      })["catch"](function (err) {
        console.error("Error export items:", err);
      });
    });
    function downloadCSV(data) {
      var csv = convertArrayOfObjectsToCSV({
        data: data
      });
      if (!csv) return;
      var filename = "enrolled_students.csv";
      var csvUtf = 'data:text/csv;charset=utf-8,';
      var href = encodeURI(csvUtf + "\uFEFF" + csv);
      var link = document.createElement('a');
      link.setAttribute('href', href);
      link.setAttribute('download', filename);
      link.click();
    }
    function convertArrayOfObjectsToCSV(_ref) {
      var data = _ref.data,
        _ref$columnDelimiter = _ref.columnDelimiter,
        columnDelimiter = _ref$columnDelimiter === void 0 ? ',' : _ref$columnDelimiter,
        _ref$lineDelimiter = _ref.lineDelimiter,
        lineDelimiter = _ref$lineDelimiter === void 0 ? '\r\n' : _ref$lineDelimiter;
      if (!Array.isArray(data) || data.length === 0) return null;
      var keys = Object.keys(data[0]);
      var result = '';
      result += keys.join(columnDelimiter) + lineDelimiter;
      data.forEach(function (item) {
        keys.forEach(function (key, idx) {
          if (idx > 0) result += columnDelimiter;
          var cell = item[key];
          if (Array.isArray(cell)) {
            result += "\"".concat(cell.map(function (item) {
              return decodeStr(item);
            }).join(','), "\"");
          } else {
            cell = cell == null ? '' : String(cell);
            if (cell.includes(columnDelimiter) || cell.includes('"') || cell.includes('\n')) {
              cell = "\"".concat(cell.replace(/"/g, '""'), "\"");
            }
            result += cell;
          }
        });
        result += lineDelimiter;
      });
      return result;
    }
    function decodeStr(str) {
      return str.replace(/&#(\d+);/g, function (_, code) {
        return String.fromCharCode(code);
      });
    }
  }
  function dateFilter() {
    initializeDatepicker('#masterstudy-datepicker-students');
    document.addEventListener('datesUpdated', function () {
      fetchItems();
    });
  }
  function itemsSort() {
    document.addEventListener('msSortIndicatorEvent', function (event) {
      var order = event.detail.sortOrder,
        orderby = event.detail.indicator.parents('.masterstudy-tcell__header').data('sort');
      order = 'none' === order ? 'asc' : order;
      fetchItems($(config.selectors.per_page).val(), 1, orderby, order);
    });
    $('.masterstudy-tcell__title').on('click', function () {
      $('.masterstudy-sort-indicator', $(this).parent()).trigger('click');
    });
  }
})(jQuery);

},{"../enrolled-quizzes/modules/utils.js":1}]},{},[2])
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbIm5vZGVfbW9kdWxlcy9icm93c2VyLXBhY2svX3ByZWx1ZGUuanMiLCJhc3NldHMvZXM2L2Vucm9sbGVkLXF1aXp6ZXMvbW9kdWxlcy91dGlscy5qcyIsImFzc2V0cy9lczYvc3R1ZGVudHMvaW5kZXguanMiXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IkFBQUE7Ozs7Ozs7Ozs7QUNBQSxNQUFNLENBQUMsQ0FBQyxHQUFHLE1BQU07QUFFVixTQUFTLG9CQUFvQixDQUFDLFVBQVUsRUFBRSxXQUFXLEVBQUU7RUFDMUQsQ0FBQyxDQUFDLCtCQUErQixDQUFDLENBQUMsV0FBVyxDQUFDLHNDQUFzQyxDQUFDLENBQUMsSUFBSSxDQUFDLENBQUM7RUFDN0YsSUFBSSxLQUFLLEdBQUcsSUFBSSxDQUFDLEdBQUcsQ0FBQyxDQUFDLEVBQUUsV0FBVyxHQUFHLENBQUMsQ0FBQztFQUN4QyxJQUFJLEdBQUcsR0FBRyxJQUFJLENBQUMsR0FBRyxDQUFDLFVBQVUsRUFBRSxXQUFXLEdBQUcsQ0FBQyxDQUFDO0VBRS9DLElBQUksV0FBVyxLQUFLLENBQUMsSUFBSSxLQUFLLEtBQUssQ0FBQyxFQUFFLEdBQUcsR0FBRyxJQUFJLENBQUMsR0FBRyxDQUFDLFVBQVUsRUFBRSxLQUFLLEdBQUcsQ0FBQyxDQUFDO0VBQzNFLElBQUksV0FBVyxLQUFLLFVBQVUsSUFBSSxHQUFHLEtBQUssVUFBVSxFQUFFLEtBQUssR0FBRyxJQUFJLENBQUMsR0FBRyxDQUFDLENBQUMsRUFBRSxHQUFHLEdBQUcsQ0FBQyxDQUFDO0VBRWxGLEtBQUssSUFBSSxDQUFDLEdBQUcsS0FBSyxFQUFFLENBQUMsSUFBSSxHQUFHLEVBQUUsQ0FBQyxFQUFFLEVBQUU7SUFDL0IsQ0FBQyxpREFBQSxNQUFBLENBQWdELENBQUMsU0FBSyxDQUFDLENBQUMsSUFBSSxDQUFDLENBQUM7RUFDbkU7RUFFQSxDQUFDLGtEQUFBLE1BQUEsQ0FBaUQsV0FBVyxRQUFJLENBQUMsQ0FBQyxNQUFNLENBQUMsQ0FBQyxDQUFDLFFB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SyxLQUFLLEdBQUcsS0FBSyxHQUFHLEtBQUs7TUFDeEMsVUFBVSxDQUFFLENBQUMsQ0FBRSxNQUFNLENBQUMsU0FBUyxDQUFDLFFBQVMsQ0FBQyxDQUFDLEdBQUcsQ0FBQyxDQUFDLEVBQUUsQ0FBQyxFQUFFLE9BQU8sRUFBRSxLQUFNLENBQUM7SUFDekUsQ0FBQyxDQUFDO0lBRUYsQ0FBQyxDQUFFLDJCQUE0QixDQUFDLENBQUMsRUFBRSxDQUFFLE9BQU8sRUFBRSxZQUFXO01BQ3JELENBQUMsQ0FBRSw2QkFBNkIsRUFBRSxDQUFDLENBQUUsSUFBSyxDQUFDLENBQUMsTUFBTSxDQUFDLENBQUUsQ0FBQyxDQUFDLE9BQU8sQ0FBRSxPQUFRLENBQUM7SUFDN0UsQ0FBQyxDQUFDO0VBQ047QUFDSixDQUFDLEVBQUUsTUFBTSxDQUFDIiwiZmlsZSI6ImdlbmVyYXRlZC5qcyIsInNvdXJjZVJvb3QiOiIiLCJzb3VyY2VzQ29udGVudCI6WyIoZnVuY3Rpb24oKXtmdW5jdGlvbiByKGUsbix0KXtmdW5jdGlvbiBvKGksZil7aWYoIW5baV0pe2lmKCFlW2ldKXt2YXIgYz1cImZ1bmN0aW9uXCI9PXR5cGVvZiByZXF1aXJlJiZyZXF1aXJlO2lmKCFmJiZjKXJldHVybiBjKGksITApO2lmKHUpcmV0dXJuIHUoaSwhMCk7dmFyIGE9bmV3IEVycm9yKFwiQ2Fubm90IGZpbmQgbW9kdWxlICdcIitpK1wiJ1wiKTt0aHJvdyBhLmNvZGU9XCJNT0RVTEVfTk9UX0ZPVU5EXCIsYX12YXIgcD1uW2ldPXtleHBvcnRzOnt9fTtlW2ldWzBdLmNhbGwocC5leHBvcnRzLGZ1bmN0aW9uKHIpe3ZhciBuPWVbaV1bMV1bcl07cmV0dXJuIG8obnx8cil9LHAscC5leHBvcnRzLHIsZSxuLHQpfXJldHVybiBuW2ldLmV4cG9ydHN9Zm9yKHZhciB1PVwiZnVuY3Rpb25cIj09dHlwZW9mIHJlcXVpcmUmJnJlcXVpcmUsaT0wO2k8dC5sZW5ndGg7aSsrKW8odFtpXSk7cmV0dXJuIG99cmV0dXJuIHJ9KSgpIiwid2luZG93LiQgPSBqUXVlcnk7XG5cbmV4cG9ydCBmdW5jdGlvbiB1cGRhdGVQYWdpbmF0aW9uVmlldyh0b3RhbFBhZ2VzLCBjdXJyZW50UGFnZSkge1xuICAgICQoXCIubWFzdGVyc3R1ZHktcGFnaW5hdGlvbl9faXRlbVwiKS5yZW1vdmVDbGFzcygnbWFzdGVyc3R1ZHktcGFnaW5hdGlvbl9faXRlbV9jdXJyZW50JykuaGlkZSgpO1xuICAgIGxldCBzdGFydCA9IE1hdGgubWF4KDEsIGN1cnJlbnRQYWdlIC0gMSk7XG4gICAgbGV0IGVuZCA9IE1hdGgubWluKHRvdGFsUGFnZXMsIGN1cnJlbnRQYWdlICsgMSk7XG5cbiAgICBpZiAoY3VycmVudFBhZ2UgPT09IDEgfHwgc3RhcnQgPT09IDEpIGVuZCA9IE1hdGgubWluKHRvdGFsUGFnZXMsIHN0YXJ0ICsgMik7XG4gICAgaWYgKGN1cnJlbnRQYWdlID09PSB0b3RhbFBhZ2VzIHx8IGVuZCA9PT0gdG90YWxQYWdlcykgc3RhcnQgPSBNYXRoLm1heCgxLCBlbmQgLSAyKTtcblxuICAgIGZvciAobGV0IGkgPSBzdGFydDsgaSA8PSBlbmQ7IGkrKykge1xuICAgICAgICAkKGAubWFzdGVyc3R1ZHktcGFnaW5hdGlvbl9faXRlbTpoYXMoW2RhdGEtaWQ9XCIke2l9XCJdKWApLnNob3coKTtcbiAgICB9XG5cbiAgICAkKGAubWFzdGVyc3R1ZHktcGFnaW5hdGlvbl9faXRlbS1ibG9ja1tkYXRhLWlkPVwiJHtjdXJyZW50UGFnZX1cIl1gKS5wYXJlbnQoKS5hZGRDbGFzcyggJ21hc3RlcnN0dWR5LXBhZ2luYXRpb25fX2l0ZW1fY3VycmVudCcgKTtcbiAgICAkKFwiLm1hc3RlcnN0dWR5LXBhZ2luYXRpb25fX2J1dHRvbi1uZXh0XCIpLnRvZ2dsZShjdXJyZW50UGFnZSA8IHRvdGFsUGFnZXMpO1xuICAgICQoXCIubWFzdGVyc3R1ZHktcGFnaW5hdGlvbl9fYnV0dG9uLXByZXZcIikudG9nZ2xlKGN1cnJlbnRQYWdlID4gMSk7XG59XG5cbmV4cG9ydCBmdW5jdGlvbiBhdHRhY2hQYWdpbmF0aW9uQ2xpY2tIYW5kbGVycyh0b3RhbFBhZ2VzLCBvblBhZ2VDaGFuZ2UsIGdldFBlclBhZ2VTZWxlY3Rvcikge1xuICAgICQoXCIubWFzdGVyc3R1ZHktcGFnaW5hdGlvbl9faXRlbS1ibG9ja1wiKS5vZmYoXCJjbGlja1wiKS5vbihcImNsaWNrXCIsIGZ1bmN0aW9uICgpIHtcbiAgICAgICAgaWYgKCAkKHRoaXMpLnBhcmVudCgpLmhhc0NsYXNzKCAnbWFzdGVyc3R1ZHktcGFnaW5hdGlvbl9faXRlbV9jdXJyZW50JyApICkge1xuICAgICAgICAgICAgcmV0dXJuO1xuICAgICAgICB9XG5cbiAgICAgICAgY29uc3QgcGFnZSA9ICQodGhpcykuZGF0YShcImlkXCIpO1xuICAgICAgICBvblBhZ2VDaGFuZ2UoJChnZXRQZXJQYWdlU2VsZWN0b3IoKSkudmFsKCksIHBhZ2UpO1xuICAgIH0pO1xuXG4gICAgJChcIi5tYXN0ZXJzdHVkeS1wYWdpbmF0aW9uX19idXR0b24tcHJldlwiKS5vZmYoXCJjbGlja1wiKS5vbihcImNsaWNrXCIsIGZ1bmN0aW9uICgpIHtcbiAgICAgICAgY29uc3QgY3VycmVudCA9ICQoXCIubWFzdGVyc3R1ZHktcGFnaW5hdGlvbl9faXRlbV9jdXJyZW50IC5tYXN0ZXJzdHVkeS1wYWdpbmF0aW9uX19pdGVtLWJsb2NrXCIpLmRhdGEoXCJpZFwiKTtcbiAgICAgICAgaWYgKGN1cnJlbnQgPiAxKSBvblBhZ2VDaGFuZ2UoJChnZXRQZXJQYWdlU2VsZWN0b3IoKSkudmFsKCksIGN1cnJlbnQgLSAxKTtcbiAgICB9KTtcblxuICAgICQoXCIubWFzdGVyc3R1ZHktcGFnaW5hdGlvbl9fYnV0dG9uLW5leHRcIikub2ZmKFwiY2xpY2tcIikub24oXCJjbGlja1wiLCBmdW5jdGlvbiAoKSB7XG4gICAgICAgIGNvbnN0IGN1cnJlbnQgPSAkKFwiLm1hc3RlcnN0dWR5LXBhZ2luYXRpb25fX2l0ZW1fY3VycmVudCAubWFzdGVyc3R1ZHktcGFnaW5hdGlvbl9faXRlbS1ibG9ja1wiKS5kYXRhKFwiaWRcIik7XG4gICAgICAgIGNvbnN0IHRvdGFsID0gJChcIi5tYXN0ZXJzdHVkeS1wYWdpbmF0aW9uX19pdGVtLWJsb2NrXCIpLmxlbmd0aDtcbiAgICAgICAgaWYgKGN1cnJlbnQgPCB0b3RhbCkgb25QYWdlQ2hhbmdlKCQoZ2V0UGVyUGFnZVNlbGVjdG9yKCkpLnZhbCgpLCBjdXJyZW50ICsgMSk7XG4gICAgfSk7XG59XG5cbmV4cG9ydCBmdW5jdGlvbiBiaW5kUGVyUGFnZUhhbmRsZXIoY29udGFpbmVyU2VsZWN0b3IsIHBlclBhZ2UsIGZldGNoRm4pIHtcbiAgICAkKFwiLm1hc3RlcnN0dWR5LXNlbGVjdF9fb3B0aW9uLCAubWFzdGVyc3R1ZHktc2VsZWN0X19jbGVhclwiLCBwZXJQYWdlKS5vZmYoXCJjbGlja1wiKS5vbihcImNsaWNrXCIsIGZ1bmN0aW9uICgpIHtcbiAgICAgICAgJChjb250YWluZXJTZWxlY3RvcikucmVtb3ZlKCk7XG4gICAgICAgIGZldGNoRm4oJCh0aGlzKS5kYXRhKFwidmFsdWVcIikpO1xuICAgIH0pO1xufVxuXG5leHBvcnQgZnVuY3Rpb24gcmVuZGVyUGFnaW5hdGlvbih7XG4gICAgYWpheHVybCxcbiAgICBub25jZSxcbiAgICB0b3RhbFBhZ2VzLFxuICAgIGN1cnJlbnRQYWdlLFxuICAgIHBhZ2luYXRpb25Db250YWluZXIsXG4gICAgb25QYWdlQ2hhbmdlLFxuICAgIGdldFBlclBhZ2VTZWxlY3Rvcixcbn0pIHtcbiAgICAkLnBvc3QoYWpheHVybCwge1xuICAgICAgICBhY3Rpb246IFwiZ2V0X3BhZ2luYXRpb25cIixcbiAgICAgICAgdG90YWxfcGFnZXM6IHRvdGFsUGFnZXMsXG4gICAgICAgIGN1cnJlbnRfcGFnZTogY3VycmVudFBhZ2UsXG4gICAgICAgIF9hamF4X25vbmNlOiBub25jZSxcbiAgICB9LCBmdW5jdGlvbiAocmVzcG9uc2UpIHtcbiAgICAgICAgaWYgKHJlc3BvbnNlLnN1Y2Nlc3MpIHtcbiAgICAgICAgICAgIGNvbnN0ICRuYXYgPSAkKHBhZ2luYXRpb25Db250YWluZXIpO1xuICAgICAgICAgICAgJG5hdi50b2dnbGUodG90YWxQYWdlcyA+IDEpLmh0bWwocmVzcG9uc2UuZGF0YS5wYWdpbmF0aW9uKTtcbiAgICAgICAgICAgIGF0dGFjaFBhZ2luYXRpb25DbGlja0hhbmRsZXJzKHRvdGFsUGFnZXMsIG9uUGFnZUNoYW5nZSwgZ2V0UGVyUGFnZVNlbGVjdG9yKTtcbiAgICAgICAgICAgIHVwZGF0ZVBhZ2luYXRpb25WaWV3KHRvdGFsUGFnZXMsIGN1cnJlbnRQYWdlKTtcbiAgICAgICAgfVxuICAgIH0pO1xufVxuIiwiaW1wb3J0IHtiaW5kUGVyUGFnZUhhbmRsZXIsIHJlbmRlclBhZ2luYXRpb24sIHVwZGF0ZVBhZ2luYXRpb25WaWV3fSBmcm9tICcuLi9lbnJvbGxlZC1xdWl6emVzL21vZHVsZXMvdXRpbHMuanMnO1xuXG4oZnVuY3Rpb24oJCkge1xuICAgIGxldCBjb25maWcgPSB7XG4gICAgICAgIHNlbGVjdG9yczoge1xuICAgICAgICAgICAgY29udGFpbmVyOiAnLm1hc3RlcnN0dWR5LXRhYmxlLWxpc3QtaXRlbXMnLFxuICAgICAgICAgICAgbG9hZGluZzogJ2l0ZW1zLWxvYWRpbmcnLFxuICAgICAgICAgICAgbm9fZm91bmQ6ICcubWFzdGVyc3R1ZHktdGFibGUtbGlzdC1uby1mb3VuZF9faW5mbycsXG4gICAgICAgICAgICByb3c6ICcubWFzdGVyc3R1ZHktdGFibGUtbGlzdF9fcm93JyxcbiAgICAgICAgICAgIHNlYXJjaF9pbnB1dDogJy5tYXN0ZXJzdHVkeS1mb3JtLXNlYXJjaF9faW5wdXQnLFxuICAgICAgICAgICAgY2hlY2tib3hBbGw6ICcjbWFzdGVyc3R1ZHktdGFibGUtbGlzdC1jaGVja2JveCcsXG4gICAgICAgICAgICBjaGVja2JveDogJ2lucHV0W25hbWU9XCJzdHVkZW50W11cIl0nLFxuICAgICAgICAgICAgcGVyX3BhZ2U6ICcjaXRlbXMtcGVyLXBhZ2UnLFxuICAgICAgICAgICAgbmF2aWdhdGlvbjogJy5tYXN0ZXJzdHVkeS10YWJsZS1saXN0LW5hdmlnYXRpb24nLFxuICAgICAgICAgICAgcGFnaW5hdGlvbjogJy5tYXN0ZXJzdHVkeS10YWJsZS1saXN0LW5hdmlnYXRpb25fX3BhZ2luYXRpb24nLFxuICAgICAgICAgICAgcGVyUGFnZTogJy5tYXN0ZXJzdHVkeS10YWJsZS1saXN0LW5hdmlnYXRpb25fX3Blci1wYWdlJyxcbiAgICAgICAgICAgIGV4cG9ydDogJ1tkYXRhLWlkPVwiZXhwb3J0LXN0dWRlbnRzLXRvLWNzdlwiXScsXG4gICAgICAgICAgICBzZWxlY3RCeUNvdXJzZTogJy5maWx0ZXItc3R1ZGVudHMtYnktY291cnNlcycsXG4gICAgICAgICAgICBkZWxldGVCdG46ICdbZGF0YS1pZD1cIm1hc3RlcnN0dWR5LXN0dWRlbnRzLWRlbGV0ZVwiXScsXG4gICAgICAgICAgICBtb2RhbERlbGV0ZTogJ1tkYXRhLWlkPVwibWFzdGVyc3R1ZHktZGVsZXRlLXN0dWRlbnRzXCJdJyxcbiAgICAgICAgfSxcbiAgICAgICAgdGVtcGxhdGVzOiB7XG4gICAgICAgICAgbm9fZm91bmQ6ICdtYXN0ZXJzdHVkeS10YWJsZS1saXN0LW5vLWZvdW5kLXRlbXBsYXRlJyxcbiAgICAgICAgICByb3c6ICdtYXN0ZXJzdHVkeS10YWJsZS1saXN0LXJvdy10ZW1wbGF0ZScsXG4gICAgICAgIH0sXG4gICAgICAgIGVuZHBvaW50czoge1xuICAgICAgICAgICAgc3R1ZGVudHM6ICcvc3R1ZGVudHMvJyxcbiAgICAgICAgICAgIGRlbGV0aW5nOiAnL3N0dWRlbnRzL2RlbGV0ZS8nLFxuICAgICAgICAgICAgY291cnNlczogJy9jb3Vyc2VzJyxcbiAgICAgICAgICAgIGV4cG9ydFN0dWRlbnRzOiAnL2V4cG9ydC9zdHVkZW50cy8nXG4gICAgICAgIH0sXG4gICAgICAgIGFwaUJhc2U6IG1zX2xtc19yZXN0dXJsLFxuICAgICAgICBub25jZTogbXNfbG1zX25vbmNlLFxuICAgIH0sXG4gICAgdG90YWxQYWdlcyA9IDEsXG4gICAgY291cnNlSWQgPSAnJztcblxuICAgICQoZG9jdW1lbnQpLnJlYWR5KGZ1bmN0aW9uKCkge1xuICAgICAgICBpZiAoICQoICcubWFzdGVyc3R1ZHktc3R1ZGVudHMtbGlzdCcgKS5sZW5ndGggKSB7XG4gICAgICAgICAgICBpbml0KCk7XG4gICAgICAgIH1cbiAgICB9KTtcblxuICAgIGZ1bmN0aW9uIGluaXQoKSB7XG4gICAgICAgIGJpbmRQZXJQYWdlSGFuZGxlcigkKCBjb25maWcuc2VsZWN0b3JzLnJvdywgY29uZmlnLnNlbGVjdG9ycy5jb250YWluZXIgKSwgY29uZmlnLnNlbGVjdG9ycy5wZXJQYWdlLCBmZXRjaEl0ZW1zKTtcbiAgICAgICAgZmV0Y2hJdGVtcygpO1xuICAgICAgICBpbml0U2VhcmNoKCk7XG4gICAgICAgIGNoZWNrQWxsKCk7XG4gICAgICAgIGRlbGV0ZVN0dWRlbnRzKCk7XG4gICAgICAgIHNlYXJjaEJ5Q291cnNlKCk7XG4gICAgICAgIGV4cG9ydFN0dWRlbnRzKCk7XG4gICAgICAgIGRhdGVGaWx0ZXIoKTtcbiAgICAgICAgaXRlbXNTb3J0KCk7XG4gICAgfVxuXG4gICAgZnVuY3Rpb24gZmV0Y2hJdGVtcyggcGVyUGFnZSA9IHVuZGVmaW5lZCwgY3VycmVudFBhZ2UgPSAxLCBvcmRlcmJ5ID0gJycsIG9yZGVyID0gJycgKSB7XG4gICAgICAgIGxldCB1cmwgPSBjb25maWcuYXBpQmFzZSArIGNvbmZpZy5lbmRwb2ludHMuc3R1ZGVudHM7XG4gICAgICAgIGNvbnN0IHF1ZXJ5ID0gW107XG4gICAgICAgIGNvbnN0ICRpbnB1dCA9ICQoIGNvbmZpZy5zZWxlY3RvcnMuc2VhcmNoX2lucHV0ICk7XG4gICAgICAgIGNvbnN0IHNlYXJjaFF1ZXJ5ID0gJGlucHV0Lmxlbmd0aCA/ICRpbnB1dC52YWwoKS50cmltKCkgOiAnJztcbiAgICAgICAgY29uc3QgZGF0ZUZyb20gPSBnZXREYXRlRnJvbSgpO1xuICAgICAgICBjb25zdCBkYXRlVG8gPSBnZXREYXRlVG8oKTtcblxuICAgICAgICBxdWVyeS5wdXNoKGBzaG93X2FsbF9lbnJvbGxlZD0xYCk7XG5cbiAgICAgICAgaWYgKHNlYXJjaFF1ZXJ5KSBxdWVyeS5wdXNoKGBzPSR7ZW5jb2RlVVJJQ29tcG9uZW50KHNlYXJjaFF1ZXJ5KX1gKTtcbiAgICAgICAgaWYgKHBlclBhZ2UpIHF1ZXJ5LnB1c2goYHBlcl9wYWdlPSR7cGVyUGFnZX1gKTtcbiAgICAgICAgaWYgKGN1cnJlbnRQYWdlKSBxdWVyeS5wdXNoKGBwYWdlPSR7Y3VycmVudFBhZ2V9YCk7XG4gICAgICAgIGlmIChjb3Vyc2VJZCkgcXVlcnkucHVzaChgY291cnNlX2lkPSR7Y291cnNlSWR9YCk7XG4gICAgICAgIGlmIChkYXRlRnJvbSkgcXVlcnkucHVzaChgZGF0ZV9mcm9tPSR7ZGF0ZUZyb219YCk7XG4gICAgICAgIGlmIChkYXRlVG8pIHF1ZXJ5LnB1c2goYGRhdGVfdG89JHtkYXRlVG99YCk7XG4gICAgICAgIGlmIChvcmRlcmJ5KSBxdWVyeS5wdXNoKGBvcmRlcmJ5PSR7b3JkZXJieX1gKTtcbiAgICAgICAgaWYgKG9yZGVyKSBxdWVyeS5wdXNoKGBvcmRlcj0ke29yZGVyfWApO1xuICAgICAgICBpZiAocXVlcnkubGVuZ3RoKSB1cmwgKz0gYD8ke3F1ZXJ5LmpvaW4oXCImXCIpfWA7XG5cbiAgICAgICAgdXBkYXRlUGFnaW5hdGlvblZpZXcoIHRvdGFsUGFnZXMsIGN1cnJlbnRQYWdlICk7XG5cbiAgICAgICAgY29uc3QgJGNvbnRhaW5lciA9ICQoIGNvbmZpZy5zZWxlY3RvcnMuY29udGFpbmVyICk7XG5cbiAgICAgICAgJCggYCR7Y29uZmlnLnNlbGVjdG9ycy5yb3d9LCAke2NvbmZpZy5zZWxlY3RvcnMubm9fZm91bmR9YCwgY29uZmlnLnNlbGVjdG9ycy5jb250YWluZXIgKS5yZW1vdmUoKTtcblxuICAgICAgICAkY29udGFpbmVyLmFkZENsYXNzKCBjb25maWcuc2VsZWN0b3JzLmxvYWRpbmcgKTtcbiAgICAgICAgJCggY29uZmlnLnNlbGVjdG9ycy5uYXZpZ2F0aW9uICkuaGlkZSgpO1xuXG4gICAgICAgIGZldGNoKHVybCwge1xuICAgICAgICAgICAgaGVhZGVyczoge1xuICAgICAgICAgICAgICAgIFwiWC1XUC1Ob25jZVwiOiBjb25maWcubm9uY2UsXG4gICAgICAgICAgICAgICAgXCJDb250ZW50LVR5cGVcIjogXCJhcHBsaWNhdGlvbi9qc29uXCIsXG4gICAgICAgICAgICB9LFxuICAgICAgICB9KVxuICAgICAgICAudGhlbihyZXMgPT4gcmVzLmpzb24oKSlcbiAgICAgICAgLnRoZW4oZGF0YSA9PiB7XG4gICAgICAgICAgICAkY29udGFpbmVyLmNzcyhcImhlaWdodFwiLCBcImF1dG9cIikucmVtb3ZlQ2xhc3MoIGNvbmZpZy5zZWxlY3RvcnMubG9hZGluZyApO1xuICAgICAgICAgICAgJCggYCR7Y29uZmlnLnNlbGVjdG9ycy5yb3d9LCAke2NvbmZpZy5zZWxlY3RvcnMubm9fZm91bmR9YCwgY29uZmlnLnNlbGVjdG9ycy5jb250YWluZXIgKS5yZW1vdmUoKTtcblxuICAgICAgICAgICAgdXBkYXRlUGFnaW5hdGlvbihkYXRhLnBhZ2VzLCBjdXJyZW50UGFnZSk7XG5cbiAgICAgICAgICAgIGlmICghZGF0YS5zdHVkZW50cyB8fCBkYXRhLnN0dWRlbnRzLmxlbmd0aCA9PT0gMCkge1xuICAgICAgICAgICAgICAgIGNvbnN0IHRlbXBsYXRlID0gZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoIGNvbmZpZy50ZW1wbGF0ZXMubm9fZm91bmQgKTtcbiAgICAgICAgICAgICAgICBpZiAoIHRlbXBsYXRlICkge1xuICAgICAgICAgICAgICAgICAgICBjb25zdCBjbG9uZSA9IHRlbXBsYXRlLmNvbnRlbnQuY2xvbmVOb2RlKHRydWUpO1xuICAgICAgICAgICAgICAgICAgICAkKCBjb25maWcuc2VsZWN0b3JzLm5hdmlnYXRpb24gKS5oaWRlKCk7XG4gICAgICAgICAgICAgICAgICAgICRjb250YWluZXIuYXBwZW5kKGNsb25lKTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgcmV0dXJuO1xuICAgICAgICAgICAgfVxuXG4gICAgICAgICAgICAkKCBjb25maWcuc2VsZWN0b3JzLm5hdmlnYXRpb24gKS5zaG93KCk7XG5cbiAgICAgICAgICAgIHRvdGFsUGFnZXMgPSBkYXRhLnBhZ2VzO1xuICAgICAgICAgICAgKGRhdGEuc3R1ZGVudHMgfHwgW10pLmZvckVhY2goaXRlbSA9PiB7XG4gICAgICAgICAgICAgICAgY29uc3QgaHRtbCA9IHJlbmRlckl0ZW1UZW1wbGF0ZShpdGVtKTtcbiAgICAgICAgICAgICAgICAkY29udGFpbmVyLmFwcGVuZChodG1sKTtcbiAgICAgICAgICAgIH0pO1xuICAgICAgICB9KVxuICAgICAgICAuY2F0Y2goZXJyID0+IHtcbiAgICAgICAgICAgIGNvbnNvbGUuZXJyb3IoXCJFcnJvciBmZXRjaGluZyBpdGVtczpcIiwgZXJyKTtcbiAgICAgICAgICAgICRjb250YWluZXIuY3NzKFwiaGVpZ2h0XCIsIFwiYXV0b1wiKS5yZW1vdmVDbGFzcyggY29uZmlnLnNlbGVjdG9ycy5sb2FkaW5nICk7XG4gICAgICAgIH0pO1xuICAgIH1cblxuICAgIGZ1bmN0aW9uIHJlbmRlckl0ZW1UZW1wbGF0ZShpdGVtKSB7XG4gICAgICAgIGNvbnN0IHRlbXBsYXRlID0gZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoY29uZmlnLnRlbXBsYXRlcy5yb3cpO1xuICAgICAgICBpZiAoIXRlbXBsYXRlKSByZXR1cm4gJyc7XG4gICAgICAgIGNvbnN0IGNsb25lID0gdGVtcGxhdGUuY29udGVudC5jbG9uZU5vZGUodHJ1ZSk7XG5cbiAgICAgICAgY29uc3QgdXJsID0gbmV3IFVSTCggaXRlbS51cmwsIHdpbmRvdy5sb2NhdGlvbi5vcmlnaW4gKTtcblxuICAgICAgICBjbG9uZS5xdWVyeVNlbGVjdG9yKCdbbmFtZT1cInN0dWRlbnRbXVwiXScpLnZhbHVlID0gaXRlbS51c2VyX2lkO1xuICAgICAgICBpZiAoIGNsb25lLnF1ZXJ5U2VsZWN0b3IoJy5tYXN0ZXJzdHVkeS10YWJsZS1saXN0X19yb3ctLWxpbmsnKSApIHtcbiAgICAgICAgICAgIGNsb25lLnF1ZXJ5U2VsZWN0b3IoJy5tYXN0ZXJzdHVkeS10YWJsZS1saXN0X19yb3ctLWxpbmsnKS5ocmVmID0gdXJsLnRvU3RyaW5nKCk7XG4gICAgICAgIH1cbiAgICAgICAgY2xvbmUucXVlcnlTZWxlY3RvcignLm1hc3RlcnN0dWR5LXRhYmxlLWxpc3RfX3RkLS1uYW1lJykudGV4dENvbnRlbnQgPSBpdGVtLmRpc3BsYXlfbmFtZTtcbiAgICAgICAgY2xvbmUucXVlcnlTZWxlY3RvcignLm1hc3RlcnN0dWR5LXRhYmxlLWxpc3RfX3RkLS1lbWFpbCcpLnRleHRDb250ZW50ID0gaXRlbS5lbWFpbDtcbiAgICAgICAgY2xvbmUucXVlcnlTZWxlY3RvcignLm1hc3RlcnN0dWR5LXRhYmxlLWxpc3RfX3RkLS1qb2luZWQnKS50ZXh0Q29udGVudCA9IGl0ZW0ucmVnaXN0ZXJlZDtcbiAgICAgICAgY2xvbmUucXVlcnlTZWxlY3RvcignLm1hc3RlcnN0dWR5LXRhYmxlLWxpc3RfX3RkLS1lbnJvbGxlZCcpLnRleHRDb250ZW50ID0gaXRlbS5lbnJvbGxlZDtcbiAgICAgICAgaWYgKCBjbG9uZS5xdWVyeVNlbGVjdG9yKCcubWFzdGVyc3R1ZHktdGFibGUtbGlzdF9fdGQtLXBvaW50cycpICkge1xuICAgICAgICAgICAgY2xvbmUucXVlcnlTZWxlY3RvcignLm1hc3RlcnN0dWR5LXRhYmxlLWxpc3RfX3RkLS1wb2ludHMnKS50ZXh0Q29udGVudCA9IGl0ZW0ucG9pbnRzO1xuICAgICAgICB9XG5cbiAgICAgICAgcmV0dXJuIGNsb25lO1xuICAgIH1cblxuICAgIGZ1bmN0aW9uIHVwZGF0ZVBhZ2luYXRpb24odG90YWxQYWdlcywgY3VycmVudFBhZ2UpIHtcbiAgICAgICAgcmVuZGVyUGFnaW5hdGlvbih7XG4gICAgICAgICAgICBhamF4dXJsOiBzdG1fbG1zX2FqYXh1cmwsXG4gICAgICAgICAgICBub25jZTogY29uZmlnLm5vbmNlLFxuICAgICAgICAgICAgdG90YWxQYWdlcyxcbiAgICAgICAgICAgIGN1cnJlbnRQYWdlLFxuICAgICAgICAgICAgcGFnaW5hdGlvbkNvbnRhaW5lcjogY29uZmlnLnNlbGVjdG9ycy5wYWdpbmF0aW9uLFxuICAgICAgICAgICAgb25QYWdlQ2hhbmdlOiBmZXRjaEl0ZW1zLFxuICAgICAgICAgICAgZ2V0UGVyUGFnZVNlbGVjdG9yOiAoKSA9PiBjb25maWcuc2VsZWN0b3JzLnBlcl9wYWdlLFxuICAgICAgICB9KTtcbiAgICB9XG5cbiAgICBmdW5jdGlvbiBpbml0U2VhcmNoKCkge1xuICAgICAgICBjb25zdCAkaW5wdXQgPSAkKCBjb25maWcuc2VsZWN0b3JzLnNlYXJjaF9pbnB1dCApO1xuICAgICAgICBpZiAoICEgJGlucHV0Lmxlbmd0aCApIHJldHVybjtcblxuICAgICAgICBsZXQgdGltZXI7XG4gICAgICAgIGxldCBsYXN0UXVlcnkgPSAnJztcblxuICAgICAgICAkaW5wdXQub2ZmKFwiaW5wdXRcIikub24oXCJpbnB1dFwiLCBmdW5jdGlvbiAoKSB7XG4gICAgICAgICAgICBjbGVhclRpbWVvdXQodGltZXIpO1xuICAgICAgICAgICAgdGltZXIgPSBzZXRUaW1lb3V0KCgpID0+IHtcbiAgICAgICAgICAgICAgICBjb25zdCBxdWVyeSA9ICRpbnB1dC52YWwoKS50cmltKCk7XG4gICAgICAgICAgICAgICAgaWYgKHF1ZXJ5ICE9PSBsYXN0UXVlcnkpIHtcbiAgICAgICAgICAgICAgICAgICAgbGFzdFF1ZXJ5ID0gcXVlcnk7XG4gICAgICAgICAgICAgICAgICAgIGZldGNoSXRlbXMoJCggY29uZmlnLnNlbGVjdG9ycy5wZXJfcGFnZSApLnZhbCgpLCAxKTtcbiAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICB9LCAzMDApO1xuICAgICAgICB9KTtcbiAgICB9XG5cbiAgICBmdW5jdGlvbiBjaGVja0FsbCgpIHtcbiAgICAgICAgY29uc3QgJHNlbGVjdEFsbCA9ICQoY29uZmlnLnNlbGVjdG9ycy5jaGVja2JveEFsbCk7XG4gICAgICAgIGNvbnN0ICRkZWxldGVCdG4gPSAkKGNvbmZpZy5zZWxlY3RvcnMuZGVsZXRlQnRuKTtcblxuICAgICAgICBpZiAoICEgJHNlbGVjdEFsbC5sZW5ndGggKSByZXR1cm47XG5cbiAgICAgICAgZnVuY3Rpb24gdXBkYXRlRGVsZXRlQnRuKCkge1xuICAgICAgICAgICAgY29uc3QgYW55Q2hlY2tlZCA9ICQoY29uZmlnLnNlbGVjdG9ycy5jaGVja2JveCkuZmlsdGVyKCc6Y2hlY2tlZCcpLmxlbmd0aCA+IDA7XG4gICAgICAgICAgICAkZGVsZXRlQnRuLnByb3AoJ2Rpc2FibGVkJywgIWFueUNoZWNrZWQpO1xuICAgICAgICB9XG5cbiAgICAgICAgJHNlbGVjdEFsbC5vbignY2hhbmdlJywgZnVuY3Rpb24oKSB7XG4gICAgICAgICAgICBjb25zdCBpc0NoZWNrZWQgPSB0aGlzLmNoZWNrZWQ7XG4gICAgICAgICAgICAkKGNvbmZpZy5zZWxlY3RvcnMuY2hlY2tib3gpLnByb3AoJ2NoZWNrZWQnLCBpc0NoZWNrZWQpLnRyaWdnZXIoJ2NoYW5nZScpO1xuICAgICAgICB9KTtcblxuICAgICAgICAkKGRvY3VtZW50KS5vbignY2hhbmdlJywgY29uZmlnLnNlbGVjdG9ycy5jaGVja2JveCwgZnVuY3Rpb24oKSB7XG4gICAgICAgICAgICBjb25zdCAkYWxsID0gJChjb25maWcuc2VsZWN0b3JzLmNoZWNrYm94KTtcbiAgICAgICAgICAgIGNvbnN0IGNoZWNrZWRDbnQgPSAkYWxsLmZpbHRlcignOmNoZWNrZWQnKS5sZW5ndGg7XG5cbiAgICAgICAgICAgICRzZWxlY3RBbGwucHJvcCgnY2hlY2tlZCcsIGNoZWNrZWRDbnQgPT09ICRhbGwubGVuZ3RoKTtcblxuICAgICAgICAgICAgdXBkYXRlRGVsZXRlQnRuKCk7XG4gICAgICAgIH0pO1xuXG4gICAgICAgIHVwZGF0ZURlbGV0ZUJ0bigpO1xuICAgIH1cblxuICAgIGZ1bmN0aW9uIGRlbGV0ZVN0dWRlbnRzKCkge1xuICAgICAgICBjb25zdCB1cmwgPSBjb25maWcuYXBpQmFzZSArIGNvbmZpZy5lbmRwb2ludHMuZGVsZXRpbmc7XG4gICAgICAgIGNvbnN0IHtjaGVja2JveEFsbCwgZGVsZXRlQnRuLCBtb2RhbERlbGV0ZSwgY29udGFpbmVyLCByb3csIG5vX2ZvdW5kLCBsb2FkaW5nLCBjaGVja2JveCwgcGVyX3BhZ2V9ID0gY29uZmlnLnNlbGVjdG9ycztcblxuICAgICAgICBsZXQgc3R1ZGVudHMgPSBbXTtcblxuICAgICAgICAkKGRlbGV0ZUJ0bikub24oJ2NsaWNrJywgZSA9PiB7XG4gICAgICAgICAgICBlLnByZXZlbnREZWZhdWx0KCk7XG4gICAgICAgICAgICBzdHVkZW50cyA9ICQoJ2lucHV0W25hbWU9XCJzdHVkZW50W11cIl06Y2hlY2tlZCcpXG4gICAgICAgICAgICAgICAgLm1hcChmdW5jdGlvbigpIHsgcmV0dXJuIHRoaXMudmFsdWU7IH0pXG4gICAgICAgICAgICAgICAgLmdldCgpO1xuXG4gICAgICAgICAgICBpZiAoc3R1ZGVudHMubGVuZ3RoKSB7XG4gICAgICAgICAgICAgICAgJChtb2RhbERlbGV0ZSkuYWRkQ2xhc3MoJ21hc3RlcnN0dWR5LWFsZXJ0X29wZW4nKTtcbiAgICAgICAgICAgIH1cbiAgICAgICAgfSk7XG5cbiAgICAgICAgJChtb2RhbERlbGV0ZSkub24oJ2NsaWNrJywgXCJbZGF0YS1pZD0nY2FuY2VsJ10sIC5tYXN0ZXJzdHVkeS1hbGVydF9faGVhZGVyLWNsb3NlXCIsIGUgPT4ge1xuICAgICAgICAgICAgZS5wcmV2ZW50RGVmYXVsdCgpO1xuICAgICAgICAgICAgJChtb2RhbERlbGV0ZSkucmVtb3ZlQ2xhc3MoJ21hc3RlcnN0dWR5LWFsZXJ0X29wZW4nKTtcbiAgICAgICAgfSk7XG5cbiAgICAgICAgJChtb2RhbERlbGV0ZSkub24oJ2NsaWNrJywgXCJbZGF0YS1pZD0nc3VibWl0J11cIiwgZSA9PiB7XG4gICAgICAgICAgICBlLnByZXZlbnREZWZhdWx0KCk7XG4gICAgICAgICAgICBpZiAoIXN0dWRlbnRzLmxlbmd0aCkgcmV0dXJuO1xuXG4gICAgICAgICAgICAkKGNvbnRhaW5lcikuZmluZChgJHtyb3d9LCAke25vX2ZvdW5kfWApLnJlbW92ZSgpO1xuICAgICAgICAgICAgJChjb250YWluZXIpLmFkZENsYXNzKGxvYWRpbmcpO1xuICAgICAgICAgICAgJChtb2RhbERlbGV0ZSkucmVtb3ZlQ2xhc3MoJ21hc3RlcnN0dWR5LWFsZXJ0X29wZW4nKTtcbiAgICAgICAgICAgICQoY2hlY2tib3gpLnByb3AoJ2NoZWNrZWQnLCBmYWxzZSk7XG4gICAgICAgICAgICAkKCBjb25maWcuc2VsZWN0b3JzLm5hdmlnYXRpb24gKS5oaWRlKCk7XG4gICAgICAgICAgICAkKGNoZWNrYm94QWxsKS5wcm9wKCdjaGVja2VkJywgZmFsc2UpO1xuXG4gICAgICAgICAgICBmZXRjaCh1cmwsIHtcbiAgICAgICAgICAgICAgICBtZXRob2Q6ICdERUxFVEUnLFxuICAgICAgICAgICAgICAgIGhlYWRlcnM6IHtcbiAgICAgICAgICAgICAgICAgICAgJ1gtV1AtTm9uY2UnOiBjb25maWcubm9uY2UsXG4gICAgICAgICAgICAgICAgICAgICdDb250ZW50LVR5cGUnOiAnYXBwbGljYXRpb24vanNvbicsXG4gICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICBib2R5OiBKU09OLnN0cmluZ2lmeSh7IHN0dWRlbnRzIH0pXG4gICAgICAgICAgICB9KVxuICAgICAgICAgICAgLnRoZW4ocmVzID0+IHtcbiAgICAgICAgICAgICAgICBzdHVkZW50cyA9IFtdO1xuICAgICAgICAgICAgICAgIGlmICghcmVzLm9rKSB0aHJvdyBuZXcgRXJyb3IoYFNlcnZlciByZXNwb25kZWQgd2l0aCAke3Jlcy5zdGF0dXN9YCk7XG4gICAgICAgICAgICAgICAgcmV0dXJuIGZldGNoSXRlbXMoJChwZXJfcGFnZSkudmFsKCksIDEpO1xuICAgICAgICAgICAgfSlcbiAgICAgICAgICAgIC5jYXRjaChjb25zb2xlLmVycm9yKTtcbiAgICAgICAgfSk7XG4gICAgfVxuXG4gICAgZnVuY3Rpb24gc2VhcmNoQnlDb3Vyc2UoKSB7XG4gICAgICAgIGNvbnN0ICRpbnB1dCAgPSAkKCBjb25maWcuc2VsZWN0b3JzLnNlbGVjdEJ5Q291cnNlICk7XG4gICAgICAgIGNvbnN0ICRwYXJlbnQgPSAkaW5wdXQucGFyZW50KCk7XG4gICAgICAgIGNvbnN0IHsgYXBpQmFzZSwgZW5kcG9pbnRzLCBub25jZSB9ID0gY29uZmlnO1xuICAgICAgICBjb25zdCBVUkwgICAgICAgPSBhcGlCYXNlICsgZW5kcG9pbnRzLmNvdXJzZXM7XG4gICAgICAgIGNvbnN0IFBFUl9QQUdFICA9IDIwO1xuXG4gICAgICAgIGxldCBzdGF0aWNDb3Vyc2VzICAgID0gW107XG4gICAgICAgIGxldCBzdGF0aWNUb3RhbFBhZ2VzID0gMDtcblxuICAgICAgICBmdW5jdGlvbiBmZXRjaENvdXJzZXModGVybSA9ICcnLCBwYWdlID0gMSkge1xuICAgICAgICAgICAgcmV0dXJuICQuYWpheCh7XG4gICAgICAgICAgICAgICAgdXJsOiAgICAgIFVSTCxcbiAgICAgICAgICAgICAgICBtZXRob2Q6ICAgJ0dFVCcsXG4gICAgICAgICAgICAgICAgZGF0YVR5cGU6ICdqc29uJyxcbiAgICAgICAgICAgICAgICBoZWFkZXJzOiAgeyAnWC1XUC1Ob25jZSc6IG5vbmNlIH0sXG4gICAgICAgICAgICAgICAgZGF0YToge1xuICAgICAgICAgICAgICAgICAgICBzOiAgICAgICAgdGVybSxcbiAgICAgICAgICAgICAgICAgICAgcGVyX3BhZ2U6IFBFUl9QQUdFLFxuICAgICAgICAgICAgICAgICAgICBjdXJyZW50X3VzZXI6IDEsXG4gICAgICAgICAgICAgICAgICAgIHBhZ2U6ICAgICBwYWdlXG4gICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgfSk7XG4gICAgICAgIH1cblxuICAgICAgICBmdW5jdGlvbiB0cnVuY2F0ZVdpdGhFbGxpcHNpcyggdGV4dCApIHtcbiAgICAgICAgICAgIHJldHVybiB0ZXh0Lmxlbmd0aCA+IDIzID8gdGV4dC5zbGljZSgwLCAyMykgKyAn4oCmJyA6IHRleHQ7XG4gICAgICAgIH1cblxuICAgICAgICBmZXRjaENvdXJzZXMoKVxuICAgICAgICAgICAgLmRvbmUocmVzcG9uc2UgPT4ge1xuICAgICAgICAgICAgICAgIHN0YXRpY0NvdXJzZXMgICAgPSByZXNwb25zZS5jb3Vyc2VzIHx8IFtdO1xuICAgICAgICAgICAgICAgIHN0YXRpY1RvdGFsUGFnZXMgPSBwYXJzZUludChyZXNwb25zZS5wYWdlcywgMTApIHx8IDA7XG4gICAgICAgICAgICB9KVxuICAgICAgICAgICAgLmFsd2F5cyhpbml0U2VsZWN0Mik7XG5cbiAgICAgICAgZnVuY3Rpb24gaW5pdFNlbGVjdDIoKSB7XG4gICAgICAgICAgICAkcGFyZW50LnJlbW92ZUNsYXNzKCAnZmlsdGVyLXN0dWRlbnRzLWJ5LWNvdXJzZXMtZGVmYXVsdCcgKTtcblxuICAgICAgICAgICAgJGlucHV0LnNlbGVjdDIoe1xuICAgICAgICAgICAgICAgIGRyb3Bkb3duUGFyZW50OiAgICAgJHBhcmVudCxcbiAgICAgICAgICAgICAgICBwbGFjZWhvbGRlcjogICAgICAgICRpbnB1dC5kYXRhKCdwbGFjZWhvbGRlcicpLFxuICAgICAgICAgICAgICAgIGFsbG93Q2xlYXI6ICAgICAgICAgdHJ1ZSxcbiAgICAgICAgICAgICAgICBtaW5pbXVtSW5wdXRMZW5ndGg6IDAsXG4gICAgICAgICAgICAgICAgdGVtcGxhdGVTZWxlY3Rpb246IGZ1bmN0aW9uKGRhdGEpIHtcbiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIHRydW5jYXRlV2l0aEVsbGlwc2lzKCBkYXRhLnRleHQgKTtcbiAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgIGVzY2FwZU1hcmt1cDogZnVuY3Rpb24obWFya3VwKSB7XG4gICAgICAgICAgICAgICAgICAgIHJldHVybiBtYXJrdXA7XG4gICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICBhamF4OiB7XG4gICAgICAgICAgICAgICAgICAgIHRyYW5zcG9ydDogZnVuY3Rpb24ocGFyYW1zLCBzdWNjZXNzLCBmYWlsdXJlKSB7XG4gICAgICAgICAgICAgICAgICAgICAgICBjb25zdCB0ZXJtID0gcGFyYW1zLmRhdGEudGVybSB8fCAnJztcbiAgICAgICAgICAgICAgICAgICAgICAgIGNvbnN0IHBhZ2UgPSBwYXJzZUludChwYXJhbXMuZGF0YS5wYWdlLCAxMCkgfHwgMTtcblxuICAgICAgICAgICAgICAgICAgICAgICAgaWYgKCF0ZXJtICYmIHBhZ2UgPT09IDEpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICByZXR1cm4gc3VjY2Vzcyh7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHJlc3VsdHM6IHN0YXRpY0NvdXJzZXMubWFwKGl0ZW0gPT4gKHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGlkOiAgIGl0ZW0uSUQsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB0ZXh0OiBpdGVtLnBvc3RfdGl0bGVcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgfSkpLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBwYWdpbmF0aW9uOiB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBtb3JlOiBzdGF0aWNUb3RhbFBhZ2VzID4gMVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB9XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgfSk7XG4gICAgICAgICAgICAgICAgICAgICAgICB9XG5cbiAgICAgICAgICAgICAgICAgICAgICAgIGZldGNoQ291cnNlcyh0ZXJtLCBwYWdlKVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIC5kb25lKHJlc3BvbnNlID0+IHN1Y2Nlc3Moe1xuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICByZXN1bHRzOiAocmVzcG9uc2UuY291cnNlcyB8fCBbXSkubWFwKGl0ZW0gPT4gKHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGlkOiAgIGl0ZW0uSUQsXG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB0ZXh0OiBpdGVtLnBvc3RfdGl0bGVcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgfSkpLFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBwYWdpbmF0aW9uOiB7XG4gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBtb3JlOiBwYWdlIDwgKHBhcnNlSW50KHJlc3BvbnNlLnBhZ2VzLCAxMCkgfHwgMClcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIH0pKVxuICAgICAgICAgICAgICAgICAgICAgICAgICAgIC5mYWlsKGZhaWx1cmUpO1xuICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICBkZWxheTogMjUwLFxuICAgICAgICAgICAgICAgICAgICBwcm9jZXNzUmVzdWx0czogZGF0YSA9PiBkYXRhLFxuICAgICAgICAgICAgICAgICAgICBjYWNoZTogdHJ1ZVxuICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICB9KTtcblxuICAgICAgICAgICAgJGlucHV0Lm9uKCdzZWxlY3QyOnNlbGVjdCBzZWxlY3QyOmNsZWFyJywgZnVuY3Rpb24oZSkge1xuICAgICAgICAgICAgICAgIGlmIChlLnR5cGUgPT09ICdzZWxlY3QyOnNlbGVjdCcpIHtcbiAgICAgICAgICAgICAgICAgICAgY291cnNlSWQgPSBlLnBhcmFtcy5kYXRhLmlkO1xuICAgICAgICAgICAgICAgIH0gZWxzZSBpZiAoZS50eXBlID09PSAnc2VsZWN0MjpjbGVhcicpIHtcbiAgICAgICAgICAgICAgICAgICAgY291cnNlSWQgPSBudWxsO1xuICAgICAgICAgICAgICAgIH1cblxuICAgICAgICAgICAgICAgIGZldGNoSXRlbXMoJCggY29uZmlnLnNlbGVjdG9ycy5wZXJfcGFnZSApLnZhbCgpLCAxKTtcbiAgICAgICAgICAgIH0pO1xuICAgICAgICB9XG4gICAgfVxuXG4gICAgZnVuY3Rpb24gZXhwb3J0U3R1ZGVudHMoKSB7XG4gICAgICAgICQoIGNvbmZpZy5zZWxlY3RvcnMuZXhwb3J0ICkub24oICdjbGljaycsIGZ1bmN0aW9uICgpIHtcbiAgICAgICAgICAgIGxldCB1cmwgPSBjb25maWcuYXBpQmFzZSArIGNvbmZpZy5lbmRwb2ludHMuZXhwb3J0U3R1ZGVudHM7XG4gICAgICAgICAgICBjb25zdCBxdWVyeSA9IFtdO1xuICAgICAgICAgICAgY29uc3QgJHNlbGVjdEJ5Q291cnNlID0gJCggY29uZmlnLnNlbGVjdG9ycy5zZWxlY3RCeUNvdXJzZSApO1xuICAgICAgICAgICAgY29uc3QgY291cnNlSWQgPSAkc2VsZWN0QnlDb3Vyc2UubGVuZ3RoID8gJHNlbGVjdEJ5Q291cnNlLnZhbCgpLnRyaW0oKSA6ICcnO1xuICAgICAgICAgICAgY29uc3QgJGlucHV0U2VhcmNoID0gJCggY29uZmlnLnNlbGVjdG9ycy5zZWFyY2hfaW5wdXQgKTtcbiAgICAgICAgICAgIGNvbnN0IHNlYXJjaFF1ZXJ5ID0gJGlucHV0U2VhcmNoLmxlbmd0aCA/ICRpbnB1dFNlYXJjaC52YWwoKS50cmltKCkgOiAnJztcbiAgICAgICAgICAgIGNvbnN0IGRhdGVGcm9tID0gZ2V0RGF0ZUZyb20oKTtcbiAgICAgICAgICAgIGNvbnN0IGRhdGVUbyA9IGdldERhdGVUbygpO1xuXG4gICAgICAgICAgICBxdWVyeS5wdXNoKGBzaG93X2FsbF9lbnJvbGxlZD0xYCk7XG4gICAgICAgICAgICBxdWVyeS5wdXNoKGBzPSR7ZW5jb2RlVVJJQ29tcG9uZW50KHNlYXJjaFF1ZXJ5KX1gKTtcbiAgICAgICAgICAgIHF1ZXJ5LnB1c2goYGNvdXJzZV9pZD0ke2NvdXJzZUlkfWApO1xuICAgICAgICAgICAgcXVlcnkucHVzaChgZGF0ZV9mcm9tPSR7ZGF0ZUZyb219YCk7XG4gICAgICAgICAgICBxdWVyeS5wdXNoKGBkYXRlX3RvPSR7ZGF0ZVRvfWApO1xuICAgICAgICAgICAgaWYgKHF1ZXJ5Lmxlbmd0aCkgdXJsICs9IGA/JHtxdWVyeS5qb2luKFwiJlwiKX1gO1xuXG4gICAgICAgICAgICBmZXRjaCh1cmwsIHtcbiAgICAgICAgICAgICAgICBoZWFkZXJzOiB7XG4gICAgICAgICAgICAgICAgICAgIFwiWC1XUC1Ob25jZVwiOiBjb25maWcubm9uY2UsXG4gICAgICAgICAgICAgICAgICAgIFwiQ29udGVudC1UeXBlXCI6IFwiYXBwbGljYXRpb24vanNvblwiLFxuICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICB9KVxuICAgICAgICAgICAgLnRoZW4ocmVzID0+IHJlcy5qc29uKCkpXG4gICAgICAgICAgICAudGhlbihkYXRhID0+IHtcbiAgICAgICAgICAgICAgICBkb3dubG9hZENTViggZGF0YSApO1xuICAgICAgICAgICAgfSlcbiAgICAgICAgICAgIC5jYXRjaChlcnIgPT4ge1xuICAgICAgICAgICAgICAgIGNvbnNvbGUuZXJyb3IoXCJFcnJvciBleHBvcnQgaXRlbXM6XCIsIGVycik7XG4gICAgICAgICAgICB9KTtcbiAgICAgICAgfSk7XG5cbiAgICAgICAgZnVuY3Rpb24gZG93bmxvYWRDU1YoZGF0YSkge1xuICAgICAgICAgICAgbGV0IGNzdiA9IGNvbnZlcnRBcnJheU9mT2JqZWN0c1RvQ1NWKHsgZGF0YSB9KTtcbiAgICAgICAgICAgIGlmICghY3N2KSByZXR1cm47XG5cbiAgICAgICAgICAgIGNvbnN0IGZpbGVuYW1lID0gYGVucm9sbGVkX3N0dWRlbnRzLmNzdmA7XG4gICAgICAgICAgICBjb25zdCBjc3ZVdGYgPSAnZGF0YTp0ZXh0L2NzdjtjaGFyc2V0PXV0Zi04LCc7XG4gICAgICAgICAgICBjb25zdCBocmVmID0gZW5jb2RlVVJJKGNzdlV0ZiArICdcXHVGRUZGJyArIGNzdik7XG5cbiAgICAgICAgICAgIGNvbnN0IGxpbmsgPSBkb2N1bWVudC5jcmVhdGVFbGVtZW50KCdhJyk7XG4gICAgICAgICAgICBsaW5rLnNldEF0dHJpYnV0ZSgnaHJlZicsIGhyZWYpO1xuICAgICAgICAgICAgbGluay5zZXRBdHRyaWJ1dGUoJ2Rvd25sb2FkJywgZmlsZW5hbWUpO1xuICAgICAgICAgICAgbGluay5jbGljaygpO1xuICAgICAgICB9XG5cbiAgICAgICAgZnVuY3Rpb24gY29udmVydEFycmF5T2ZPYmplY3RzVG9DU1YoeyBkYXRhLCBjb2x1bW5EZWxpbWl0ZXIgPSAnLCcsIGxpbmVEZWxpbWl0ZXIgPSAnXFxyXFxuJyB9KSB7XG4gICAgICAgICAgICBpZiAoIUFycmF5LmlzQXJyYXkoZGF0YSkgfHwgZGF0YS5sZW5ndGggPT09IDApIHJldHVybiBudWxsO1xuXG4gICAgICAgICAgICBjb25zdCBrZXlzID0gT2JqZWN0LmtleXMoZGF0YVswXSk7XG4gICAgICAgICAgICBsZXQgcmVzdWx0ID0gJyc7XG5cbiAgICAgICAgICAgIHJlc3VsdCArPSBrZXlzLmpvaW4oY29sdW1uRGVsaW1pdGVyKSArIGxpbmVEZWxpbWl0ZXI7XG5cbiAgICAgICAgICAgIGRhdGEuZm9yRWFjaChpdGVtID0+IHtcbiAgICAgICAgICAgICAgICBrZXlzLmZvckVhY2goKGtleSwgaWR4KSA9PiB7XG4gICAgICAgICAgICAgICAgICAgIGlmIChpZHggPiAwKSByZXN1bHQgKz0gY29sdW1uRGVsaW1pdGVyO1xuXG4gICAgICAgICAgICAgICAgICAgIGxldCBjZWxsID0gaXRlbVtrZXldO1xuXG4gICAgICAgICAgICAgICAgICAgIGlmIChBcnJheS5pc0FycmF5KGNlbGwpKSB7XG4gICAgICAgICAgICAgICAgICAgICAgICByZXN1bHQgKz0gYFwiJHtjZWxsLm1hcCggaXRlbSA9PiBkZWNvZGVTdHIoIGl0ZW0gKSApLmpvaW4oJywnKX1cImA7XG4gICAgICAgICAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgICAgICAgICBjZWxsID0gY2VsbCA9PSBudWxsID8gJycgOiBTdHJpbmcoY2VsbCk7XG4gICAgICAgICAgICAgICAgICAgICAgICBpZiAoY2VsbC5pbmNsdWRlcyhjb2x1bW5EZWxpbWl0ZXIpIHx8IGNlbGwuaW5jbHVkZXMoJ1wiJykgfHwgY2VsbC5pbmNsdWRlcygnXFxuJykpIHtcbiAgICAgICAgICAgICAgICAgICAgICAgICAgICBjZWxsID0gYFwiJHtjZWxsLnJlcGxhY2UoL1wiL2csICdcIlwiJyl9XCJgO1xuICAgICAgICAgICAgICAgICAgICAgICAgfVxuICAgICAgICAgICAgICAgICAgICAgICAgcmVzdWx0ICs9IGNlbGw7XG4gICAgICAgICAgICAgICAgICAgIH1cbiAgICAgICAgICAgICAgICB9KTtcbiAgICAgICAgICAgICAgICByZXN1bHQgKz0gbGluZURlbGltaXRlcjtcbiAgICAgICAgICAgIH0pO1xuXG4gICAgICAgICAgICByZXR1cm4gcmVzdWx0O1xuICAgICAgICB9XG5cbiAgICAgICAgZnVuY3Rpb24gZGVjb2RlU3RyKCBzdHIgKSB7XG4gICAgICAgICAgICByZXR1cm4gc3RyLnJlcGxhY2UoLyYjKFxcZCspOy9nLCAoXywgY29kZSkgPT4gU3RyaW5nLmZyb21DaGFyQ29kZShjb2RlKSk7XG4gICAgICAgIH1cbiAgICB9XG5cbiAgICBmdW5jdGlvbiBkYXRlRmlsdGVyKCkge1xuICAgICAgICBpbml0aWFsaXplRGF0ZXBpY2tlcignI21hc3RlcnN0dWR5LWRhdGVwaWNrZXItc3R1ZGVudHMnKTtcblxuICAgICAgICBkb2N1bWVudC5hZGRFdmVudExpc3RlbmVyKCdkYXRlc1VwZGF0ZWQnLCBmdW5jdGlvbigpIHtcbiAgICAgICAgICAgIGZldGNoSXRlbXMoKTtcbiAgICAgICAgfSk7XG4gICAgfVxuXG4gICAgZnVuY3Rpb24gaXRlbXNTb3J0KCkge1xuICAgICAgICBkb2N1bWVudC5hZGRFdmVudExpc3RlbmVyKCdtc1NvcnRJbmRpY2F0b3JFdmVudCcsIGZ1bmN0aW9uKCBldmVudCApIHtcbiAgICAgICAgICAgIGxldCBvcmRlciAgID0gZXZlbnQuZGV0YWlsLnNvcnRPcmRlcixcbiAgICAgICAgICAgICAgICBvcmRlcmJ5ID0gZXZlbnQuZGV0YWlsLmluZGljYXRvci5wYXJlbnRzKCcubWFzdGVyc3R1ZHktdGNlbGxfX2hlYWRlcicpLmRhdGEoJ3NvcnQnKTtcblxuICAgICAgICAgICAgb3JkZXIgPSAnbm9uZScgPT09IG9yZGVyID8gJ2FzYycgOiBvcmRlcjtcbiAgICAgICAgICAgIGZldGNoSXRlbXMoICQoIGNvbmZpZy5zZWxlY3RvcnMucGVyX3BhZ2UgKS52YWwoKSwgMSwgb3JkZXJieSwgb3JkZXIgKTtcbiAgICAgICAgfSk7XG5cbiAgICAgICAgJCggJy5tYXN0ZXJzdHVkeS10Y2VsbF9fdGl0bGUnICkub24oICdjbGljaycsIGZ1bmN0aW9uKCkge1xuICAgICAgICAgICAgJCggJy5tYXN0ZXJzdHVkeS1zb3J0LWluZGljYXRvcicsICQoIHRoaXMgKS5wYXJlbnQoKSApLnRyaWdnZXIoICdjbGljaycgKTtcbiAgICAgICAgfSk7XG4gICAgfVxufSkoalF1ZXJ5KTtcbiJdfQ==
