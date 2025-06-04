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
    var highlightMenu = function highlightMenu() {
      if ($('body').is("." + classes.join(', .'))) {
        $('#adminmenu > li').removeClass('wp-has-current-submenu wp-menu-open').find('.wp-submenu').css('margin-right', 0);
        $('#toplevel_page_stm-lms-settings').addClass('wp-has-current-submenu wp-menu-open').removeClass('wp-not-current-submenu');
        $('.toplevel_page_stm-lms-settings').addClass('wp-has-current-submenu').removeClass('wp-not-current-submenu');
      }
    };
    var initUnlockSlider = function initUnlockSlider() {
      var $holder = $('#unlock-slider-slide-holder');
      var $slides = $holder.children('div');
      var numSlides = $slides.length;
      if (!numSlides) return;
      $holder.css('width', "".concat(numSlides * 100, "%"));
      $slides.css('width', "".concat(100 / numSlides, "%"));
      var $nav = $('#unlock-slider-slide-nav');
      for (var i = 0; i < numSlides; i++) {
        $nav.append("<a href=\"javascript:void(0)\" class=\"unlock-slider-slide-nav-bt".concat(i === 0 ? ' active' : '', "\"></a>"));
      }
      var current = 0;
      var moveSlide = function moveSlide(index) {
        $holder.css('margin-left', "-".concat(index * 100, "%"));
        $nav.children().removeClass('active').eq(index).addClass('active');
      };
      var autoSlide = function autoSlide() {
        current = (current + 1) % numSlides;
        moveSlide(current);
      };
      var interval = setInterval(autoSlide, 4000);
      $('body').on('click', '.unlock-slider-slide-nav-bt', function () {
        current = $nav.children().index(this);
        moveSlide(current);
        clearInterval(interval);
      });
    };
    setupSettingsMenu();
    setupUsersMenu();
    setupTemplatesMenu();
    updateDemoLink();
    highlightMenu();
    initUnlockSlider();
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