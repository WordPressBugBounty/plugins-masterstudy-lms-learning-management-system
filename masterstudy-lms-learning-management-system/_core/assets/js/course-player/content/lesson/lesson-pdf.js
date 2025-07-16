"use strict";

function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }
(function ($) {
  var lesson = pdf_lesson;
  var pdfFile = lesson.pdf_file.url;
  var pdfFileName = lesson.pdf_file.label;
  var pdfReadAll = lesson.pdf_read_all;
  var translations = pdf_lesson.translations;
  var MAX_ZOOM_SCALE = 5;
  var MIN_ZOOM_SCALE = 0.5;
  var MOBILE_SCREEN_SIZE = 476;
  var MOBILE_WIDTH = window.innerWidth <= MOBILE_SCREEN_SIZE;
  var IS_MOBILE = isMobileDevice() || MOBILE_WIDTH;
  var CLIENT_WIDTH_OFFSET_CHECK = IS_MOBILE ? 0 : 120;
  var CLIENT_WIDTH_OFFSET_SET = IS_MOBILE ? 40 : 20;
  var LOCAL_STORAGE_PAGE_KEY = 'pdf_lesson_page';
  function isMobileDevice() {
    return /Mobi|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  }
  var pdfDoc;
  var pageNum = 1;
  var scale = MOBILE_WIDTH ? 0.5 : 1;
  var isDragging = false;
  var isExpanded = false;
  var dragStart = {
    x: 0,
    y: 0
  };
  var canvasPosition = {
    x: 0,
    y: 0
  };
  var iframe;
  var initialViewport;
  var isTooltipVisible = false;
  var canvasRef = document.querySelector('.masterstudy-pdf-container__pdf-view');
  var canvasContainerRef = document.querySelector('.masterstudy-pdf-container__canvas');
  var contentWrapper = document.querySelector('.masterstudy-course-player-content__wrapper');
  var totalPagesRef = document.querySelector('.masterstudy-toolbar__total_pages');
  var pagesInputRef = document.querySelector('#toolbar__pages-input');
  var zoomValueRef = $('.masterstudy-toolbar__zoom-value');
  var zoomInBtnRef = document.querySelector('.masterstudy-toolbar__zoom-in-btn');
  var zoomOutBtnRef = document.querySelector('.masterstudy-toolbar__zoom-out-btn');
  var expandBtnRef = $('.masterstudy-toolbar__expand-btn');
  var toolbarTooltipRef = $('.masterstudy-toolbar__menu-tooltip');
  var toolbarContainer = $('.masterstudy-pdf-container__toolbar');
  var bookmarksList = $('.masterstudy-bookmarks__list');
  var submitButton = $('[data-id="masterstudy-course-player-lesson-submit"]');
  var submitHint = $(submitButton).parent().find('.masterstudy-hint');
  var backBtn = $('.masterstudy-pdf-container__back-btn:visible');
  var nextBtn = $('.masterstudy-pdf-container__next-btn:visible');
  $(toolbarTooltipRef).appendTo('.masterstudy-course-player-content');
  if (pdfReadAll) {
    submitButton.attr('disabled', 1);
    submitButton.addClass('masterstudy-button_disabled');
  }
  if (isMobileDevice()) {
    $('.masterstudy-pdf-container').css('--masterstudy-mobile-toolbar-offset', '130px');
  }
  if (isMobileDevice()) {
    expandBtnRef.css('display', 'none');
  }
  var ctx = canvasRef.getContext('2d');
  var observe = new ResizeObserver(function () {
    var clientWidth = contentWrapper.clientWidth - CLIENT_WIDTH_OFFSET_CHECK;
    if (canvasRef.offsetWidth < clientWidth) {
      canvasContainerRef.style.width = "".concat(canvasRef.offsetWidth, "px");
    } else if (canvasContainerRef.offsetWidth >= clientWidth) {
      canvasContainerRef.style.width = "".concat(clientWidth - CLIENT_WIDTH_OFFSET_SET, "px");
    } else if (canvasContainerRef.offsetWidth < canvasRef.width) {
      canvasContainerRef.style.width = "".concat(Math.min(clientWidth - CLIENT_WIDTH_OFFSET_SET, canvasRef.width), "px");
    }
  });
  observe.observe(contentWrapper);
  function updateScale() {
    zoomValueRef.val("".concat(scale * 100, "%"));
  }
  pdfjsLib.getDocument(pdfFile).promise.then(function (pdf) {
    var localPage = pageNum;
    try {
      var localPageData = JSON.parse(localStorage.getItem(LOCAL_STORAGE_PAGE_KEY));
      if (localPageData && localPageData[lesson.lesson_id]) {
        var p = localPageData[lesson.lesson_id];
        localPage = p <= pdf.numPages && p > 1 ? p : pageNum;
      }
    } catch (e) {
      localPage = pageNum;
    }
    pageNum = localPage;
    pdfDoc = pdf;
    totalPagesRef.textContent = pdf.numPages;
    updateScale();
    renderPage(pageNum);
  });
  function handleNavigation(num) {
    var disabled = 'masterstudy-pdf-btn_disabled';
    backBtn.toggleClass(disabled, num === 1);
    nextBtn.toggleClass(disabled, num === pdfDoc.numPages);
    var onLastPage = num === pdfDoc.numPages;
    if (onLastPage) {
      submitButton.removeAttr('disabled');
      submitButton.removeClass('masterstudy-button_disabled');
      submitHint.css('display', 'none');
    }
  }
  function renderPage(num) {
    pagesInputRef.value = num;
    pdfDoc.getPage(num).then(function (page) {
      var viewport = page.getViewport({
        scale: scale
      });
      var localData;
      var dpr = window.devicePixelRatio || 1;
      var transform = [dpr, 0, 0, dpr, 0, 0];
      handleNavigation(num);
      try {
        localData = JSON.parse(localStorage.getItem(LOCAL_STORAGE_PAGE_KEY));
        if (localData) {
          localData[lesson.lesson_id] = num;
        } else {
          localData = _defineProperty({}, lesson.lesson_id, num);
        }
      } catch (e) {
        localData = _defineProperty({}, lesson.lesson_id, num);
      } finally {
        localStorage.setItem(LOCAL_STORAGE_PAGE_KEY, JSON.stringify(localData));
      }
      if (!initialViewport) {
        initialViewport = viewport;
      }
      var clientWidth = contentWrapper.clientWidth - CLIENT_WIDTH_OFFSET_CHECK;
      canvasRef.width = Math.floor(viewport.width * dpr);
      canvasRef.height = Math.floor(viewport.height * dpr);
      canvasRef.style.width = viewport.width + 'px';
      canvasRef.style.height = viewport.height + 'px';
      if (viewport.width < clientWidth) {
        canvasContainerRef.style.width = "".concat(viewport.width, "px");
        canvasContainerRef.style.height = "".concat(viewport.height, "px");
        canvasPosition = {
          x: 0,
          y: 0
        };
      } else {
        canvasContainerRef.style.width = "".concat(clientWidth - CLIENT_WIDTH_OFFSET_SET, "px");
        canvasContainerRef.style.height = "".concat(viewport.height, "px");
      }
      var renderContext = {
        canvasContext: ctx,
        viewport: viewport,
        transform: transform
      };
      page.render(renderContext);
    });
  }
  function applyPosition() {
    var maxX = Math.max(0, canvasRef.offsetWidth - canvasContainerRef.clientWidth);
    var maxY = Math.max(0, canvasRef.offsetHeight - canvasContainerRef.clientHeight);
    canvasPosition.x = Math.min(0, Math.max(-maxX, canvasPosition.x));
    canvasPosition.y = Math.min(0, Math.max(-maxY, canvasPosition.y));
    canvasRef.style.transform = "translate(".concat(canvasPosition.x, "px, ").concat(canvasPosition.y, "px)");
    var textLayer = document.getElementById('pdf-text-layer');
    if (textLayer) {
      textLayer.style.transform = "translate(".concat(canvasPosition.x, "px, ").concat(canvasPosition.y, "px)");
    }
  }
  function closeTooltip() {
    toolbarTooltipRef.removeClass('masterstudy-toolbar__menu-tooltip_visible');
    isTooltipVisible = false;
  }
  function createBookmark(listItem, page, title) {
    $.ajax({
      url: lesson.ajax_url,
      dataType: 'json',
      context: this,
      data: {
        'lesson_id': lesson.lesson_id,
        'course_id': lesson.course_id,
        'page_number': page,
        'title': title,
        'action': 'stm_lms_add_bookmark',
        'nonce': lesson.add_bookmark_nonce
      },
      success: function success(response) {
        $(listItem).find('.masterstudy-bookmarks__list-item-page').text(page);
        $(listItem).find('.masterstudy-bookmarks__list-item-title').text(title);
        $(listItem).attr('data-bookmark-id', response.data.bookmark_id);
        $(listItem).removeClass('masterstudy-bookmarks__list-item_editing');
        $('.masterstudy-bookmarks__new-bookmark-container').show();
      }
    });
  }
  function updateBookmark(listItem, id, page, title) {
    $.ajax({
      url: lesson.ajax_url,
      dataType: 'json',
      context: this,
      data: {
        'id': id,
        'page_number': page,
        'title': title,
        'action': 'stm_lms_update_bookmark',
        'nonce': lesson.update_bookmark_nonce
      },
      success: function success() {
        $(listItem).find('.masterstudy-bookmarks__list-item-page').text(page);
        $(listItem).find('.masterstudy-bookmarks__list-item-title').text(title);
        $(listItem).removeClass('masterstudy-bookmarks__list-item_editing');
        $('.masterstudy-bookmarks__new-bookmark-container').show();
      }
    });
  }
  function removeBookmark(listItem, id) {
    var res = confirm('Are you sure you want to delete this bookmark?');
    if (res) {
      $.ajax({
        url: lesson.ajax_url,
        dataType: 'json',
        context: this,
        data: {
          'id': id,
          'action': 'stm_lms_remove_bookmark',
          'nonce': lesson.remove_bookmark_nonce
        },
        success: function success() {
          $(listItem).remove();
        }
      });
    }
  }
  backBtn.on('click', function () {
    if (pageNum <= 1) return;
    pageNum--;
    renderPage(pageNum);
  });
  nextBtn.on('click', function () {
    if (pageNum >= pdfDoc.numPages) return;
    pageNum++;
    renderPage(pageNum);
  });
  pagesInputRef.addEventListener('input', function (e) {
    if (!e.target.value) return;
    var val = parseInt(e.target.value);
    if (Number.isNaN(val) || val > pdfDoc.numPages || val < 1) return;
    pageNum = val;
    renderPage(pageNum);
  });
  zoomInBtnRef.addEventListener('click', function () {
    if (scale >= MAX_ZOOM_SCALE) return;
    scale += 0.25;
    if (canvasContainerRef.offsetWidth >= canvasRef.width) {
      canvasPosition.x = 0;
      canvasPosition.y = 0;
      canvasRef.style.transform = "translate(".concat(canvasPosition.x, "px, ").concat(canvasPosition.y, "px)");
    }
    updateScale();
    renderPage(pageNum);
  });
  zoomOutBtnRef.addEventListener('click', function () {
    if (scale <= MIN_ZOOM_SCALE) return;
    scale -= 0.25;
    canvasPosition.x = 0;
    canvasPosition.y = 0;
    canvasRef.style.transform = "translate(".concat(canvasPosition.x, "px, ").concat(canvasPosition.y, "px)");
    updateScale();
    renderPage(pageNum);
  });
  canvasContainerRef.addEventListener('mousedown', function (e) {
    if (canvasContainerRef.offsetWidth >= canvasRef.width) return;
    isDragging = true;
    dragStart.x = e.clientX - canvasPosition.x;
    dragStart.y = e.clientY - canvasPosition.y;
    canvasContainerRef.style.cursor = 'grabbing';
  });
  canvasContainerRef.addEventListener('touchstart', function (e) {
    if (e.touches.length !== 1 || canvasContainerRef.offsetWidth >= canvasRef.width) return;
    isDragging = true;
    var touch = e.touches[0];
    dragStart.x = touch.clientX - canvasPosition.x;
    dragStart.y = touch.clientY - canvasPosition.y;
  });
  document.addEventListener('mousemove', function (e) {
    if (!isDragging) return;
    canvasPosition.x = e.clientX - dragStart.x;
    canvasPosition.y = e.clientY - dragStart.y;
    applyPosition();
  });
  document.addEventListener('touchmove', function (e) {
    if (!isDragging || e.touches.length !== 1) return;
    var touch = e.touches[0];
    canvasPosition.x = touch.clientX - dragStart.x;
    canvasPosition.y = touch.clientY - dragStart.y;
    applyPosition();
  });
  document.addEventListener('mouseup', function () {
    isDragging = false;
    canvasContainerRef.style.cursor = 'default';
  });
  document.addEventListener('touchend', function (e) {
    if (isDragging && e.touches.length === 0) {
      isDragging = false;
    }
  });
  document.addEventListener('touchcancel', function () {
    if (isDragging) {
      isDragging = false;
    }
  });
  canvasRef.addEventListener('dragstart', function (e) {
    e.preventDefault();
  });
  var downloadPdf = function downloadPdf() {
    var link = document.createElement('a');
    link.href = pdfFile;
    link.download = pdfFileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    closeTooltip();
  };
  var printPdf = function printPdf() {
    if (isMobileDevice()) {
      window.open(pdfFile, '_blank');
    } else {
      if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.style.display = "none";
        iframe.src = pdfFile;
        document.body.appendChild(iframe);
      }
      iframe.contentWindow.focus();
      iframe.contentWindow.print();
    }
    closeTooltip();
  };
  var openInNewTab = function openInNewTab() {
    window.open(pdfFile, '_blank');
    closeTooltip();
  };
  toolbarContainer.on('click', '.masterstudy-toolbar__download-btn', downloadPdf);
  toolbarTooltipRef.on('click', '.masterstudy-toolbar__download-btn', downloadPdf);
  toolbarContainer.on('click', '.masterstudy-toolbar__print-btn', printPdf);
  toolbarTooltipRef.on('click', '.masterstudy-toolbar__print-btn', printPdf);
  toolbarContainer.on('click', '.masterstudy-toolbar__open-new-tab-btn', openInNewTab);
  toolbarTooltipRef.on('click', '.masterstudy-toolbar__open-new-tab-btn', openInNewTab);
  toolbarTooltipRef.on('click', '.masterstudy-toolbar__menu-tooltip-overlay', function () {
    console.log('here');
    closeTooltip();
  });
  toolbarTooltipRef.on('click', '.masterstudy-toolbar__close-modal-btn', function () {
    closeTooltip();
  });
  $('.masterstudy-toolbar__menu-btn:visible').on('click', function () {
    if (isTooltipVisible) {
      toolbarTooltipRef.removeClass('masterstudy-toolbar__menu-tooltip_visible');
    } else {
      toolbarTooltipRef.addClass('masterstudy-toolbar__menu-tooltip_visible');
    }
    isTooltipVisible = !isTooltipVisible;
  });
  expandBtnRef.on('click', function () {
    if (isExpanded) {
      scale = IS_MOBILE ? 0.5 : 1;
      expandBtnRef.classList.remove('masterstudy-toolbar__expand-btn_expanded');
    } else {
      var clientWidth = contentWrapper.clientWidth - CLIENT_WIDTH_OFFSET_CHECK;
      scale = Math.round(clientWidth / initialViewport.width);
      expandBtnRef.classList.add('masterstudy-toolbar__expand-btn_expanded');
    }
    isExpanded = !isExpanded;
    updateScale();
    renderPage(pageNum);
  });
  bookmarksList.on('click', 'li', function () {
    var page = $(this).find('.masterstudy-bookmarks__list-item-page').text();
    if (!page) return;
    page = parseInt(page);
    if (Number.isNaN(page) || page > pdfDoc.numPages || page < 1) return;
    renderPage(page);
  });
  bookmarksList.on('click', 'li .masterstudy-bookmarks__list-item-save', function (e) {
    e.stopPropagation();
    var listItem = $(this).closest('li');
    var id = $(listItem).data('bookmarkId');
    var pageEl = $(listItem).find('.masterstudy-bookmarks__list-item-page__input');
    var titleEl = $(listItem).find('.masterstudy-bookmarks__list-item-title__input');
    var page = pageEl.val();
    var title = titleEl.val();
    if (!page) {
      pageEl.css('border-color', 'var(--danger-100)');
      return;
    }
    if (!title) {
      titleEl.css('border-color', 'var(--danger-100)');
      return;
    }
    page = parseInt(page);
    if (Number.isNaN(page) || page > pdfDoc.numPages || page < 1) {
      pageEl.css('border-color', 'var(--danger-100)');
      return;
    }
    pageEl.css('border-color', '');
    titleEl.css('border-color', '');
    if (id) {
      updateBookmark(listItem, id, page, title);
    } else {
      createBookmark(listItem, page, title);
    }
  });
  bookmarksList.on('click', 'li .masterstudy-bookmarks__list-item-close', function (e) {
    e.stopPropagation();
    var listItem = $(this).closest('li');
    var id = $(listItem).data('bookmarkId');
    if (!id) {
      $(listItem).remove();
      $('.masterstudy-bookmarks__new-bookmark-container').show();
      return;
    }
    var page = $(listItem).find('.masterstudy-bookmarks__list-item-page').text();
    var title = $(listItem).find('.masterstudy-bookmarks__list-item-title').text();
    $(listItem).find('.masterstudy-bookmarks__list-item-page__input').val(page);
    $(listItem).find('.masterstudy-bookmarks__list-item-title__input').val(title);
    $(listItem).removeClass('masterstudy-bookmarks__list-item_editing');
  });
  bookmarksList.on('click', 'li .masterstudy-bookmarks__list-item-delete-btn', function (e) {
    e.stopPropagation();
    var listItem = $(this).closest('li');
    var id = $(listItem).data('bookmarkId');
    if (!id) return;
    removeBookmark(listItem, id);
  });
  bookmarksList.on('click', 'li .masterstudy-bookmarks__list-item-edit-btn', function (e) {
    e.stopPropagation();
    var listItem = $(this).closest('li');
    var id = $(listItem).data('bookmarkId');
    if (!id) return;
    $(listItem).addClass('masterstudy-bookmarks__list-item_editing');
  });
  $('.masterstudy-bookmarks__new-bookmark-btn').on('click', function () {
    $('.masterstudy-bookmarks__new-bookmark-container').hide();
    var newBookmark = "\n            <li class=\"masterstudy-bookmarks__list-item masterstudy-bookmarks__list-item_editing\">\n\t\t\t\t<div class=\"masterstudy-bookmarks__list-item-content\">\n                    <span class=\"masterstudy-bookmarks__list-item-page\">".concat(pageNum, "</span>\n                    <div class=\"masterstudy-bookmarks__list-item-field\">\n                        <span class=\"masterstudy-bookmarks__list-item-field-label\">").concat(translations['page_number'], "</span>\n                        <input class=\"masterstudy-bookmarks__list-item-page__input\" name=\"page\" placeholder=\"").concat(translations['page'], "\" value=\"").concat(pageNum, "\" type=\"number\" max=\"").concat(pdfDoc.numPages, "\" min=\"1\">\n                    </div>\n\n                    <span class=\"masterstudy-bookmarks__list-item-title\"></span>\n                    <div class=\"masterstudy-bookmarks__list-item-field\">\n                        <span class=\"masterstudy-bookmarks__list-item-field-label\">").concat(translations['note'], "</span>\n                        <input class=\"masterstudy-bookmarks__list-item-title__input\" name=\"title\" placeholder=\"").concat(translations['note_placeholder'], "\" value=\"\" type=\"text\">\n                        <button class=\"masterstudy-bookmarks__list-item-save\">\n                            <span>").concat(translations['save'], "</span>\n                        </button>\n                    </div>\n\n                    <div class=\"masterstudy-bookmarks__list-item-actions\">\n                        <button class=\"masterstudy-bookmarks__list-item-close\">\n                            ").concat(translations['cancel'], "\n                        </button>\n                        <button class=\"masterstudy-bookmarks__list-item-edit-btn\">\n                            <span class=\"stmlms-pencil1\"></span>\n                        </button>\n                        <button class=\"masterstudy-bookmarks__list-item-delete-btn\">\n                            <span class=\"stmlms-trash1\"></span>\n                        </button>\n                    </div>\n                </div>\n            </li>\n        ");
    bookmarksList.append(newBookmark);
  });
  $('.masterstudy-bookmarks__collapse-icon').on('click', function () {
    var isOpened = bookmarksList.is(':visible');
    var content = $('.masterstudy-bookmarks-content');
    if (isOpened) {
      content.animate({
        height: 0
      }, 100, function () {
        setTimeout(function () {
          content.css('display', 'none');
          content.css('height', '');
        }, 300);
      });
      $(content).parent().removeClass('masterstudy-bookmarks_opened');
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
      $(content).parent().addClass('masterstudy-bookmarks_opened');
    }
  });
  $(zoomValueRef).on('change', function () {
    var val = Number($(zoomValueRef).val().replace(/\D/g, ''));
    if (Number.isNaN(val)) {
      updateScale();
      return;
    }
    val = val / 100;
    if (val < MIN_ZOOM_SCALE) {
      val = MIN_ZOOM_SCALE;
    } else if (val > MAX_ZOOM_SCALE) {
      val = MAX_ZOOM_SCALE;
    }
    scale = val;
    updateScale();
    renderPage(pageNum);
  });

  // screen.orientation.addEventListener('change', () => {
  //     renderPage(pageNum)
  // });
})(jQuery);