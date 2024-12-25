"use strict";

(function ($) {
  $(document).ready(function () {
    $('.masterstudy-single-course-complete').removeAttr('style');
    if (course_completed.completed) {
      $('body').addClass('masterstudy-single-course-complete_hidden');
      $('.masterstudy-single-course-complete').addClass('masterstudy-single-course-complete_active');
      stmLmsInitProgress();
    }
    if (course_completed.block_enabled && course_completed.user_id) {
      stmLmsInitProgress();
    }
    $('.masterstudy-single-course-complete-block__details').on('click', function () {
      $('body').addClass('masterstudy-single-course-complete_hidden');
      $('.masterstudy-single-course-complete').addClass('masterstudy-single-course-complete_active');
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
  function stmLmsInitProgress() {
    var course_id = course_completed.course_id;
    var $statsContainer = $('#masterstudy-single-course-complete');
    var loading = true;
    var stats = {};
    var ajaxUrl = course_completed.ajax_url + '?action=stm_lms_total_progress&course_id=' + course_id + '&nonce=' + course_completed.nonce;
    $.get(ajaxUrl, function (response) {
      stats = response;
      loading = false;
      course_completed_success();
    });
    function course_completed_success() {
      $('.masterstudy-single-course-complete__loading').hide();
      $('.masterstudy-single-course-complete__success').show();
      if ($('body').hasClass('rtl')) {
        $('.masterstudy-single-course-complete__opportunities-percent').html('%' + stats.course.progress_percent);
      } else {
        $('.masterstudy-single-course-complete__opportunities-percent').html(stats.course.progress_percent + '%');
      }
      if (stats.title) {
        $statsContainer.find('h2').show();
        $statsContainer.find('h2').html(stats.title);
      }
      if (stats.curriculum.hasOwnProperty('lesson')) {
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-lesson').addClass('show-item');
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-lesson .masterstudy-single-course-complete__curiculum-statistic-item_completed').html(stats.curriculum.lesson.completed);
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-lesson .masterstudy-single-course-complete__curiculum-statistic-item_total').html(stats.curriculum.lesson.total);
      }
      if (stats.curriculum.hasOwnProperty('multimedia')) {
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-multimedia').addClass('show-item');
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-multimedia .masterstudy-single-course-complete__curiculum-statistic-item_completed').html(stats.curriculum.multimedia.completed);
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-multimedia .masterstudy-single-course-complete__curiculum-statistic-item_total').html(stats.curriculum.multimedia.total);
      }
      if (stats.curriculum.hasOwnProperty('quiz')) {
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-quiz').addClass('show-item');
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-quiz .masterstudy-single-course-complete__curiculum-statistic-item_completed').html(stats.curriculum.quiz.completed);
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-quiz .masterstudy-single-course-complete__curiculum-statistic-item_total').html(stats.curriculum.quiz.total);
      }
      if (stats.curriculum.hasOwnProperty('assignment')) {
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-assignment').addClass('show-item');
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-assignment .masterstudy-single-course-complete__curiculum-statistic-item_completed').html(stats.curriculum.assignment.completed);
        $statsContainer.find('.masterstudy-single-course-complete__curiculum-statistic-item_type-assignment .masterstudy-single-course-complete__curiculum-statistic-item_total').html(stats.curriculum.assignment.total);
      }
      $('.masterstudy-button_course_button').attr('href', stats.url);
    }
  }
})(jQuery);