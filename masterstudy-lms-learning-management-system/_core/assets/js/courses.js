"use strict";

function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
(function ($) {
  window.addEventListener('load', function () {
    var coming_soon_containers = document.querySelectorAll('.coming-soon-card-countdown-container');
    coming_soon_containers.forEach(function (container) {
      if (container.clientWidth < 220) {
        container.classList.add('smaller-container');
      }
    });
  });
  $(document).ready(function () {
    /**
     *
     * @var courses_view
     */
    var $more = $('.stm_lms_load_more_courses');
    if ($('body').hasClass('stm_lms_infinite')) {
      $(window).on('scroll', function () {
        $more.each(function () {
          if (!$(this).is(":hidden")) {
            var $this = $(this);
            var position = $this.position().top;
            var top = $(document).scrollTop();
            if (position - 100 < top) {
              $more.click();
            }
          }
        });
      });
    }
    $more.on('click', function (e) {
      if ($(this).is(":hidden")) return false;
      if ($(this).hasClass('loading')) return false;
      e.preventDefault();
      var offset = $(this).attr('data-offset');
      var args = $(this).attr('data-args');
      var $grid = $(this).closest('.stm_lms_courses').find('[data-pages]:last');
      var total = $grid.attr('data-pages');
      var suburl = $(this).attr('data-url');
      var $sort = $('.courses_filters .stm_lms_courses_grid__sort select');
      var data = {
        offset: offset,
        args: args,
        action: 'stm_lms_load_content',
        nonce: stm_lms_nonces['load_content']
      };
      if ($sort.length) {
        data['sort'] = $sort.val();
      }
      if (total == offset) return false;
      $.ajax({
        url: stm_lms_ajaxurl + suburl,
        dataType: 'json',
        context: this,
        data: data,
        beforeSend: function beforeSend() {
          $(this).addClass('loading');
        },
        complete: function complete(data) {
          data = data['responseJSON'];
          $(this).removeClass('loading');
          if (_typeof(data) === "object" && data.hasOwnProperty('search_title')) {
            $('.courses_filters__title').find('.lms-courses-search-result').html(data['search_title']);
          }
          $grid.append(data['content']);
          $(this).attr('data-offset', data['page']);
          hide_button($(this), data['page']);
        }
      });
    });
    $more.each(function () {
      hide_button($(this), 1);
    });
    if (typeof courses_view !== 'undefined' && courses_view.type === 'list') {
      $('.stm_lms_courses_wrapper').addClass('stm_lms_courses_list_view');
    } else if (typeof courses_view !== 'undefined' && courses_view.type === 'grid') {
      $('.stm_lms_courses_wrapper').addClass('stm_lms_courses_grid_view');
    }

    /** Search part **/
    $('#lms-search-btn').on('click', function () {
      search();
    });

    /** Submit Search on Enter **/
    $('#lms-search-input').keypress(function (event) {
      var keycode = event.keyCode ? event.keyCode : event.which;
      if (keycode == '13') {
        search();
      }
    });
    function search() {
      var $form = $('.stm_lms_courses__archive_filter form');
      var searchAction = $('#lms-search-input').data('action');
      var searchValue = $('#lms-search-input').val();
      var suburl = '';

      /** if filter form enabled **/
      if ($form.length) {
        $form.find('input[name="search"]').val(searchValue);
        suburl = '?' + $form.serialize();
        history.pushState({}, null, location.origin + location.pathname + suburl);
        $form.submit();
      }
      if (searchAction.trim()) {
        suburl = '?search=' + searchValue;
        history.pushState({}, null, location.origin + location.pathname + suburl);
        var $sort = $('.courses_filters .stm_lms_courses_grid__sort select');
        var $btn = $('.stm_lms_load_more_courses');
        var $grid = $('.stm_lms_courses').find('[data-pages]:last');
        var args = $btn.attr('data-args');
        var sort_value = $sort.val();
        $btn.attr('data-url', suburl);
        $.ajax({
          url: stm_lms_ajaxurl + suburl,
          dataType: 'json',
          context: this,
          data: {
            offset: 0,
            args: args,
            sort: sort_value,
            featured: false,
            action: 'stm_lms_load_content',
            nonce: stm_lms_nonces['load_content']
          },
          beforeSend: function beforeSend() {
            $grid.closest('.stm_lms_courses__archive').addClass('loading');
            $([document.documentElement, document.body]).animate({
              scrollTop: $('.stm_lms_courses__archive').offset().top - 130
            }, 1000);
          },
          complete: function complete(data) {
            data = data['responseJSON'];
            if (_typeof(data) === "object" && data.hasOwnProperty('search_title')) {
              $('.featured-courses').remove();
              $('.featured-head').remove();
            }
            $grid.closest('.stm_lms_courses__archive').removeClass('loading');
            $grid.html(data['content']).attr('data-pages', data.pages);
            $btn.attr('data-offset', data['page']);
            hide_button($btn, data['page']);
          }
        });
      }
      if (searchAction.trim() && searchValue.length <= 0) {
        history.pushState({}, null, location.origin + location.pathname);
      }
    }
    if ($('#ms-lms-multi-instructors-zero-co-courses-found').length) {
      $('.masterstudy-lms-public-account-courses-tabs .tabs').css('display', 'none');
    }
    /** search part | End **/
    $('.masterstudy-lms-public-account-courses-tabs li').click(function () {
      $('.masterstudy-lms-public-account-courses-tabs li').removeClass('active');
      $(this).addClass('active');
      var tabId = $(this).attr('id');
      if (tabId === 'tab_co_courses') {
        $('#stm_lms_instructor_co_courses').removeClass('inactive');
        $('#stm_lms_instructor_courses').addClass('inactive');
      } else if (tabId === 'tab_courses') {
        $('#stm_lms_instructor_co_courses').addClass('inactive');
        $('#stm_lms_instructor_courses').removeClass('inactive');
      }
    });
    if ($('.stm-lms-public-instructor').length > 0) {
      $('.stm_lms_instructor_co_courses .stm_lms_instructor_courses__top').css('display', 'none');
    }
  });
})(jQuery);
function hide_button($btn, page) {
  var $container = $btn.closest('.stm_lms_courses').find('[data-pages]:last');
  var pages = $container.attr('data-pages');
  if (parseInt(pages) === page || parseInt(pages) < page || !$container.length) {
    $btn.slideUp();
    $btn.closest('.stm_lms_courses').addClass('all_loaded');
  } else {
    $btn.slideDown();
    $btn.closest('.stm_lms_courses').removeClass('all_loaded');
  }
}