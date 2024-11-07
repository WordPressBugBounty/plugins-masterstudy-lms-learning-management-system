"use strict";

(function ($) {
  $(document).ready(function () {
    //expired component
    if (expired_data.load_scripts) {
      var cookie_name = "stm_lms_expired_course_".concat(expired_data.id);
      var cookie = $.cookie(cookie_name);
      $('.masterstudy-single-course-expired-popup').removeAttr('style');
      setTimeout(function () {
        if (cookie !== 'closed') {
          $('body').addClass('masterstudy-expired-popup');
          $('.masterstudy-single-course-expired-popup').addClass('masterstudy-single-course-expired-popup_active');
        }
      }, 200);
      $('.masterstudy-single-course-expired-popup').find('.masterstudy-button').on('click', function () {
        $('body').removeClass('masterstudy-expired-popup');
        $('.masterstudy-single-course-expired-popup').removeClass('masterstudy-single-course-expired-popup_active');
        var date = new Date();
        $.cookie(cookie_name, 'closed', {
          path: '/',
          expires: date.getTime() + 24 * 60 * 60 * 1000
        });
      });
      $('.masterstudy-single-course-expired-popup').on('click', function (event) {
        if (event.target === this) {
          $(this).removeClass('masterstudy-single-course-expired-popup_active');
          $('body').removeClass('masterstudy-expired-popup');
          var date = new Date();
          $.cookie(cookie_name, 'closed', {
            path: '/',
            expires: date.getTime() + 24 * 60 * 60 * 1000
          });
        }
      });
    }

    //reviews component
    var offset = 0;
    var total = true;
    var reviewText = '';
    var userMark = '';
    var editor = false;
    var loadMoreButton = $("[data-id='masterstudy-single-course-reviews-more']");
    var reviewsList = $('.masterstudy-single-course-reviews__list-wrapper');
    var showAddReviewButton = $('.masterstudy-single-course-reviews__add-button');
    var closeReviewButton = $('.masterstudy-single-course-reviews__form-close');
    var addReviewForm = $('.masterstudy-single-course-reviews__form');
    var submitReviewButton = $("[data-id='masterstudy-single-course-reviews-submit']");
    var errorMessageBlock = $('.masterstudy-single-course-reviews__form-message');
    var reviewFormStars = $('.masterstudy-single-course-reviews__form-rating').find('.masterstudy-single-course-reviews__star');
    if (typeof reviews_data !== 'undefined') {
      getReviews();
      if (typeof tinyMCE !== 'undefined') {
        getEditor();
      }
      loadMoreButton.click(function (event) {
        event.preventDefault();
        getReviews();
      });
      showAddReviewButton.click(function (event) {
        event.preventDefault();
        addReviewForm.addClass('masterstudy-single-course-reviews__form_active');
      });
      closeReviewButton.click(function (event) {
        event.preventDefault();
        errorMessageBlock.removeClass('masterstudy-single-course-reviews__form-message_active');
        errorMessageBlock.html('');
        addReviewForm.removeClass('masterstudy-single-course-reviews__form_active');
        reviewFormStars.removeClass('masterstudy-single-course-reviews__star_clicked');
        if (editor) {
          editor.setContent('');
          $('.masterstudy-wp-editor__word-count').html('');
        }
      });
      submitReviewButton.click(function (event) {
        event.preventDefault();
        addReview();
      });
      reviewFormStars.click(function () {
        $(this).addClass('masterstudy-single-course-reviews__star_clicked');
        $(this).siblings().removeClass('masterstudy-single-course-reviews__star_clicked');
        $(this).prevAll().addBack().addClass('masterstudy-single-course-reviews__star_clicked');
        userMark = $('.masterstudy-single-course-reviews__star_clicked').length;
      });
      reviewFormStars.hover(function () {
        $(this).prevAll().addBack().addClass('masterstudy-single-course-reviews__star_filled');
      }, function () {
        $(this).parent().find('.masterstudy-single-course-reviews__star').removeClass('masterstudy-single-course-reviews__star_filled');
      });
    }
    function getReviews() {
      var getReviewsUrl = "".concat(stm_lms_ajaxurl, "?action=stm_lms_get_reviews&nonce=").concat(stm_lms_nonces['stm_lms_get_reviews'], "&offset=").concat(offset, "&post_id=").concat(reviews_data.course_id);
      var reviewHtml = '';
      loadMoreButton.addClass('masterstudy-button_loading');
      $.get(getReviewsUrl, function (response) {
        if (response.posts.length > 0) {
          response.posts.forEach(function (review) {
            reviewHtml += generateReviewHtml(review);
          });
          reviewsList.html(reviewsList.html() + reviewHtml);
          offset++;
          total = response.total;
        }
        loadMoreButton.removeClass('masterstudy-button_loading');
        total ? loadMoreButton.parent().hide() : loadMoreButton.parent().show();
      });
    }
    function addReview() {
      var addReviewsUrl = stm_lms_ajaxurl + '?action=stm_lms_add_review&nonce=' + stm_lms_nonces['stm_lms_add_review'];
      if (editor) {
        reviewText = editor.getContent();
      }
      submitReviewButton.addClass('masterstudy-button_loading');
      $.post(addReviewsUrl, {
        post_id: reviews_data.course_id,
        mark: userMark,
        review: reviewText
      }, function (response) {
        if (response.status === 'success') {
          errorMessageBlock.html(response.message);
          errorMessageBlock.addClass('masterstudy-single-course-reviews__form-message_success').addClass('masterstudy-single-course-reviews__form-message_active');
          setTimeout(function () {
            addReviewForm.removeClass('masterstudy-single-course-reviews__form_active');
            if (editor) {
              editor.setContent('');
              $('.masterstudy-wp-editor__word-count').html('');
            }
          }, 1500);
        } else {
          errorMessageBlock.html(response.message);
          errorMessageBlock.addClass('masterstudy-single-course-reviews__form-message_active');
        }
        submitReviewButton.removeClass('masterstudy-button_loading');
      });
    }
    function generateReviewHtml(review) {
      var starsHtml = '';
      for (var i = 1; i <= 5; i++) {
        if (i <= review.mark) {
          starsHtml += '<span class="masterstudy-single-course-reviews__star masterstudy-single-course-reviews__star_filled"></span>';
        } else {
          starsHtml += '<span class="masterstudy-single-course-reviews__star"></span>';
        }
      }
      return "\n                <div class=\"masterstudy-single-course-reviews__item\">\n                    <div class=\"masterstudy-single-course-reviews__item-header\">\n                        <div class=\"masterstudy-single-course-reviews__item-mark\">\n                            ".concat(starsHtml, "\n                        </div>\n                        ").concat(review.status === 'pending' ? "\n                            <div class=\"masterstudy-single-course-reviews__item-status\">\n                                ".concat(reviews_data.status, "\n                            </div>") : '', "\n                    </div>\n                    <div class=\"masterstudy-single-course-reviews__item-content\">\n                        ").concat(review.content, "\n                    </div>\n                    <div class=\"masterstudy-single-course-reviews__item-row\">\n                        <a class=\"masterstudy-single-course-reviews__item-user\"\n                            ").concat(reviews_data.student_public_profile ? "href=\"".concat(review.user_url, "\"") : '', "\n                        >\n                            <span class=\"masterstudy-single-course-reviews__item-author\">\n                                ").concat(reviews_data.author_label, "\n                            </span>\n                            <span class=\"masterstudy-single-course-reviews__item-author-name\">\n                                ").concat(review.user, "\n                            </span>\n                        </a>\n                        <div class=\"masterstudy-single-course-reviews__item-date\">\n                            ").concat(review.time, "\n                        </div>\n                    </div>\n                </div>");
    }
    function getEditor() {
      editor = tinyMCE.get(reviews_data.editor_id);
      if (editor) {
        if (editor.iframeElement === undefined) {
          setTimeout(function () {
            getEditor();
          }, 500);
        } else {
          editor.theme.resizeTo(null, 200);
          reviewText = editor.getContent();
        }
      }
    }

    //curriculum component
    $('.masterstudy-curriculum-list__toggler').click(function (event) {
      event.preventDefault();
      toggleContainer.call(this, true);
    });
    $('.masterstudy-curriculum-list__excerpt-toggler').click(function (event) {
      event.preventDefault();
      toggleContainer.call(this, false);
    });
    $('.masterstudy-curriculum-list__link_disabled').click(function (event) {
      event.preventDefault();
    });
    $('.masterstudy-hint').hover(function () {
      $(this).closest('.masterstudy-curriculum-list__materials').css('overflow', 'visible');
    }, function () {
      $(this).closest('.masterstudy-curriculum-list__materials').css('overflow', 'hidden');
    });

    //faq component
    $('.masterstudy-single-course-faq__item').click(function () {
      var content = $(this).find('.masterstudy-single-course-faq__answer'),
        isOpened = content.is(':visible'),
        openedClass = 'masterstudy-single-course-faq__container-wrapper_opened';
      if (isOpened) {
        content.animate({
          height: 0
        }, 100, function () {
          setTimeout(function () {
            content.css('display', 'none');
            content.css('height', '');
          }, 300);
        });
        $(this).find('.masterstudy-single-course-faq__container-wrapper').removeClass(openedClass);
      } else {
        content.css('display', 'block');
        var autoHeight = content.height('auto').height();
        content.height(0).animate({
          height: autoHeight
        }, 100, function () {
          setTimeout(function () {
            content.css('height', '');
          }, 300);
        });
        $(this).find('.masterstudy-single-course-faq__container-wrapper').addClass(openedClass);
      }
    });

    //materials component
    if (isSafari()) {
      $('.masterstudy-single-course-materials__link').remove();
    }
    $('.masterstudy-single-course-materials__link').click(handleDownloadClick);
    if (typeof MasterstudyAudioPlayer !== 'undefined') {
      MasterstudyAudioPlayer.init({
        selector: '.masterstudy-audio-player',
        showDeleteButton: false
      });
    }
    function handleDownloadClick() {
      $('.masterstudy-single-course-materials').find('.masterstudy-file-attachment__link').each(function () {
        var clickEvent = new MouseEvent('click', {
          bubbles: true,
          cancelable: true,
          view: window
        });
        this.dispatchEvent(clickEvent);
      });
    }

    //tabs component
    $('.masterstudy-single-course-tabs__item').click(function () {
      var targetId = $(this).data('id');
      $(this).siblings().removeClass('masterstudy-single-course-tabs__item_active');
      $(this).addClass('masterstudy-single-course-tabs__item_active');
      $(this).parent().next().find('.masterstudy-single-course-tabs__container').removeClass('masterstudy-single-course-tabs__container_active').filter(function () {
        return $(this).data('id') === targetId;
      }).addClass('masterstudy-single-course-tabs__container_active');
    });
    if ($('.masterstudy-single-course-tabs_style-sidebar').length && !window.matchMedia('(max-width: 1023.98px)').matches) {
      var allowScrollUpdate = true;
      $('.masterstudy-single-course-tabs_style-sidebar').find('.masterstudy-single-course-tabs__item').click(function () {
        $('html, body').stop();
        allowScrollUpdate = false;
        var targetId = $(this).data('id');
        $(this).siblings().removeClass('masterstudy-single-course-tabs__item_active');
        $(this).addClass('masterstudy-single-course-tabs__item_active');
        var activeContainer = $('body').find('.masterstudy-single-course-tabs__container').removeClass('masterstudy-single-course-tabs__container_active').filter(function () {
          return $(this).data('id') === targetId;
        }).addClass('masterstudy-single-course-tabs__container_active');
        $('html, body').animate({
          scrollTop: $(activeContainer).offset().top - 70
        }, 1000, function () {
          allowScrollUpdate = true;
        });
      });
      $(window).on('scroll', function () {
        if (!allowScrollUpdate) {
          return;
        }
        var scrollPosition = $(window).scrollTop();
        var isInViewport = false;
        $('.masterstudy-single-course-tabs__container').each(function () {
          var container = $(this);
          var containerTop = container.offset().top;
          var containerBottom = containerTop + container.height();
          if (scrollPosition >= containerTop - 70 && scrollPosition < containerBottom - 70) {
            var targetId = container.data('id');
            isInViewport = true;
            $('.masterstudy-single-course-tabs__item').each(function () {
              var tab = $(this);
              if (tab.data('id') === targetId) {
                tab.siblings().removeClass('masterstudy-single-course-tabs__item_active');
                tab.addClass('masterstudy-single-course-tabs__item_active');
              }
            });
          }
        });
        if (!isInViewport) {
          $('.masterstudy-single-course-tabs__item').removeClass('masterstudy-single-course-tabs__item_active');
        }
      });
    }
    if ($('.masterstudy-single-course-tabs_style-sidebar').length && window.matchMedia('(max-width: 1023.98px)').matches) {
      $('.masterstudy-single-course-tabs').removeClass('masterstudy-single-course-tabs_style-sidebar');
      $('.masterstudy-single-course-tabs__content').removeClass('masterstudy-single-course-tabs_style-sidebar');
      $('.masterstudy-single-course-tabs').children().first().addClass('masterstudy-single-course-tabs__item_active');
      $('.masterstudy-single-course-tabs__content').children().first().addClass('masterstudy-single-course-tabs__container_active');
    }

    //wishlist component
    $('body').on('click', '.masterstudy-single-course-wishlist', function () {
      var post_id = $(this).attr('data-id');
      if ($('body').hasClass('logged-in')) {
        $.ajax({
          url: stm_lms_ajaxurl,
          dataType: 'json',
          context: this,
          data: {
            action: 'stm_lms_wishlist',
            nonce: stm_lms_nonces['stm_lms_wishlist'],
            post_id: post_id
          },
          complete: function complete(response) {
            var data = response['responseJSON'];
            $(this).find('.masterstudy-single-course-wishlist__title').toggleClass('masterstudy-single-course-wishlist_added');
            if (!wishlist_data.without_title) {
              $(this).find('.masterstudy-single-course-wishlist__title').text(data.text);
            }
          }
        });
      }
    });
  });

  //curriculum component
  function toggleContainer(main) {
    var content = main ? $(this).parent().next() : $(this).parent().parent().next(),
      isOpened = content.is(':visible'),
      openedClass = main ? 'masterstudy-curriculum-list__wrapper_opened' : 'masterstudy-curriculum-list__container-wrapper_opened';
    if (isOpened) {
      content.animate({
        height: 0
      }, 100, function () {
        setTimeout(function () {
          content.css('display', 'none');
          content.css('height', '');
        }, 300);
      });
      $(this).parent().parent().removeClass(openedClass);
    } else {
      content.css('display', 'block');
      var autoHeight = content.height('auto').height();
      content.height(0).animate({
        height: autoHeight
      }, 100, function () {
        setTimeout(function () {
          content.css('height', '');
        }, 300);
      });
      $(this).parent().parent().addClass(openedClass);
    }
  }

  //materials component
  function isSafari() {
    return /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
  }

  //excerpt component
  $('.masterstudy-single-course-excerpt__more').click(function () {
    $(this).siblings('.masterstudy-single-course-excerpt__hidden').toggle();
    $(this).siblings('.masterstudy-single-course-excerpt__continue').toggle();
    $(this).text($(this).text().trim() === excerpt_data.more_title ? excerpt_data.less_title : excerpt_data.more_title);
  });

  //share button component
  setTimeout(function () {
    $('.masterstudy-single-course-share-button-modal').removeAttr('style');
  }, 1000);
  $('.masterstudy-single-course-share-button__title').click(function () {
    $(this).parent().next().addClass('masterstudy-single-course-share-button-modal_open');
    $('body').addClass('masterstudy-single-course-share-button-body-hidden');
  });
  $('.masterstudy-single-course-share-button-modal').click(function (event) {
    if (event.target === this) {
      $('.masterstudy-single-course-share-button-modal').removeClass('masterstudy-single-course-share-button-modal_open');
      $('body').removeClass('masterstudy-single-course-share-button-body-hidden');
    }
  });
  $('.masterstudy-single-course-share-button-modal__close').on('click', function () {
    $('.masterstudy-single-course-share-button-modal').removeClass('masterstudy-single-course-share-button-modal_open');
    $('body').removeClass('masterstudy-single-course-share-button-body-hidden');
  });
  $('.masterstudy-single-course-share-button-modal__link_copy').click(function (event) {
    event.preventDefault();
    var tempInput = document.createElement("input");
    var _this = $(this);
    tempInput.style.position = "absolute";
    tempInput.style.left = "-9999px";
    tempInput.value = share_data.course_url;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand("copy");
    document.body.removeChild(tempInput);
    var originalButtonText = _this.text();
    _this.text(share_data.copy_text);
    setTimeout(function () {
      _this.text(originalButtonText);
    }, 2000);
  });
})(jQuery);