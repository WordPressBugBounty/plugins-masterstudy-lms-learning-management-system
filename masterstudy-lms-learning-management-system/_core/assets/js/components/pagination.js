"use strict";

(function ($) {
  function initPagination($pagesContainer) {
    if ($pagesContainer.data("masterstudy-pagination-initialized")) {
      return;
    }
    var pagesWrapper = $pagesContainer.find(".masterstudy-pagination__wrapper");
    var pagesList = $pagesContainer.find(".masterstudy-pagination__list");
    var scrollButtonNext = $pagesContainer.find(".masterstudy-pagination__button-next");
    var scrollButtonPrev = $pagesContainer.find(".masterstudy-pagination__button-prev");
    var maxVisiblePages = parseInt($pagesContainer.attr("data-max-visible-pages"), 10);
    var totalPages = parseInt($pagesContainer.attr("data-total-pages"), 10);
    var itemWidth = parseInt($pagesContainer.attr("data-item-width"), 10);
    var isQueryable = $pagesContainer.attr("data-is-queryable") === "1";
    var pageStep = getPageStepWidth(pagesList, itemWidth);
    var wrapperWidth = pagesWrapper.data("width");
    var currentPosition = 0;
    var currentPage = parseInt($pagesContainer.attr("data-current-page"), 10);
    var centeredPage = Math.round(maxVisiblePages / 2);
    var maxPosition = pageStep * Math.max(totalPages - maxVisiblePages, 0);
    var noScroll = totalPages <= maxVisiblePages;
    var containerWidth = wrapperWidth || pageStep * Math.min(maxVisiblePages, totalPages);
    $pagesContainer.data("masterstudy-pagination-initialized", true);
    pagesWrapper.css("width", containerWidth);

    // Page onload
    prevNextButtonState($pagesContainer, currentPage, totalPages);
    currentPosition = calculateInitialPosition(currentPage, centeredPage, totalPages, maxPosition, pageStep);
    setCurrentPage(pagesList, currentPage, "masterstudy-pagination__item_current");
    pagesList.animate({
      left: -currentPosition + "px"
    }, 50);
    scrollButtonNext.on("click.masterstudyPagination", function (e) {
      e.preventDefault();
      if (isQueryable && currentPage < totalPages) {
        updatePageQueryParam(currentPage + 1);
      }
      if (currentPage < totalPages) {
        currentPage = currentPage + 1;
      }
      if (currentPage === totalPages) {
        $(this).addClass("masterstudy-pagination__button_disabled");
      } else {
        $(this).removeClass("masterstudy-pagination__button_disabled");
      }
      if (currentPage !== 1) {
        $(this).parent().find(".masterstudy-pagination__button-prev").removeClass("masterstudy-pagination__button_disabled");
      }
      if (currentPage > centeredPage && currentPosition < maxPosition) {
        currentPosition += pageStep;
        pagesList.animate({
          left: -currentPosition + "px"
        }, 50);
      }
      setCurrentPage(pagesList, currentPage, "masterstudy-pagination__item_current");
    });
    scrollButtonPrev.on("click.masterstudyPagination", function (e) {
      e.preventDefault();
      if (isQueryable && currentPage > 1) {
        updatePageQueryParam(currentPage - 1);
      }
      if (currentPage > 1) {
        currentPage = currentPage - 1;
      }
      if (currentPage === 1) {
        $(this).addClass("masterstudy-pagination__button_disabled");
      } else {
        $(this).removeClass("masterstudy-pagination__button_disabled");
      }
      if (currentPage !== totalPages) {
        $(this).parent().find(".masterstudy-pagination__button-next").removeClass("masterstudy-pagination__button_disabled");
      }
      if (currentPage >= centeredPage && currentPage < totalPages - centeredPage + 1 && currentPosition > 0) {
        currentPosition -= pageStep;
        pagesList.animate({
          left: -currentPosition + "px"
        }, 50);
      }
      setCurrentPage(pagesList, currentPage, "masterstudy-pagination__item_current");
    });
    $pagesContainer.find(".masterstudy-pagination__item-block").on("click.masterstudyPagination", function () {
      currentPage = $(this).data("id");
      if (currentPage < centeredPage) {
        currentPosition = 0;
      } else if (currentPage > totalPages - centeredPage + 1) {
        currentPosition = noScroll ? 0 : maxPosition;
      } else {
        currentPosition = (currentPage - centeredPage) * pageStep;
      }
      prevNextButtonState($pagesContainer, currentPage, totalPages);
      setCurrentPage(pagesList, currentPage, "masterstudy-pagination__item_current");
      pagesList.animate({
        left: -currentPosition + "px"
      }, 50);
      if (isQueryable) {
        updatePageQueryParam(currentPage);
      }
    });
  }
  function init() {
    var $scope = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : $(document);
    $scope.find(".masterstudy-pagination").addBack(".masterstudy-pagination").each(function () {
      initPagination($(this));
    });
  }
  function calculateInitialPosition(currentPage, centeredPage, totalPages, maxPosition, pageStep) {
    var position;
    if (currentPage <= centeredPage) {
      position = 0;
    } else if (currentPage > totalPages - centeredPage) {
      position = maxPosition;
    } else {
      position = (currentPage - centeredPage) * pageStep;
    }
    return position;
  }
  function getPageStepWidth(pagesList, fallbackWidth) {
    var firstPage = pagesList.find(".masterstudy-pagination__item").first();
    var pageWidth = Math.round(firstPage.outerWidth());
    return pageWidth > 0 ? pageWidth : fallbackWidth;
  }
  function setCurrentPage(pagesList, currentPage, className) {
    pagesList.find("[data-id=\"".concat(currentPage, "\"]")).parent().siblings().removeClass(className);
    pagesList.find("[data-id=\"".concat(currentPage, "\"]")).parent().addClass(className);
  }
  function prevNextButtonState(container, currentPage, totalPages) {
    var $btnClassPrev = ".masterstudy-pagination__button-prev";
    var $btnClassNext = ".masterstudy-pagination__button-next";
    container.find($btnClassPrev).removeClass("masterstudy-pagination__button_disabled");
    container.find($btnClassNext).removeClass("masterstudy-pagination__button_disabled");
    if (totalPages === 1) {
      container.find($btnClassPrev).addClass("masterstudy-pagination__button_disabled");
      container.find($btnClassNext).addClass("masterstudy-pagination__button_disabled");
    } else if (currentPage === 1) {
      container.find($btnClassPrev).addClass("masterstudy-pagination__button_disabled");
    } else if (currentPage === totalPages) {
      container.find($btnClassNext).addClass("masterstudy-pagination__button_disabled");
    }
  }
  function updatePageQueryParam(pageNumber) {
    var currentUrl = window.location.href;
    var urlParams = new URLSearchParams(window.location.search);
    var queryName = "page";
    if (urlParams.has(queryName)) {
      urlParams.set(queryName, pageNumber);
    } else {
      urlParams.append(queryName, pageNumber);
    }
    // Set query params and reload
    var queryUrl = currentUrl.split("?")[0] + "?" + urlParams.toString();
    window.history.replaceState({}, document.title, queryUrl);
    window.location.href = queryUrl;
  }
  window.MasterstudyPagination = window.MasterstudyPagination || {};
  window.MasterstudyPagination.init = init;
  $(document).ready(function () {
    init($(document));
  });
})(jQuery);