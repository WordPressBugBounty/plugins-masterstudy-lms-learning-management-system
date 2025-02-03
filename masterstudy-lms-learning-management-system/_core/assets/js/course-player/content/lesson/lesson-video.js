"use strict";

(function ($) {
  $(document).ready(function () {
    var videoPlayerWrapper = $('.masterstudy-course-player-lesson-video__wrapper');
    var videoPlayerContainer = $('.masterstudy-course-player-lesson-video');
    var playButton = $('.masterstudy-course-player-lesson-video__play-button');
    var currentProgressContainer = $('#current-video-progress');
    var iframe = document.getElementById('videoPlayer');
    var requiredProgress = parseInt($('#required-video-progress').data('required-progress'), 10) || 0;
    var userProgress = parseInt(currentProgressContainer.data('progress'), 10) || 0;
    var submitButton = $('[data-id="masterstudy-course-player-lesson-submit"]');
    var hint = $('.masterstudy-course-player-navigation__next .masterstudy-hint');
    var dataQuery = submitButton.attr('data-query');
    var initialLoad = true;
    var youTubePlayer;
    if (userProgress < requiredProgress) {
      submitButton.attr('disabled', 1);
      submitButton.addClass('masterstudy-button_disabled');
    }
    var videoElement = $('.masterstudy-course-player-lesson-video__wrapper video');
    if (videoElement.length) {
      videoElement.click(function () {
        $(this).siblings('span').hide();
      });
      $('body').on('click', '.masterstudy-timecode', function () {
        var timecode = parseInt($(this).data('timecode'), 10);
        if (!isNaN(timecode)) {
          var video = videoElement.get(0);
          if (video) {
            videoPlayerContainer[0].scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
            video.currentTime = timecode;
            if (video.paused) {
              video.play();
            }
          }
        }
      });
    }
    if (videoPlayerWrapper.length && playButton.length) {
      playButton.click(function () {
        playButton.hide();
        videoPlayerWrapper.find('video').get(0).play();
      });
      videoPlayerWrapper.on('play', function () {
        playButton.hide();
      });
      videoPlayerWrapper.on('pause', function () {
        if (!window.matchMedia('(max-width: 576px)').matches) {
          playButton.show();
        }
      });
    }
    if (iframe) {
      if ('youtube' === video_player_data.video_type && !video_player_data.plyr_youtube_player) {
        youTubePlayer = new YT.Player('videoPlayer', {
          events: {
            'onStateChange': onPlayerStateChange
          }
        });
        $('body').on('click', '.masterstudy-timecode', function () {
          var timecode = parseInt($(this).data('timecode'), 10);
          if (!isNaN(timecode) && youTubePlayer) {
            videoPlayerContainer[0].scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
            youTubePlayer.seekTo(timecode, true);
            if (youTubePlayer.getPlayerState() !== YT.PlayerState.PLAYING) {
              youTubePlayer.playVideo();
            }
          }
        });
      } else if ('vimeo' === video_player_data.video_type && !video_player_data.plyr_vimeo_player) {
        var player = new Vimeo.Player(iframe);
        player.on('timeupdate', function (data) {
          return onTimeUpdate(data.seconds, data.duration);
        });
        player.on('ended', finalizeProgress);
        $('body').on('click', '.masterstudy-timecode', function () {
          var timecode = parseInt($(this).data('timecode'), 10);
          if (!isNaN(timecode)) {
            videoPlayerContainer[0].scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
            player.setCurrentTime(timecode).then(function () {
              player.play();
            });
          }
        });
      }
    }
    var plyrVideoPlayer = document.querySelector('.masterstudy-course-player-lesson-video .masterstudy-plyr-video-player');
    if (plyrVideoPlayer) {
      var videoPlayer = new Plyr($(plyrVideoPlayer), {
        invertTime: true
      });
      var overlay = $('<div>').addClass('plyr-overlay');
      var _iframe = $(plyrVideoPlayer).find('iframe');
      if (_iframe.length) {
        _iframe.before(overlay);
        _iframe.after(overlay.clone());
      }
      videoPlayer.on('timeupdate', function (event) {
        var currentTime = event.detail.plyr.currentTime || 0;
        var duration = event.detail.plyr.duration || 0;
        onTimeUpdate(currentTime, duration);
      });
      videoPlayer.on('ended', finalizeProgress);
      $('body').on('click', '.masterstudy-timecode', function () {
        var timecode = parseInt($(this).data('timecode'), 10);
        if (!isNaN(timecode)) {
          videoPlayerContainer[0].scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
          if (video_player_data.video_type === 'youtube') {
            if (videoPlayer.embed && videoPlayer.embed.playVideo) {
              videoPlayer.embed.seekTo(timecode, true);
              if (videoPlayer.embed.getPlayerState() !== YT.PlayerState.PLAYING) {
                videoPlayer.embed.playVideo();
                videoPlayer.play();
              }
            }
          } else if (video_player_data.video_type === 'vimeo') {
            if (videoPlayer.embed && videoPlayer.embed.setCurrentTime) {
              videoPlayer.embed.setCurrentTime(timecode).then(function () {
                videoPlayer.embed.getPaused().then(function (paused) {
                  if (paused) {
                    videoPlayer.embed.play();
                    videoPlayer.play();
                  }
                });
              });
            }
          } else {
            videoPlayer.currentTime = timecode;
            if (videoPlayer.paused) {
              videoPlayer.play();
            }
          }
        }
      });
    }
    var prestoPlayer = document.querySelector('.masterstudy-course-player-lesson-video presto-player');
    if (prestoPlayer) {
      wp.hooks.addAction('presto.playerTimeUpdate', 'masterstudy-presto-time-update', function (player) {
        var currentTime = player.currentTime || 0;
        var duration = player.duration || 0;
        onTimeUpdate(currentTime, duration);
      });
      wp.hooks.addAction('presto.playerEnded', 'masterstudy-presto-ended', function (player) {
        finalizeProgress();
      });
      $('body').on('click', '.masterstudy-timecode', function () {
        var timecode = parseInt($(this).data('timecode'), 10);
        if (!isNaN(timecode)) {
          videoPlayerContainer[0].scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
          prestoPlayer.currentTime = timecode;
          if (!prestoPlayer.playerPlaying) {
            prestoPlayer.play();
          }
        }
      });
    }
    var vdoIframe = document.querySelector('.masterstudy-course-player-lesson-video iframe[src*="vdocipher.com"]');
    if (vdoIframe) {
      var _player = VdoPlayer.getInstance(vdoIframe);
      if (_player && _player.video) {
        _player.video.addEventListener('timeupdate', function () {
          var currentTime = _player.video.currentTime || 0;
          var duration = _player.video.duration || 0;
          onTimeUpdate(currentTime, duration);
        });
        _player.video.addEventListener('ended', finalizeProgress);
        $('body').on('click', '.masterstudy-timecode', function () {
          var timecode = parseInt($(this).data('timecode'), 10);
          if (!isNaN(timecode)) {
            videoPlayerContainer[0].scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
            _player.video.currentTime = timecode;
          }
        });
      }
    }
    function onPlayerStateChange(event) {
      if (event.data == YT.PlayerState.PLAYING) {
        updateYouTubeProgress();
      } else if (event.data === YT.PlayerState.ENDED) {
        finalizeProgress();
      }
    }
    function updateYouTubeProgress() {
      if (youTubePlayer) {
        var currentTime = Math.floor(youTubePlayer.getCurrentTime()) || 0;
        var duration = Math.floor(youTubePlayer.getDuration()) || 0;
        onTimeUpdate(currentTime, duration);
        if (youTubePlayer.getPlayerState() === YT.PlayerState.PLAYING) {
          requestAnimationFrame(updateYouTubeProgress);
        }
      }
    }
    function onTimeUpdate(currentTime, duration) {
      if (initialLoad && userProgress > 0) {
        return;
      }
      initialLoad = false;
      if (duration > 0 && video_player_data.video_progress) {
        var progress = Math.floor(currentTime / duration * 100);
        if (userProgress >= requiredProgress) {
          hint.hide();
          submitButton.removeAttr('disabled');
          submitButton.removeClass('masterstudy-button_disabled');
        }
        if (userProgress > progress) {
          return;
        }
        if (progress > 100) userProgress = 100;
        userProgress = progress;
        if (dataQuery) {
          var queryObject = JSON.parse(dataQuery);
          queryObject.progress = userProgress;
          submitButton.attr('data-query', JSON.stringify(queryObject));
        }
        if (currentProgressContainer) {
          currentProgressContainer.text("".concat(userProgress, "%"));
          currentProgressContainer.attr('data-progress', userProgress);
        }
      }
    }
    function finalizeProgress() {
      if (currentProgressContainer && video_player_data.video_progress) {
        userProgress = 100;
        if (dataQuery) {
          var queryObject = JSON.parse(dataQuery);
          queryObject.progress = userProgress;
          submitButton.attr('data-query', JSON.stringify(queryObject));
          hint.hide();
          submitButton.removeAttr('disabled');
          submitButton.removeClass('masterstudy-button_disabled');
        }
        currentProgressContainer.text("".concat(userProgress, "%"));
        currentProgressContainer.attr('data-progress', userProgress);
      }
    }
  });
})(jQuery);