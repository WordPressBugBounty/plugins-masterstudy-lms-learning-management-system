"use strict";

(function ($) {
  $(document).ready(function () {
    var completeBlock = $('.masterstudy-single-course-complete');
    completeBlock.removeAttr('style');
    if (course_completed.completed) {
      $('body').addClass('masterstudy-single-course-complete_hidden');
      completeBlock.addClass('masterstudy-single-course-complete_active');
      stmLmsInitProgress(completeBlock);
    }
    if (course_completed.block_enabled && course_completed.user_id) {
      stmLmsInitProgress(completeBlock);
    }
    $('.masterstudy-single-course-complete-block__details').on('click', function () {
      $('body').addClass('masterstudy-single-course-complete_hidden');
      var completeBlock = $(this).parent().next('.masterstudy-single-course-complete');
      if (completeBlock.length) {
        $('body').addClass('masterstudy-single-course-complete_hidden');
        completeBlock.addClass('masterstudy-single-course-complete_active');
        stmLmsInitProgress(completeBlock);
      }
    });
    $('.masterstudy-single-course-complete').on('click', function (event) {
      if ($(event.target).hasClass('masterstudy-single-course-complete')) {
        $('.masterstudy-single-course-complete').removeClass('masterstudy-single-course-complete_active');
        $('body').removeClass('masterstudy-single-course-complete_hidden');
      }
    });
    $('.masterstudy-single-course-complete__buttons, .masterstudy-single-course-complete__close').on('click', function (event) {
      $('.masterstudy-single-course-complete').removeClass('masterstudy-single-course-complete_active');
      $('body').removeClass('masterstudy-single-course-complete_hidden');
    });
  });
  function stmLmsInitProgress(statsContainer) {
    var course_id = course_completed.elementor_widget ? statsContainer.data('course-id') : course_completed.course_id;
    var loading = true;
    var stats = {};
    var ajaxUrl = course_completed.ajax_url + '?action=stm_lms_total_progress&course_id=' + course_id + '&nonce=' + course_completed.nonce;
    $.get(ajaxUrl, function (response) {
      stats = response;
      loading = false;
      course_completed_success(statsContainer, stats);
    });
    function course_completed_success(statsContainer, stats) {
      statsContainer.find('.masterstudy-single-course-complete__loading').hide();
      statsContainer.find('.masterstudy-single-course-complete__success').show();
      if ($('body').hasClass('rtl')) {
        statsContainer.find('.masterstudy-single-course-complete__opportunities-percent').html('%' + stats.course.progress_percent);
      } else {
        statsContainer.find('.masterstudy-single-course-complete__opportunities-percent').html(stats.course.progress_percent + '%');
      }
      if (stats.title) {
        statsContainer.find('h2').show().html(stats.title);
      }
      ['lesson', 'multimedia', 'quiz', 'assignment'].forEach(function (type) {
        if (stats.curriculum.hasOwnProperty(type)) {
          statsContainer.find(".masterstudy-single-course-complete__curiculum-statistic-item_type-".concat(type)).addClass('show-item');
          statsContainer.find(".masterstudy-single-course-complete__curiculum-statistic-item_type-".concat(type, " .masterstudy-single-course-complete__curiculum-statistic-item_completed")).html(stats.curriculum[type].completed);
          statsContainer.find(".masterstudy-single-course-complete__curiculum-statistic-item_type-".concat(type, " .masterstudy-single-course-complete__curiculum-statistic-item_total")).html(stats.curriculum[type].total);
        }
      });
      statsContainer.find('.masterstudy-button_course_button').attr('href', stats.url);
    }
  }
})(jQuery);