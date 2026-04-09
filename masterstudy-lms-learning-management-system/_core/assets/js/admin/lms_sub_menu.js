"use strict";

(function ($) {
  $(document).ready(function () {
    var classes = ['post-type-stm-courses', 'post-type-stm-lessons', 'post-type-stm-quizzes', 'post-type-stm-questions', 'post-type-stm-assignments', 'post-type-stm-google-meets', 'post-type-stm-user-assignment', 'post-type-stm-reviews', 'post-type-stm-orders', 'post-type-stm-ent-groups', 'post-type-stm-payout', 'taxonomy-stm_lms_course_taxonomy', 'taxonomy-stm_lms_question_taxonomy', 'stm-lms_page_stm-lms-online-testing', 'admin_page_stm_lms_scorm_settings', 'toplevel_page_stm-lms-dashboard'];
    var setupSettingsMenu = function setupSettingsMenu() {
      var $settingsParent = $('.stm-lms-settings-menu-title').closest('li');
      $settingsParent.addClass('stm-lms-settings-menu');
      $settingsParent.nextAll('li').addClass('stm-lms-pro-addons-menu');
    };
    var setupUsersMenu = function setupUsersMenu() {
      var $instructorsParent = $('.stm-lms-instructors-menu-title').closest('li');
      var $studentsParent = $('.stm-lms-students-menu-title').closest('li');
      $instructorsParent.addClass('stm-lms-instructors-menu');
      $studentsParent.addClass('stm-lms-students-menu');
    };
    var setupTemplatesMenu = function setupTemplatesMenu() {
      var $templates = $('.stm-lms-templates-menu-title');
      var $settings_parent = $('.stm-lms-settings-menu-title').closest('li');
      $settings_parent.next('li').addClass('stm-lms-help-support');
      if ($templates.length === 0) {
        $settings_parent.addClass('stm-lms-settings-menu');
      }
      var $templatesParent = $templates.closest('li');
      if (!$templatesParent.length) return;
      var li_addon_last = $('li.stm-lms-pro-addons-menu:last');
      $templatesParent.addClass('stm-lms-templates-menu');
      $templatesParent.next('li').addClass('stm-lms-addons-page-menu');
      $templatesParent.nextAll('li').addClass('stm-lms-pro-addons-menu');
      if (li_addon_last.find('span.stm-lms-unlock-pro-btn').length) {
        li_addon_last.addClass('upgrade');
      }
    };
    var updateDemoLink = function updateDemoLink() {
      var link = document.querySelector('a[href="admin.php?page=masterstudy-starter-demo-import"]');
      if (link) {
        link.target = "_blank";
        link.href = "https://stylemixthemes.com/wordpress-lms-plugin/starter-templates/";
      }
    };
    var setupCourseCategorySubmenu = function setupCourseCategorySubmenu() {
      var $submenu = $('#toplevel_page_stm-lms-settings .wp-submenu');
      var $coursesLink = $submenu.find('li a[href*="page=stm-lms-courses-link"], li a[href*="post_type=stm-courses"]').first();
      var $courseCategoryLink = $submenu.find('li a[href*="taxonomy=stm_lms_course_taxonomy"]').first();
      var $questionLink = $submenu.find('li a[href*="post_type=stm-questions"]').first();
      var $questionCategoryLink = $submenu.find('li a[href*="taxonomy=stm_lms_question_taxonomy"]').first();
      var $studentsLink = $submenu.find('li a[href*="page=manage_students"]').first();
      var $pointStatisticsLink = $submenu.find('li a[href*="page=point_system_statistics"]').first();
      var $payoutsLink = $submenu.find('li a[href*="post_type=stm-payout"]').first();
      var $statisticsLink = $submenu.find('li a[href*="page=stm_lms_statistics"]').first();
      if (!$submenu.length) {
        return;
      }
      var $courseCategoryItem = $courseCategoryLink.closest('li').detach();
      var $questionCategoryItem = $questionCategoryLink.closest('li').detach();
      var $pointStatisticsItem = $pointStatisticsLink.closest('li').detach();
      var $statisticsItem = $statisticsLink.closest('li').detach();
      var attachUnderParent = function attachUnderParent($parentLink, $childItem) {
        if (!$parentLink.length || !$childItem.length) {
          return;
        }
        var $parentItem = $parentLink.closest('li');
        $parentItem.after($childItem);
      };
      var isCourseContext = $('body').hasClass('post-type-stm-courses') || $('body').hasClass('taxonomy-stm_lms_course_taxonomy');
      var isQuestionContext = $('body').hasClass('post-type-stm-questions') || $('body').hasClass('taxonomy-stm_lms_question_taxonomy');
      var isPointStatisticsContext = window.location.search.indexOf('page=manage_students') !== -1 || window.location.search.indexOf('page=point_system_statistics') !== -1;
      var isStatisticsContext = $('body').hasClass('post-type-stm-payout') || window.location.search.indexOf('page=stm_lms_statistics') !== -1;
      if (isCourseContext) {
        attachUnderParent($coursesLink, $courseCategoryItem);
      }
      if (isQuestionContext) {
        attachUnderParent($questionLink, $questionCategoryItem);
      }
      if (isPointStatisticsContext) {
        attachUnderParent($studentsLink, $pointStatisticsItem);
      }
      if (isStatisticsContext) {
        attachUnderParent($payoutsLink, $statisticsItem);
      }
      $coursesLink.on('click', function () {
        attachUnderParent($coursesLink, $courseCategoryItem);
      });
      $questionLink.on('click', function () {
        attachUnderParent($questionLink, $questionCategoryItem);
      });
      $studentsLink.on('click', function () {
        attachUnderParent($studentsLink, $pointStatisticsItem);
      });
      $payoutsLink.on('click', function () {
        attachUnderParent($payoutsLink, $statisticsItem);
      });
    };
    var setupClassroomsAndCertificatesOrder = function setupClassroomsAndCertificatesOrder() {
      var $submenu = $('#toplevel_page_stm-lms-settings .wp-submenu');
      if (!$submenu.length) {
        return;
      }
      var normalizeText = function normalizeText(text) {
        return text.toLowerCase().replace(/\s+/g, ' ').trim();
      };
      var findMenuItemByKeyword = function findMenuItemByKeyword(keywords) {
        return $submenu.find('li').filter(function () {
          var itemText = normalizeText($(this).text());
          return keywords.some(function (keyword) {
            return itemText.includes(keyword);
          });
        }).first();
      };
      var $googleMeetItem = findMenuItemByKeyword(['google meet']);
      var $analyticsItem = findMenuItemByKeyword(['analytics']);
      var $classroomsItem = findMenuItemByKeyword(['classrooms', 'classroom']);
      var $certificatesItem = findMenuItemByKeyword(['certificates', 'certificate builder', 'certificate']);
      var $anchorItem = $googleMeetItem.length ? $googleMeetItem : $analyticsItem;
      if (!$anchorItem.length) {
        return;
      }
      var $itemsToMove = $();
      if ($classroomsItem.length) {
        $itemsToMove = $itemsToMove.add($classroomsItem);
      }
      if ($certificatesItem.length) {
        $itemsToMove = $itemsToMove.add($certificatesItem);
      }
      if (!$itemsToMove.length) {
        return;
      }
      $itemsToMove.detach();
      $anchorItem.after($itemsToMove);
    };
    var setupTopLevelLmsMenusOrder = function setupTopLevelLmsMenusOrder() {
      var $adminMenu = $('#adminmenu');
      if (!$adminMenu.length) {
        return;
      }
      var normalizeText = function normalizeText(text) {
        return text.toLowerCase().replace(/\s+/g, ' ').trim();
      };
      var findTopLevelByIds = function findTopLevelByIds(ids) {
        return $adminMenu.children('li').filter(function () {
          var itemId = (this.id || '').toLowerCase();
          return ids.some(function (id) {
            return itemId === id.toLowerCase();
          });
        }).first();
      };
      var findTopLevelItem = function findTopLevelItem(hrefKeywords, textKeywords) {
        return $adminMenu.children('li').filter(function () {
          var $link = $(this).children('a');
          var href = ($link.attr('href') || '').toLowerCase();
          var text = normalizeText($link.text());
          var hrefMatched = hrefKeywords.some(function (keyword) {
            return href.includes(keyword);
          });
          var textMatched = textKeywords.some(function (keyword) {
            return text.includes(keyword);
          });
          return hrefMatched || textMatched;
        }).first();
      };
      var $postsItem = $adminMenu.children('#menu-posts').first().length ? $adminMenu.children('#menu-posts').first() : $adminMenu.children('li').filter(function () {
        var $link = $(this).children('a');
        var text = normalizeText($link.text());
        return text === 'posts';
      }).first();
      if (!$postsItem.length) {
        return;
      }
      var orderedItems = [findTopLevelByIds(['toplevel_page_certificate_builder']).length ? findTopLevelByIds(['toplevel_page_certificate_builder']) : findTopLevelItem(['page=certificate_builder', 'page=certificate-builder'], ['certificates', 'certificate']), findTopLevelByIds(['toplevel_page_revenue']).length ? findTopLevelByIds(['toplevel_page_revenue']) : findTopLevelItem(['toplevel_page_revenue', 'page=revenue', 'admin.php?page=revenue'], ['analytics', 'revenue']), findTopLevelByIds(['toplevel_page_grades']).length ? findTopLevelByIds(['toplevel_page_grades']) : findTopLevelItem(['toplevel_page_grades', 'page=grades', 'admin.php?page=grades'], ['grades', 'grade']), findTopLevelByIds(['toplevel_page_mslms_zoom']).length ? findTopLevelByIds(['toplevel_page_mslms_zoom']) : findTopLevelItem(['toplevel_page_mslms_zoom', 'page=mslms_zoom', 'admin.php?page=mslms_zoom'], ['zoom conference', 'zoom']), findTopLevelByIds(['menu-posts-stm-google-meets', 'toplevel_page_google_meet_settings', 'toplevel_page_google_meet']).length ? findTopLevelByIds(['menu-posts-stm-google-meets', 'toplevel_page_google_meet_settings', 'toplevel_page_google_meet']) : findTopLevelItem(['post_type=stm-google-meets', 'page=google_meet_settings', 'page=google-meet', 'page=google_meet'], ['google meet']), findTopLevelByIds(['toplevel_page_google_classrooms', 'toplevel_page_google_classroom']).length ? findTopLevelByIds(['toplevel_page_google_classrooms', 'toplevel_page_google_classroom']) : findTopLevelItem(['page=google_classrooms', 'page=google-classroom', 'page=google_classroom'], ['classrooms', 'classroom'])].filter(function ($item) {
        return $item.length;
      });
      if (!orderedItems.length) {
        return;
      }
      orderedItems.forEach(function ($item) {
        return $item.detach();
      });
      orderedItems.forEach(function ($item) {
        $item.insertBefore($postsItem);
      });
    };
    var highlightMenu = function highlightMenu() {
      if ($('body').is("." + classes.join(', .'))) {
        $('#adminmenu > li').removeClass('wp-has-current-submenu wp-menu-open').find('.wp-submenu').css('margin-right', 0);
        $('#toplevel_page_stm-lms-settings').addClass('wp-has-current-submenu wp-menu-open').removeClass('wp-not-current-submenu');
        $('.toplevel_page_stm-lms-settings').addClass('wp-has-current-submenu').removeClass('wp-not-current-submenu');
      }
    };
    var selectedBlinkTimeout = null;
    var scrollToShortcodesGuide = function scrollToShortcodesGuide() {
      if (window.location.hash !== '#stm_lms_shortcodes') {
        return;
      }
      if (!document.getElementById('stm-lms-guide-highlight-style')) {
        var style = document.createElement('style');
        style.id = 'stm-lms-guide-highlight-style';
        style.textContent = "\n                    @keyframes blink-and-fade {\n                        0%, 100% { opacity: 0; }\n                        50% { opacity: 1; }\n                    }\n\n                    @keyframes fade-out {\n                        0% { opacity: 1; }\n                        100% { opacity: 0; }\n                    }\n\n                    .stm_lms_guide.selected-field {\n                        position: relative;\n                    }\n\n                    .stm_lms_guide.selected-field:before {\n                        content: '';\n                        border: 1px solid #2985f7;\n                        border-radius: 5px;\n                        background-color: #2985f74a;\n                        position: absolute;\n                        left: -4px;\n                        right: -4px;\n                        top: -2px;\n                        bottom: -2px;\n                        display: block;\n                        pointer-events: none;\n                        animation: blink-and-fade 1.2s ease-in-out 4, fade-out 6s forwards;\n                        opacity: 0;\n                    }\n                ";
        document.head.appendChild(style);
      }
      var smoothScrollToGuide = function smoothScrollToGuide(selectedField) {
        var targetTop = selectedField.getBoundingClientRect().top + window.scrollY - 180;
        var scrollSteps = [0, 350, 900];
        scrollSteps.forEach(function (delay) {
          setTimeout(function () {
            window.scrollTo({
              top: targetTop,
              behavior: 'smooth'
            });
          }, delay);
        });
      };
      var attempts = 0;
      var maxAttempts = 20;
      var tryScroll = function tryScroll() {
        attempts++;
        var $guide = $('#stm_lms_shortcodes .stm_lms_guide:visible, .wpcfto-tab.active .stm_lms_guide:visible').first();
        if ($guide.length) {
          var selectedField = $guide.get(0);
          selectedField.classList.remove('selected-field');
          // Force reflow to restart animation on repeated opens.
          void selectedField.offsetWidth;
          selectedField.classList.add('selected-field');
          smoothScrollToGuide(selectedField);
          if (selectedBlinkTimeout) {
            clearTimeout(selectedBlinkTimeout);
          }
          selectedBlinkTimeout = setTimeout(function () {
            if (selectedField.classList.contains('selected-field')) {
              selectedField.classList.remove('selected-field');
            }
          }, 9100);
          return;
        }
        if (attempts < maxAttempts) {
          setTimeout(tryScroll, 150);
        }
      };
      setTimeout(tryScroll, 150);
    };

    // unlock banner slider
    var slidePosition = 0;
    var numOfSlide = $("#unlock-slider-slide-holder > div").size();
    $("#unlock-slider-slide-holder").css("width", numOfSlide * 100 + "%");
    $(".unlock-slider-slide").css("width", 100 / numOfSlide + "%");
    for (var a = 0; a < numOfSlide; a++) {
      $('#unlock-slider-slide-nav').append(' <a href="javascript: void(0)" class="unlock-slider-slide-nav-bt' + (a === 0 ? ' active' : '') + '">  </a> ');
    }
    $('body').on('click', '.unlock-slider-slide-nav-bt', function () {
      moveSlide($(this));
      clearInterval(autoPlaySlideInter);
    });
    function moveSlide(thisa) {
      var thisindex = $('#unlock-slider-slide-nav a').index(thisa);
      $('#unlock-slider-slide-holder').css("margin-left", '-' + thisindex + '00%');
      $('#unlock-slider-slide-nav a').removeClass('active');
      thisa.addClass('active');
    }
    function autoPlaySlide() {
      slidePosition++;
      if (slidePosition == numOfSlide) {
        slidePosition = 0;
      }
      moveSlide($("#unlock-slider-slide-nav").children(".unlock-slider-slide-nav-bt:eq(" + slidePosition + ")"));
    }
    var autoPlaySlideInter = setInterval(autoPlaySlide, 4000);
    setupSettingsMenu();
    setupUsersMenu();
    setupTemplatesMenu();
    setupCourseCategorySubmenu();
    setupClassroomsAndCertificatesOrder();
    setupTopLevelLmsMenusOrder();
    setTimeout(setupTopLevelLmsMenusOrder, 250);
    $(window).on('load', setupTopLevelLmsMenusOrder);
    updateDemoLink();
    highlightMenu();
    scrollToShortcodesGuide();
    $(window).on('load', scrollToShortcodesGuide);
    $(window).on('hashchange', scrollToShortcodesGuide);
  });
  $(window).on('load', function () {
    if (!$('body').hasClass('post-type-stm-questions')) return;
    var originalTitle = $('#titlediv input').val();
    var observer = new MutationObserver(function (mutationsList, observer) {
      var $editor = $('#editorquestion_title .ql-editor');
      if ($editor.length) {
        $editor.html(originalTitle);
        observer.disconnect();
      }
      $('#section_question_settings .ql-toolbar').each(function () {
        $(this).find('.ql-color, .ql-blockquote').each(function () {
          $(this).parent().remove();
        });
      });
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  });
})(jQuery);