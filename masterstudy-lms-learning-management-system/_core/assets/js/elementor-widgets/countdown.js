"use strict";

(function ($) {
  var initCountdown = function initCountdown($scope) {
    $scope.find('.masterstudy-countdown').each(function () {
      var $el = $(this);
      if ($el.data('countdown-initialized')) return;
      $el.data('countdown-initialized', true);
      $el.countdown({
        timestamp: $el.data('timer')
      });
    });
  };
  $(window).on('elementor/frontend/init', function () {
    elementorFrontend.hooks.addAction('frontend/element_ready/global', initCountdown);
  });
})(jQuery);