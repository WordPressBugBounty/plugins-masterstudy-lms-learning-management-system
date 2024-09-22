"use strict";

(function ($) {
  $(document).ready(function () {
    var manageStudents = $('#masterstudy-manage-students');
    var table = manageStudents.find('.masterstudy-table');
    var tableBody = table.find('.masterstudy-tbody');
    var per_page = 10,
      search = '',
      order = '',
      orderby = '',
      page = 1;
    var pagination = new MasterstudyPagination({
      visibleNumber: 3,
      perPageLimit: per_page,
      dataListContainer: '.masterstudy-tbody',
      dataItemElementsClass: '.masterstudy-table__item',
      dataItemExcludeClass: 'masterstudy-table__item--hidden',
      dataItemDisplayCss: 'flex'
    });
    pagination.onPageChange(function (page, isPageLoadedBefore) {
      if (1 !== page) {
        if (!isPageLoadedBefore) {
          table.find('.masterstudy-tfooter').addClass('masterstudy-tfooter--hidden');
          fetchData({
            per_page: per_page,
            s: search,
            page: page,
            orderby: orderby,
            order: order
          }, false);
        }
      }
    });
    // on page load.
    fetchData({
      per_page: per_page,
      s: search,
      page: page
    });
    document.addEventListener('msAddStudentEvent', function (event) {
      if (event.detail.progress === 100) {
        fetchData({
          per_page: per_page,
          s: search,
          page: 1
        }, true);
      }
    });
    document.addEventListener('msSortIndicatorEvent', function (event) {
      order = event.detail.sortOrder;
      orderby = event.detail.indicator.parents('.masterstudy-tcell__header').data('sort');
      table.find('.masterstudy-tfooter').addClass('masterstudy-tfooter--hidden');
      order = 'none' === order ? 'asc' : order;
      fetchData({
        per_page: per_page,
        s: search,
        page: page,
        orderby: orderby,
        order: order
      }, true);
    });
    document.addEventListener('msfieldEvent', function (event) {
      var fieldValue = event.detail.value;
      table.find('.masterstudy-tfooter').addClass('masterstudy-tfooter--hidden');
      switch (event.detail.name) {
        case 's':
          search = fieldValue ? fieldValue : '';
          $('.masterstudy-select__clear').click();
          $('.masterstudy-select').removeClass('masterstudy-select_open');
          break;
        case 'per_page':
          per_page = fieldValue ? fieldValue : 10;
          break;
      }
      fetchData({
        per_page: per_page,
        s: search,
        page: 1
      }, true);
    });
    function fetchData() {
      var params = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
      var isClearData = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : true;
      var urlParams = new URLSearchParams(window.location.search);
      var courseId = urlParams.get('course_id');
      if (courseId) {
        if (isClearData) {
          clearTableData();
        }
        params.course_id = courseId;
        var queryString = new URLSearchParams(params).toString();
        var apiUrl = "".concat(ms_lms_resturl, "/students?").concat(queryString);
        loader();
        fetch(apiUrl, {
          method: 'GET',
          headers: {
            'X-WP-Nonce': ms_lms_nonce,
            'Content-Type': 'application/json'
          }
        }).then(function (response) {
          if (response.ok) {
            return response.json();
          }
        }).then(function (response) {
          setTimeout(function () {
            loader(true);
            updateUI(response, isClearData);
          }, 1500);
        })["catch"](function (error) {
          throw error;
        });
      }
    }
    function updateUI(data, isClearData) {
      if (data) {
        if (isClearData) {
          clearTableData();
        }
        notFound(data.max_pages);
        manageStudents.find('.masterstudy-manage-students__count-number').text(data.total);
        pagination.paginate(data.max_pages, data.per_page, isClearData);
        $.each(data.students, function (order, students) {
          addDataToTable(order, students, data.page, data.per_page);
        });
      }
    }
    function clearTableData() {
      var tableItems = tableBody.find('.masterstudy-table__item');
      $.each(tableItems, function (i, item) {
        if (1 < i) {
          $(item).remove();
        }
      });
    }
    function notFound(maxPages) {
      tableBody.find('.masterstudy-table__item.not-founded').remove();
      if (0 === maxPages) {
        var tableItem = tableBody.find('.masterstudy-table__item').eq(1).clone();
        tableItem.removeClass('masterstudy-table__item--hidden');
        tableItem.addClass('not-founded');
        tableBody.append(tableItem);
      }
      if (maxPages <= 1 && 10 === per_page) {
        table.find('.masterstudy-tfooter').addClass('masterstudy-tfooter--hidden');
      } else {
        if (maxPages === 1) {
          $('.masterstudy-pagination').addClass('hidden');
        } else {
          $('.masterstudy-pagination').removeClass('hidden');
        }
        table.find('.masterstudy-tfooter').removeClass('masterstudy-tfooter--hidden');
      }
    }
    function addDataToTable(order, data, page, per_page) {
      var tableItem = tableBody.find('.masterstudy-table__item:first').clone();
      tableItem.removeClass('masterstudy-table__item--hidden');
      tableItem.find('.masterstudy-tcell__data').each(function (i, cell) {
        var _data$student$avatar, _data$student$key, _data$student$key2, _data$start_time;
        var key = $(cell).data('key');
        var value = data[key] || data[key] === 0 ? data[key] : '';
        switch (key) {
          case 'login':
            $(cell).before((_data$student$avatar = data['student']['avatar']) !== null && _data$student$avatar !== void 0 ? _data$student$avatar : '');
            $(cell).text((_data$student$key = data['student'][key]) !== null && _data$student$key !== void 0 ? _data$student$key : '');
            break;
          case 'email':
            $(cell).text((_data$student$key2 = data['student'][key]) !== null && _data$student$key2 !== void 0 ? _data$student$key2 : '');
            break;
          case 'ago':
            $(cell).text(value);
            $(cell).attr('data-value', (_data$start_time = data['start_time']) !== null && _data$start_time !== void 0 ? _data$start_time : 0);
            break;
          case 'progress_percent':
            $(cell).find('.masterstudy-progress__bar-filled').css({
              width: "".concat(value, "%")
            });
          case 'progress_link':
            $(cell).find('[data-id="manage-students-view-progress"]').attr('href', value);
            break;
          case 'course_id':
            $(cell).find('[data-id="manage-students-delete"]').attr('data-course-id', value);
            $(cell).find('[data-id="manage-students-delete"]').attr('data-student-id', data['user_id'] || 0);
            break;
          default:
            $(cell).text(value);
            break;
        }
      });
      tableItem.attr('data-initial-order', order + 1 + (page - 1) * per_page);
      tableBody.append(tableItem);
    }
    function loader() {
      var isToHide = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : false;
      if (isToHide) {
        tableBody.find('.masterstudy-loader').remove();
      } else {
        var prevLoader = tableBody.find('.masterstudy-loader');
        if (prevLoader.length < 1) {
          var _loader = $('.masterstudy-loader').clone();
          _loader.css({
            display: 'block'
          });
          tableBody.append(_loader);
        }
      }
    }
    var alertPopup = $("[data-id='masterstudy-manage-students-delete-student']");
    var student_id = '';
    alertPopup.css('display', 'none');
    $('body').on('click', '[data-id="manage-students-delete"]', function (e) {
      e.preventDefault();
      alertPopup.css('display', 'flex');
      alertPopup.addClass('masterstudy-alert_open');
      student_id = $(this).data('student-id');
    });
    alertPopup.find("[data-id='submit']").click(function (e) {
      e.preventDefault();
      var urlParams = new URLSearchParams(window.location.search);
      var course_id = urlParams.get('course_id');
      var apiUrl = "".concat(ms_lms_resturl, "/student/").concat(course_id, "/").concat(student_id);
      fetch(apiUrl, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': ms_lms_nonce,
          'Content-Type': 'application/json'
        }
      }).then(function (response) {
        if (response.ok) {
          return response.json();
        }
      }).then(function (response) {
        alertPopup.removeClass('masterstudy-alert_open');
        if ('ok' === response.status) {
          fetchData({
            per_page: per_page,
            s: search,
            page: page
          }, true);
        }
      })["catch"](function (error) {
        throw error;
      });
    });
    alertPopup.find("[data-id='cancel']").click(closeAlertPopup);
    alertPopup.find('.masterstudy-alert__header-close').click(closeAlertPopup);
    function closeAlertPopup(e) {
      e.preventDefault();
      alertPopup.removeClass('masterstudy-alert_open');
    }
    $('#main').addClass('masterstudy-manage-students__main');
  });
})(jQuery);