"use strict";

(function ($) {
  $(document).ready(function () {
    var videoPlayers = $('.masterstudy-course-player-lesson-video__wrapper');
    var playButtons = $('.masterstudy-course-player-lesson-video__play-button');
    $('.masterstudy-course-player-lesson-video__wrapper video').click(function () {
      $(this).siblings('span').hide();
    });
    videoPlayers.each(function (index, videoPlayer) {
      var playButton = $(playButtons[index]);
      $(playButton).click(function () {
        playButton.hide();
        videoPlayer.querySelector('video').play();
      });
      $(videoPlayer).on('play', function () {
        playButton.hide();
      });
      $(videoPlayer).on('pause', function () {
        if (!window.matchMedia('(max-width: 576px)').matches) {
          playButton.show();
        }
      });
    });
    $('.masterstudy-plyr-video-player').each(function (i, player) {
      new Plyr($(player));
      var overlay = $("<div>").addClass('plyr-overlay');
      $(player).find('iframe').before(overlay);
      $(player).find('iframe').after(overlay.clone());
    });
  });
})(jQuery);