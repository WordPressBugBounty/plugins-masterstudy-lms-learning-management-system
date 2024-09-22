"use strict";

(function ($) {
  $(document).ready(function () {
    // Audio Player init
    if (typeof MasterstudyAudioPlayer !== 'undefined') {
      MasterstudyAudioPlayer.init({
        selector: '.masterstudy-audio-player',
        showDeleteButton: false
      });
    }
  });
})(jQuery);