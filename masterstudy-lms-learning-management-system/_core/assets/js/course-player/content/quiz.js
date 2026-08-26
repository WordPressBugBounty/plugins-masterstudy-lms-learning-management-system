"use strict";

function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && iter[Symbol.iterator] != null || iter["@@iterator"] != null) return Array.from(iter); }
function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }
function _createForOfIteratorHelper(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e) { throw _e; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e2) { didErr = true; err = _e2; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }
(function ($) {
  $(document).ready(function () {
    function getQuizRoot() {
      return $('.masterstudy-course-player-quiz').first();
    }
    function initializeRetakeMarkup($scope) {
      if (window.MasterstudyCoursePlayerQuestions && typeof window.MasterstudyCoursePlayerQuestions.init === 'function') {
        window.MasterstudyCoursePlayerQuestions.init($scope);
      }
    }
    function showQuizInterface() {
      $('.masterstudy-course-player-quiz__form').removeClass('masterstudy-course-player-quiz__form_hide');
      $('.masterstudy-course-player-navigation__submit-quiz').removeClass('masterstudy-course-player-navigation__submit-quiz_hide');
      $('.masterstudy-course-player-content__header').hide();
      $('.masterstudy-course-player-quiz__content').hide();
      $('.masterstudy-course-player-quiz__content-meta').hide();
      $('.masterstudy-course-player-quiz__start-quiz').hide();
      $('.masterstudy-course-player-header__navigation-quiz').addClass('masterstudy-course-player-header__navigation-quiz_show');
      $('.masterstudy-course-player-quiz__navigation-tabs').addClass('masterstudy-course-player-quiz__navigation-tabs_show');
      if ($('.masterstudy-course-player-question__content').find('.masterstudy-course-player-item-match').length > 0) {
        initializeItemMatch();
      }
      if ($('.masterstudy-course-player-question__content').find('.masterstudy-course-player-image-match').length > 0) {
        initializeImageMatch();
      }
      if ($('.masterstudy-course-player-question__content').find('.masterstudy-course-player-sortable').length > 0) {
        initializeSortable();
      }
      $('.masterstudy-course-player-fill-the-gap__questions').off('input.masterstudyCoursePlayerQuiz', 'input').on('input.masterstudyCoursePlayerQuiz', 'input', function () {
        var _$$val;
        var val = (_$$val = $(this).val()) !== null && _$$val !== void 0 ? _$$val : '';
        var minWidth = this.style.minWidth.replace(/\D+/, '');
        $(this).css('width', "".concat(Math.max(Number(minWidth), val.length * 8 + 16), "px"));
      });
      $('.masterstudy-course-player-content__wrapper').scrollTop(0);
    }
    function startCurrentQuiz() {
      showQuizInterface();
      startQuiz();
    }

    // h5p quiz integration
    if (typeof H5P !== 'undefined') {
      loadH5p();
    }
    function loadH5p() {
      H5P.externalDispatcher.on('xAPI', function (event) {
        if (typeof event.data.statement.result !== 'undefined') {
          var data = event.data.statement.result;
          data['action'] = 'stm_lms_add_h5p_result';
          data['sources'] = {
            post_id: quiz_data.course_id,
            item_id: quiz_data.quiz_id
          };
          $.ajax({
            type: 'POST',
            url: quiz_data.ajax_url + '?nonce=' + quiz_data.h5p_nonce,
            dataType: 'json',
            context: this,
            data: data,
            beforeSend: function beforeSend() {
              if (data.success === true) $('#stm-lms-lessons').addClass('loading');
            },
            complete: function complete(data) {
              data = data['responseJSON'];
              if (typeof data.completed !== 'undefined' && data.completed) {
                location.reload();
              } else {
                $('#stm-lms-lessons').removeClass('loading');
              }
            }
          });
        }
      });
    }
    function getFormData() {
      var data = {};
      var question_ids = [];
      $('.masterstudy-course-player-quiz__form').serializeArray().forEach(function (item) {
        /*if is array*/
        if (item.name.includes('[]')) {
          var key = item.name.replace('[]', '');
          if (typeof data[key] === 'undefined') {
            data[key] = [item.value];
          } else {
            data[key].push(item.value);
          }
        } else {
          if (item.name === 'question_ids') {
            question_ids = item.value.split(',');
          }
          data[item.name] = item.value;
        }
        if (question_ids.length > 0) {
          $.each(question_ids, function (index, key) {
            var bankQuestionsItems = $("[name*=\"questions_sequency[".concat(key, "]\"]"));
            if (bankQuestionsItems.length > 0) {
              $.each(bankQuestionsItems, function (i, item) {
                if (typeof data[$(item).val()] === 'undefined') {
                  data[$(item).val()] = [''];
                }
              });
              delete data[key];
            } else {
              if (typeof data[key] === 'undefined') {
                data[key] = [''];
              }
            }
          });
        }
      });
      return {
        data: data,
        question_ids: question_ids
      };
    }

    // start quiz
    $(document).on('click', "[data-id='start-quiz']", function (e) {
      e.preventDefault();
      startCurrentQuiz();
    });
    function handleEvent(el, cb) {
      function onChange() {
        if (!$('.masterstudy-course-player-quiz').hasClass('masterstudy-course-player-quiz_show-answers')) {
          cb();
        }
      }
      $(el).on('click', '.masterstudy-course-player-answer', onChange);
      $(el).on('keyup', '.masterstudy-course-player-quiz-keywords__keyword-to-fill', cb);
      $(el).on('change', '.masterstudy-course-player-item-match__input', onChange);
      $(el).on('change', '.masterstudy-course-player-image-match__input', onChange);
      $(el).on('change', '.masterstudy-course-player-fill-the-gap__questions input', onChange);
    }

    // quiz alert
    $("[data-id='submit-quiz']").click(function (e) {
      var _data$required_answer, _data$required_answer2;
      e.preventDefault();
      var _getFormData = getFormData(),
        data = _getFormData.data;
      var requiredAnswers = (_data$required_answer = (_data$required_answer2 = data['required_answer_ids']) === null || _data$required_answer2 === void 0 ? void 0 : _data$required_answer2.split(',')) !== null && _data$required_answer !== void 0 ? _data$required_answer : [];
      var notAnsweredQuestions = [];
      var _iterator = _createForOfIteratorHelper(requiredAnswers),
        _step;
      try {
        for (_iterator.s(); !(_step = _iterator.n()).done;) {
          var _data$reqAnswer$;
          var reqAnswer = _step.value;
          var fillTheGapVariant = reqAnswer + '[0]';
          if (data[fillTheGapVariant] !== undefined) {
            if (data[fillTheGapVariant] === '') {
              notAnsweredQuestions.push(reqAnswer);
            }
          } else if (data[reqAnswer] !== undefined && !((_data$reqAnswer$ = data[reqAnswer][0]) !== null && _data$reqAnswer$ !== void 0 && _data$reqAnswer$.length)) {
            notAnsweredQuestions.push(reqAnswer);
          }
        }
      } catch (err) {
        _iterator.e(err);
      } finally {
        _iterator.f();
      }
      var listOfElements = Array.from($(".masterstudy-course-player-question"));
      var isScrolled = false;
      var _loop = function _loop() {
        var el = _listOfElements[_i];
        if (notAnsweredQuestions.includes(el.dataset.questionId)) {
          var onChange = function onChange() {
            el.classList.remove('masterstudy-course-player-question_required');
          };
          if (!isScrolled) {
            el.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
            isScrolled = true;
          }
          el.classList.add('masterstudy-course-player-question_required');
          handleEvent(el, onChange);
        }
      };
      for (var _i = 0, _listOfElements = listOfElements; _i < _listOfElements.length; _i++) {
        _loop();
      }
      if (isScrolled) return;
      $("[data-id='quiz_alert']").addClass('masterstudy-alert_open');
    });

    // submit quiz
    $("[data-id='quiz_alert']").find("[data-id='submit']").click(function (e) {
      e.preventDefault();
      submitQuiz();
    });
    function submitQuiz() {
      var _getFormData2 = getFormData(),
        data = _getFormData2.data;
      $.ajax({
        type: 'POST',
        url: quiz_data.ajax_url + '?nonce=' + quiz_data.submit_nonce,
        dataType: 'json',
        context: this,
        data: data,
        beforeSend: function beforeSend() {
          $("[data-id='quiz_alert']").removeClass('masterstudy-alert_open');
          $("[data-id='submit-quiz']").addClass('masterstudy-button_loading');
        },
        success: function success(data) {
          if (quiz_data.is_single_quiz) {
            var date = new Date();
            date.setTime(date.getTime() + 60 * 60 * 1000);
            document.cookie = "quiz_user_answer_id=".concat(data.user_answer_id, ";expires=").concat(date.toUTCString(), ";path=/");
            var currentUrl = currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('show_answers', data.user_answer_id);
            currentUrl.searchParams.set('progress', data.progress);
            window.location.href = currentUrl;
          } else {
            location.reload();
          }
        }
      });
    }

    //cancel submit
    $("[data-id='quiz_alert']").find("[data-id='cancel']").click(function (e) {
      e.preventDefault();
      $("[data-id='quiz_alert']").removeClass('masterstudy-alert_open');
    });
    $("[data-id='quiz_alert']").find('.masterstudy-alert__header-close').click(function (e) {
      e.preventDefault();
      $("[data-id='quiz_alert']").removeClass('masterstudy-alert_open');
    });
    function shuffleArray(array) {
      for (var i = array.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var _ref = [array[j], array[i]];
        array[i] = _ref[0];
        array[j] = _ref[1];
      }
      return array;
    }
    function randomizeAnswersOnRetake() {
      var $scope = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : getQuizRoot();
      $('input[name^="order_"]', $scope).each(function (_, el) {
        var val = JSON.parse($(el).attr('value'));
        var id = $(el).attr('name').split('_')[1];
        var shuffledArray = shuffleArray(val);
        var question = $("[data-question-id=\"".concat(id, "\"]"), $scope);
        var container = question.find('.masterstudy-course-player-question__content');
        var questionAnswers = container.find('.masterstudy-course-player-answer');
        var isItemMatch = !!container.find('.masterstudy-course-player-item-match').length;
        var isImageMatch = !!container.find('.masterstudy-course-player-image-match').length;
        var isSortable = !!container.find('.masterstudy-course-player-sortable').length;
        if (isSortable) return;
        if (isItemMatch) {
          var elMap = {};
          var answerContainer = container.find('.masterstudy-course-player-item-match__answer');
          var itemMatchContainer = container.find('.masterstudy-course-player-item-match');
          var questionQuestions = container.find('.masterstudy-course-player-item-match__question');
          questionQuestions.each(function (idx, questionEl) {
            var questionVal = $(questionEl).find('.masterstudy-course-player-item-match__question-content');
            if (questionVal) {
              questionVal = questionVal.text().trim();
              var answer = questionAnswers[idx];
              elMap[questionVal] = {
                question: $(this),
                answer: answer
              };
            }
          });
          questionAnswers.detach();
          questionQuestions.detach();
          _toConsumableArray(shuffledArray).reverse().forEach(function (val) {
            if (elMap[val]) {
              itemMatchContainer.prepend(elMap[val].question);
            }
          });
          shuffledArray.forEach(function (val) {
            if (elMap[val]) {
              answerContainer.append(elMap[val].answer);
            }
          });
        } else if (isImageMatch) {
          var _elMap = {};
          var _answerContainer = container.find('.masterstudy-course-player-image-match__answer');
          var imageMatchContainer = container.find('.masterstudy-course-player-image-match');
          var _questionQuestions = container.find('.masterstudy-course-player-image-match__question');
          _questionQuestions.each(function (idx, questionEl) {
            var questionVal = $(questionEl).find('.masterstudy-course-player-image-match__question-content img');
            if (questionVal) {
              questionVal = $(questionVal).attr('data-image-id');
              var answer = questionAnswers[idx];
              _elMap[questionVal] = {
                question: $(this),
                answer: answer
              };
            }
          });
          questionAnswers.detach();
          _questionQuestions.detach();
          _toConsumableArray(shuffledArray).reverse().forEach(function (val) {
            if (_elMap[val]) {
              imageMatchContainer.prepend(_elMap[val].question);
            }
          });
          shuffledArray.forEach(function (val) {
            if (_elMap[val]) {
              _answerContainer.append(_elMap[val].answer);
            }
          });
        } else {
          var answersMap = Object.create(null);
          questionAnswers.each(function (_, answerEl) {
            var answerVal = $(answerEl).find('.masterstudy-course-player-answer__text');
            var inputVal = $(answerEl).find('input').val();
            if (answerVal.length) {
              var mathJax = answerVal.find('script[type^="math/tex"]');
              if (mathJax.length) {
                answerVal = "$$".concat(mathJax.text().trim(), "$$");
              } else {
                answerVal = answerVal.text().trim();
              }
            } else {
              answerVal = '';
            }
            if (!answerVal && inputVal) {
              answerVal = inputVal;
            }
            if (answerVal) {
              if (!answersMap[answerVal]) {
                answersMap[answerVal] = [];
              }
              answersMap[answerVal].push($(answerEl));
            }
          });
          Object.keys(answersMap).forEach(function (key) {
            return shuffleArray(answersMap[key]);
          });
          questionAnswers.detach();
          shuffledArray.forEach(function (val) {
            var _answersMap$val;
            if ((_answersMap$val = answersMap[val]) !== null && _answersMap$val !== void 0 && _answersMap$val.length) {
              container.append(answersMap[val].shift());
            }
          });
          Object.keys(answersMap).forEach(function (key) {
            answersMap[key].forEach(function (answer) {
              return container.append(answer);
            });
          });
        }
        $(el).val(JSON.stringify(shuffledArray)).attr('value', JSON.stringify(shuffledArray));
      });
    }

    // retake quiz
    $(document).on('click', '.masterstudy-course-player-quiz__result-retake .masterstudy-button', function (e) {
      e.preventDefault();
      var $quizRoot = getQuizRoot();
      var templateId = "#masterstudy-course-player-quiz-retake-template-".concat(quiz_data.quiz_id);
      var $template = $(templateId, $quizRoot);
      if (!$template.length || !$template[0].content) {
        return;
      }
      var $templateClone = $template.clone();
      var freshQuizHtml = $template.html().trim();
      var $freshQuiz = $(freshQuizHtml).filter('.masterstudy-course-player-quiz').first();
      if (!$freshQuiz.length) {
        return;
      }
      $freshQuiz.append($templateClone);
      $quizRoot.replaceWith($freshQuiz);
      initializeRetakeMarkup($freshQuiz);
      if (quiz_data.random_answers === '1') {
        randomizeAnswersOnRetake($freshQuiz);
      }
      $('.masterstudy-tabs-attempts-history').hide();
      startCurrentQuiz();
    });
    function startQuiz() {
      if (!quiz_data.duration > 0) {
        return;
      }
      $.ajax({
        url: quiz_data.ajax_url,
        dataType: 'json',
        context: this,
        data: {
          'quiz_id': quiz_data.quiz_id,
          'action': 'stm_lms_start_quiz',
          'nonce': quiz_data.start_nonce,
          'source': quiz_data.course_id
        },
        success: function success(data) {
          if ($('.masterstudy-course-player-quiz-timer').length > 0) {
            countTo(parseInt(data) * 1000);
            $('.masterstudy-course-player-quiz-timer').addClass('masterstudy-course-player-quiz-timer_started');
          }
        }
      });
    }

    // scroll to question
    $('.masterstudy-course-player-quiz__navigation-tabs .masterstudy-tabs-pagination__item-block').click(function () {
      var questionId = $(this).data('id');
      document.querySelector(".masterstudy-course-player-quiz__form [data-number-question=\"".concat(questionId, "\"]")).scrollIntoView({
        behavior: 'smooth'
      });
    });

    // quiz timer countdown
    var countInterval,
      timeOut = false;
    function countTo(countDownDate) {
      clearInterval(countInterval);
      countInterval = setInterval(function () {
        var now = new Date().getTime();
        var distance = countDownDate - now;
        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
        var hours = Math.floor(distance % (1000 * 60 * 60 * 24) / (1000 * 60 * 60));
        var minutes = Math.floor(distance % (1000 * 60 * 60) / (1000 * 60));
        var seconds = Math.floor(distance % (1000 * 60) / 1000);
        if (hours < 10) hours = '0' + hours;
        if (minutes < 10) minutes = '0' + minutes;
        if (seconds < 10) seconds = '0' + seconds;
        if (hours === '00' && minutes < 60) {
          $('.masterstudy-course-player-quiz-timer__minutes').text(minutes);
          $('.masterstudy-course-player-quiz-timer__seconds').text(seconds);
          $('.masterstudy-course-player-quiz-timer__separator[data-id="minutes"]').addClass('masterstudy-course-player-quiz-timer__separator_show');
        } else if (days < 1) {
          $('.masterstudy-course-player-quiz-timer__hours').text(hours);
          $('.masterstudy-course-player-quiz-timer__minutes').text(minutes);
          $('.masterstudy-course-player-quiz-timer__seconds').text(seconds);
          $('.masterstudy-course-player-quiz-timer__separator').addClass('masterstudy-course-player-quiz-timer__separator_show');
        } else {
          var daysText = $('.masterstudy-course-player-quiz-timer').attr('data-text-days');
          $('.masterstudy-course-player-quiz-timer__days').text(days + ' ' + daysText);
        }
        if (!timeOut && distance < 1001) {
          clearInterval(countInterval);
          timeOut = true;
          quiz_data.prevent_submit = 1;
          submitQuiz();
        }
      }, 1000);
    }
    function initializeItemMatch() {
      $('.masterstudy-course-player-item-match:not(.masterstudy-course-player-item-match_not-drag)').each(function (index) {
        var questionClass = 'item_drag_' + index;
        $(this).find('.masterstudy-course-player-item-match__answer, .masterstudy-course-player-item-match__question-answer').sortable({
          connectWith: '.' + questionClass + '.masterstudy-course-player-item-match__question-answer',
          appendTo: '.' + questionClass + '.masterstudy-course-player-item-match__answer',
          helper: 'clone',
          start: function start(event, ui) {
            $(ui.helper).css('cursor', 'grabbing');
          },
          stop: function stop(event, ui) {
            $(ui.helper).css('cursor', 'grab');
          },
          over: function over(event, ui) {
            var parent = $(this);
            if (parent.hasClass('masterstudy-course-player-item-match__question-answer')) {
              parent.addClass('masterstudy-course-player-item-match__question-answer_highlight');
            }
          },
          out: function out(event, ui) {
            var parent = $(this);
            if (parent.hasClass('masterstudy-course-player-item-match__question-answer')) {
              parent.removeClass('masterstudy-course-player-item-match__question-answer_highlight');
            }
          },
          remove: function remove(event, ui) {
            var parent = $(this);
            if (parent.hasClass('masterstudy-course-player-item-match__question-answer')) {
              parent.closest('.masterstudy-course-player-item-match__question-answer-wrapper').find('.masterstudy-course-player-item-match__question-answer-text').show();
              parent.closest('.masterstudy-course-player-item-match__question').removeClass('masterstudy-course-player-item-match__question_full');
            }
          },
          receive: function receive(event, ui) {
            var parent = $(this);
            var donor = $(ui.sender);
            if (parent.hasClass('masterstudy-course-player-item-match__question-answer')) {
              parent.closest('.masterstudy-course-player-item-match__question-answer-wrapper').find('.masterstudy-course-player-item-match__question-answer-text').hide();
              parent.closest('.masterstudy-course-player-item-match__question').addClass('masterstudy-course-player-item-match__question_full');
            }
            if (parent.children().length > 1) {
              /*Swap items*/
              $(ui.sender).sortable('cancel');
              if (parent.hasClass('masterstudy-course-player-item-match__question-answer') && donor.hasClass('masterstudy-course-player-item-match__question-answer')) {
                var parent_content = parent.find('.masterstudy-course-player-item-match__answer-item-content');
                var donor_content = donor.find('.masterstudy-course-player-item-match__answer-item-content');
                var parent_text = parent_content.text();
                var donor_text = donor_content.text();
                parent_content.text(donor_text);
                donor_content.text(parent_text);
                parent.closest('.masterstudy-course-player-item-match__question-answer-wrapper').find('.masterstudy-course-player-item-match__question-answer-text').hide();
                donor.closest('.masterstudy-course-player-item-match__question-answer-wrapper').find('.masterstudy-course-player-item-match__question-answer-text').hide();
                parent.closest('.masterstudy-course-player-item-match__question').addClass('masterstudy-course-player-item-match__question_full');
                donor.closest('.masterstudy-course-player-item-match__question').addClass('masterstudy-course-player-item-match__question_full');
              }
            }
            var items = [];
            var answers = parent.closest('.masterstudy-course-player-item-match').find('.masterstudy-course-player-item-match__question');
            if (answers.length > 0) {
              answers.each(function () {
                var input_parent = $(this).closest('.masterstudy-course-player-item-match').find('.masterstudy-course-player-item-match__input');
                var slot = $(this).find('.masterstudy-course-player-item-match__answer-item-content');
                var mathScript = slot.find('script[type="math/tex"]');
                var item = '';
                if (mathScript.length) {
                  item = mathScript.text().trim();
                } else {
                  item = slot.length ? slot.text().trim() : '';
                }
                items.push(item);
                var item_match_val = '[stm_lms_item_match]' + items.join('[stm_lms_sep]');
                input_parent.val(item_match_val).trigger('change');
              });
            }
          }
        }).addClass(questionClass);
      });
    }
    function initializeSortable() {
      $('.masterstudy-course-player-sortable:not(.masterstudy-course-player-sortable_not-drag)').each(function (index) {
        var questionClass = 'item_drag_' + index;
        var $list = $(this).find('.masterstudy-course-player-sortable__answer');
        shuffleChildren($list);
        updateOrderingInput($list);
        $list.addClass(questionClass).sortable({
          axis: 'y',
          containment: 'parent',
          helper: 'clone',
          tolerance: 'pointer',
          start: function start(event, ui) {
            $(ui.helper).css('cursor', 'grabbing');
          },
          stop: function stop(event, ui) {
            $(ui.helper).css('cursor', 'grab');
            updateOrderingInput($list);
          },
          update: function update(event, ui) {
            updateOrderingInput($list);
          }
        });
      });
      function shuffleChildren($container) {
        var $children = $container.children().get();
        $children.sort(function () {
          return Math.random() - 0.5;
        });
        $.each($children, function (_, child) {
          $container.append(child);
        });
      }
      function updateOrderingInput($list) {
        var items = [];
        var $questionBlock = $list.closest('.masterstudy-course-player-sortable');
        var $input = $questionBlock.find('.masterstudy-course-player-sortable__input');
        $list.find('.masterstudy-course-player-sortable__answer-item-content').each(function () {
          var $slot = $(this);
          var mathScript = $slot.find('script[type^="math/tex"]');
          var item;
          if (mathScript.length) {
            item = "$$".concat(mathScript.text().trim(), "$$");
          } else {
            item = $slot.text().trim();
          }
          items.push(item);
        });
        var sortable_val = '[stm_lms_sortable]' + items.join('[stm_lms_sep]');
        $input.val(sortable_val).trigger('change');
      }
    }
    function initializeImageMatch() {
      $('.masterstudy-course-player-image-match:not(.masterstudy-course-player-image-match_not-drag)').each(function (index) {
        var questionClass = 'image_drag_' + index;
        $(this).find('.masterstudy-course-player-image-match__answer, .masterstudy-course-player-image-match__question-answer').sortable({
          connectWith: '.' + questionClass + '.masterstudy-course-player-image-match__question-answer',
          appendTo: '.' + questionClass + '.masterstudy-course-player-image-match__answer',
          helper: 'clone',
          start: function start(event, ui) {
            var isQuestionAnswer = $(ui.item).parent().hasClass('masterstudy-course-player-image-match__question-answer');
            var isGridStyle = $(ui.helper).closest('.masterstudy-course-player-image-match').hasClass('masterstudy-course-player-image-match_style-grid');
            var isSmallScreen = window.matchMedia('(max-width: 576px)').matches;
            var height;
            $(ui.helper).css('cursor', 'grabbing');
            if (isQuestionAnswer) {
              if (isGridStyle) {
                height = isSmallScreen ? '105px' : '177px';
              } else {
                height = isSmallScreen ? '105px' : '280px';
              }
              $(ui.helper).find('img').css('height', height);
            }
          },
          stop: function stop(event, ui) {
            $(ui.helper).css('cursor', 'grab');
            $(ui.helper).css('width', '100%');
          },
          over: function over(event, ui) {
            var parent = $(this);
            if (parent.hasClass('masterstudy-course-player-image-match__question-answer')) {
              parent.addClass('masterstudy-course-player-image-match__question-answer_highlight');
            }
          },
          out: function out(event, ui) {
            var parent = $(this);
            if (parent.hasClass('masterstudy-course-player-image-match__question-answer')) {
              parent.removeClass('masterstudy-course-player-image-match__question-answer_highlight');
            }
          },
          remove: function remove(event, ui) {
            var parent = $(this);
            if (parent.hasClass('masterstudy-course-player-image-match__question-answer')) {
              parent.closest('.masterstudy-course-player-image-match__question-answer-wrapper').find('.masterstudy-course-player-image-match__question-answer-drag-text').show();
              parent.closest('.masterstudy-course-player-image-match__question').removeClass('masterstudy-course-player-image-match__question_full');
            }
          },
          receive: function receive(event, ui) {
            var parent = $(this);
            var donor = $(ui.sender);
            if (parent.hasClass('masterstudy-course-player-image-match__question-answer')) {
              parent.closest('.masterstudy-course-player-image-match__question-answer-wrapper').find('.masterstudy-course-player-image-match__question-answer-drag-text').hide();
              parent.closest('.masterstudy-course-player-image-match__question').addClass('masterstudy-course-player-image-match__question_full');
            }
            if (parent.children().length > 1) {
              /*Swap items*/
              $(ui.sender).sortable('cancel');
              if (parent.hasClass('masterstudy-course-player-image-match__question-answer') && donor.hasClass('masterstudy-course-player-image-match__question-answer')) {
                var parent_img_container = parent.find('.masterstudy-course-player-image-match__answer-item-content img');
                var donor_img_container = donor.find('.masterstudy-course-player-image-match__answer-item-content img');
                var donor_text_container = donor.find('.masterstudy-course-player-image-match__answer-item-text');
                var parent_text_container = parent.find('.masterstudy-course-player-image-match__answer-item-text');
                var parent_img = parent_img_container.attr('src');
                var donor_img = donor_img_container.attr('src');
                var donor_text = donor_text_container.text();
                var parent_text = parent_text_container.text();
                var empty_text_donor = donor.find('.masterstudy-course-player-image-match__answer-item-container_hide').length > 0;
                var empty_text_parent = parent.find('.masterstudy-course-player-image-match__answer-item-container_hide').length > 0;
                parent_img_container.attr('src', donor_img);
                donor_img_container.attr('src', parent_img);
                donor_text_container.text(parent_text);
                parent_text_container.text(donor_text);
                if (empty_text_donor && !empty_text_parent) {
                  donor.find('.masterstudy-course-player-image-match__answer-item-container').removeClass('masterstudy-course-player-image-match__answer-item-container_hide');
                  parent.find('.masterstudy-course-player-image-match__answer-item-container').addClass('masterstudy-course-player-image-match__answer-item-container_hide');
                } else if (empty_text_parent && !empty_text_donor) {
                  donor.find('.masterstudy-course-player-image-match__answer-item-container').addClass('masterstudy-course-player-image-match__answer-item-container_hide');
                  parent.find('.masterstudy-course-player-image-match__answer-item-container').removeClass('masterstudy-course-player-image-match__answer-item-container_hide');
                }
                parent.closest('.masterstudy-course-player-image-match__question-answer-wrapper').find('.masterstudy-course-player-image-match__question-answer-drag-text').hide();
                donor.closest('.masterstudy-course-player-image-match__question-answer-wrapper').find('.masterstudy-course-player-image-match__question-answer-drag-text').hide();
                parent.closest('.masterstudy-course-player-image-match__question').addClass('masterstudy-course-player-image-match__question_full');
                donor.closest('.masterstudy-course-player-image-match__question').addClass('masterstudy-course-player-image-match__question_full');
              }
            }
            var items = [];
            var answers = parent.closest('.masterstudy-course-player-image-match').find('.masterstudy-course-player-image-match__question');
            if (answers.length > 0) {
              answers.each(function () {
                var input_parent = $(this).closest('.masterstudy-course-player-image-match').find('.masterstudy-course-player-image-match__input');
                var slot = $(this).find('.masterstudy-course-player-image-match__answer-item-content');
                var item = slot.length ? slot.find('.masterstudy-course-player-image-match__answer-item-text').text().trim() : '';
                if ($(this).find('.masterstudy-course-player-image-match__answer-item-content img').length > 0) {
                  item += '|' + $(this).find('.masterstudy-course-player-image-match__answer-item-content img').attr('src');
                }
                items.push(item);
                var image_match_val = '[stm_lms_image_match]' + items.join('[stm_lms_sep]');
                input_parent.val(image_match_val).trigger('change');
              });
            }
          }
        }).addClass(questionClass);
      });
    }
  });
})(jQuery);