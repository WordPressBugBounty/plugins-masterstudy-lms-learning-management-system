"use strict";

(function ($) {
  var videoPlaying = null;
  var timeout;
  function toggleQuestions(question) {
    var dataId = question.attr('data-number-question');
    var parent_bank = question.parent().closest('.masterstudy-course-player-question');
    var other_questions = question.closest('.masterstudy-course-player-quiz__questions').find('.masterstudy-course-player-question').not('.masterstudy-course-player-question_question-bank').not("[data-number-question=\"".concat(dataId, "\"]"));
    if (parent_bank.hasClass('masterstudy-course-player-question_question-bank')) {
      parent_bank.removeClass('masterstudy-course-player-question_hide');
    }
    question.removeClass('masterstudy-course-player-question_hide');
    other_questions.addClass('masterstudy-course-player-question_hide');
  }
  function indicateAnswers($scope) {
    var pagers = $('.masterstudy-course-player-quiz__pagination .masterstudy-pagination__item', $scope);
    $('.masterstudy-course-player-question:not(.masterstudy-course-player-question_question-bank)', $scope).each(function () {
      if ($(this).hasClass('masterstudy-course-player-question_correct') || $(this).hasClass('masterstudy-course-player-question_wrong')) {
        var index = $(this).data('number-question') - 1;
        pagers.eq(index).find('.masterstudy-pagination__item-indicator').addClass('masterstudy-pagination__item-indicator_done');
      }
    });
  }
  function updateIndicatorState($question) {
    if ($('.masterstudy-course-player-quiz').hasClass('masterstudy-course-player-quiz_show-answers')) {
      return;
    }
    var hasValue = false;
    var pager = $('.masterstudy-course-player-quiz__pagination .masterstudy-pagination__item');
    $question.find('input').each(function (index, input) {
      switch ($(input).prop('type')) {
        case 'radio':
        case 'checkbox':
          if ($(input).prop('checked')) {
            hasValue = true;
          }
          break;
        case 'hidden':
          hasValue = false;
          break;
        default:
          if ($(input).val()) {
            hasValue = true;
          }
      }
      if (hasValue) {
        return false;
      }
    });
    var value = $question.data('number-question') - 1;
    if (hasValue) {
      pager.eq(value).find('.masterstudy-pagination__item-indicator').addClass('masterstudy-pagination__item-indicator_done');
    } else {
      pager.eq(value).find('.masterstudy-pagination__item-indicator').removeClass('masterstudy-pagination__item-indicator_done');
    }
  }
  function handleKeywordInput() {
    clearTimeout(timeout);
    var $input = $(this);
    var $parent = $input.closest('.masterstudy-course-player-quiz-keywords');
    var $keywordField = $parent.find('.masterstudy-course-player-quiz-keywords__keyword-to-fill');
    var answers = window[$parent.attr('data-quiz-keywords')];
    var userAnswer = $input.val().toLowerCase();
    if (!Array.isArray(answers)) {
      return;
    }
    $parent.find('.masterstudy-course-player-quiz-keywords__input').removeAttr('required');
    timeout = setTimeout(function () {
      var matched = false;
      answers.forEach(function (answer, answerIndex) {
        if (userAnswer.includes(answer)) {
          matched = true;
          $input.val('').focus();
          var $insertTo = $parent.find('.masterstudy-course-player-quiz-keywords__answer_' + answerIndex + ' .masterstudy-course-player-quiz-keywords__value');
          var $flying = $parent.find('.masterstudy-course-player-quiz-keywords__flying-word').text(answer);
          var childPos = $insertTo.offset();
          var parentPos = $insertTo.closest('.masterstudy-course-player-quiz-keywords').offset();
          $flying.addClass('visible').css({
            top: childPos.top - parentPos.top,
            left: childPos.left - parentPos.left
          });
          setTimeout(function () {
            $insertTo.text(answer);
            answers[answerIndex] = '(^-^*)/';
            $flying.text('').css({
              top: '20px',
              left: '14px'
            }).removeClass('visible');
            $keywordField.removeClass('masterstudy-course-player-quiz-keywords__keyword-to-fill_invalid');
            $keywordField.removeClass('masterstudy-course-player-quiz-keywords__keyword-to-fill_shake');
            addAnswer();
          }, 500);
        }
      });
      if (!matched && userAnswer.trim() !== '') {
        $keywordField.addClass('masterstudy-course-player-quiz-keywords__keyword-to-fill_invalid');
        $keywordField.removeClass('masterstudy-course-player-quiz-keywords__keyword-to-fill_shake');
        void $keywordField[0].offsetWidth;
        $keywordField.addClass('masterstudy-course-player-quiz-keywords__keyword-to-fill_shake');
      }
    }, 300);
  }
  function addAnswer() {
    $('.masterstudy-course-player-quiz-keywords').each(function () {
      var answers = $(this).find('.masterstudy-course-player-quiz-keywords__answer .masterstudy-course-player-quiz-keywords__value').map(function () {
        return $(this).text().trim();
      }).get().filter(Boolean);
      var $input = $(this).find('.masterstudy-course-player-quiz-keywords__input');
      var keywords_val = "[stm_lms_keywords]" + answers.join('[stm_lms_sep]');
      if (answers.length) {
        $input.val(keywords_val);
      }
    });
  }
  function pauseVideos() {
    var videos = document.getElementsByTagName("video");
    for (var i = 0; i < videos.length; i++) {
      videos[i].pause();
    }
  }
  function onPlay() {
    if (videoPlaying && videoPlaying !== this) {
      videoPlaying.pause();
    }
    videoPlaying = this;
  }
  function bindVideoPlayers($scope) {
    var videos = $('video', $scope).toArray();
    videos.forEach(function (video) {
      video.removeEventListener('play', onPlay);
      video.addEventListener('play', onPlay);
    });
  }
  function init() {
    var $scope = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : $(document);
    if (typeof MasterstudyAudioPlayer !== 'undefined') {
      MasterstudyAudioPlayer.init({
        selector: '.masterstudy-audio-player',
        showDeleteButton: false
      });
    }
    $('.masterstudy-course-player-quiz__pagination .masterstudy-pagination__item-block', $scope).off('click.masterstudyCoursePlayerQuestions').on('click.masterstudyCoursePlayerQuestions', function () {
      $(".masterstudy-course-player-quiz__form [data-number-question=\"".concat($(this).data('id'), "\"]")).each(function () {
        toggleQuestions($(this));
      });
    });
    $('.masterstudy-course-player-quiz__pagination .masterstudy-pagination__button-next', $scope).off('click.masterstudyCoursePlayerQuestions').on('click.masterstudyCoursePlayerQuestions', function () {
      var currentPage = $(this).parent().find('.masterstudy-pagination__item_current .masterstudy-pagination__item-block').data('id');
      $(".masterstudy-course-player-quiz__form [data-number-question=\"".concat(currentPage + 1, "\"]")).each(function () {
        toggleQuestions($(this));
      });
    });
    $('.masterstudy-course-player-quiz__pagination .masterstudy-pagination__button-prev', $scope).off('click.masterstudyCoursePlayerQuestions').on('click.masterstudyCoursePlayerQuestions', function () {
      var currentPage = $(this).parent().find('.masterstudy-pagination__item_current .masterstudy-pagination__item-block').data('id');
      $(".masterstudy-course-player-quiz__form [data-number-question=\"".concat(currentPage - 1, "\"]")).each(function () {
        toggleQuestions($(this));
      });
    });
    if ($('.masterstudy-course-player-quiz__pagination', $scope).length > 0) {
      indicateAnswers($scope);
      $('.masterstudy-course-player-question:not(.masterstudy-course-player-question_question-bank)', $scope).off('.masterstudyCoursePlayerQuestionsIndicator').on('click.masterstudyCoursePlayerQuestionsIndicator mouseleave.masterstudyCoursePlayerQuestionsIndicator touchend.masterstudyCoursePlayerQuestionsIndicator', function () {
        updateIndicatorState($(this));
      });
    }

    // submit question
    $(document).off('click.masterstudyCoursePlayerQuestionsAnswer', '.masterstudy-course-player-answer').on('click.masterstudyCoursePlayerQuestionsAnswer', '.masterstudy-course-player-answer', function () {
      if (!$('.masterstudy-course-player-quiz').hasClass('masterstudy-course-player-quiz_show-answers')) {
        var input = $(this).find('input');
        if (input.is(':radio')) {
          input.prop('checked', true);
          $(this).siblings('.masterstudy-course-player-answer').find('input:radio').prop('checked', false);
          $(this).addClass('masterstudy-course-player-answer_checked');
          $(this).find('.masterstudy-course-player-answer__radio').addClass('masterstudy-course-player-answer__radio_checked');
          $(this).siblings('.masterstudy-course-player-answer').removeClass('masterstudy-course-player-answer_checked');
          $(this).siblings('.masterstudy-course-player-answer').find('.masterstudy-course-player-answer__radio').removeClass('masterstudy-course-player-answer__radio_checked');
        } else if (input.is(':checkbox')) {
          if (!input.prop('checked')) {
            input.prop('checked', true);
            $(this).addClass('masterstudy-course-player-answer_checked');
            $(this).find('.masterstudy-course-player-answer__checkbox').addClass('masterstudy-course-player-answer__checkbox_checked');
          } else {
            input.prop('checked', false);
            $(this).removeClass('masterstudy-course-player-answer_checked');
            $(this).find('.masterstudy-course-player-answer__checkbox').removeClass('masterstudy-course-player-answer__checkbox_checked');
          }
        }
      }
    });
    $(document).off('input.masterstudyCoursePlayerQuestionsKeyword', '.masterstudy-course-player-quiz-keywords__keyword-to-fill').on('input.masterstudyCoursePlayerQuestionsKeyword', '.masterstudy-course-player-quiz-keywords__keyword-to-fill', function () {
      var $input = $(this);
      if ($input.val().trim() === '') {
        $input.removeClass('masterstudy-course-player-quiz-keywords__keyword-to-fill_invalid');
        $input.removeClass('masterstudy-course-player-quiz-keywords__keyword-to-fill_shake');
      }
    });
    $('.masterstudy-course-player-quiz-keywords__keyword-to-fill', $scope).off('keyup.masterstudyCoursePlayerQuestions').on('keyup.masterstudyCoursePlayerQuestions', handleKeywordInput);
    $('.masterstudy-pagination__item-block, .masterstudy-tabs-pagination__item-block', $scope).off('click.masterstudyCoursePlayerVideoPause').on('click.masterstudyCoursePlayerVideoPause', pauseVideos);
    bindVideoPlayers($scope);
  }
  window.MasterstudyCoursePlayerQuestions = window.MasterstudyCoursePlayerQuestions || {};
  window.MasterstudyCoursePlayerQuestions.init = init;
  $(document).ready(function () {
    init($(document));
  });
})(jQuery);