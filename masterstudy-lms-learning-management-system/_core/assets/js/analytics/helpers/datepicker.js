"use strict";

function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }
function createDatepicker(selector) {
  var options = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
  var localeObject = flatpickr.l10ns[stats_data.locale['current_locale']];
  var defaultOptions = {
    inline: true,
    mode: 'range',
    monthSelectorType: 'static',
    locale: _objectSpread(_objectSpread({}, localeObject), {}, {
      firstDayOfWeek: stats_data.locale['firstDayOfWeek']
    })
  };
  var finalOptions = Object.assign({}, defaultOptions, options);
  return flatpickr(selector, finalOptions);
}
function closeDatepickerModal() {
  document.querySelector('.masterstudy-datepicker-modal').classList.remove('masterstudy-datepicker-modal_open');
  document.body.classList.remove('masterstudy-datepicker-body-hidden');
}
function updateDates(period) {
  var datepicker = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
  var firstTime = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : false;
  var saveToLocale = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : true;
  if (!period) {
    return;
  }
  var periodStart = resetTime(period[0]);
  var periodEnd = resetTime(period[1]);
  var selectedStart = resetTime(selectedPeriod[0]);
  var selectedEnd = resetTime(selectedPeriod[1]);
  if (!firstTime && periodStart.getTime() === selectedStart.getTime() && periodEnd.getTime() === selectedEnd.getTime()) {
    return;
  }
  selectedPeriod = period;
  var isDefaultPeriod = false;
  var defaultPeriodKey = null;
  document.querySelectorAll('.masterstudy-datepicker-modal__single-item').forEach(function (item) {
    var periodKey = item.id.replace('masterstudy-datepicker-modal-', '');
    if (defaultDateRanges[periodKey][0].toDateString() === selectedPeriod[0].toDateString() && defaultDateRanges[periodKey][1].toDateString() === selectedPeriod[1].toDateString()) {
      isDefaultPeriod = true;
      defaultPeriodKey = periodKey;
      item.classList.add('masterstudy-datepicker-modal__single-item_fill');
      if (document.querySelector('.masterstudy-date-field-label')) {
        document.querySelector('.masterstudy-date-field-label').textContent = item.textContent.trim();
      }
    } else {
      item.classList.remove('masterstudy-datepicker-modal__single-item_fill');
    }
  });
  if (!firstTime) {
    var event = new CustomEvent('datesUpdated', {
      detail: {
        selectedPeriod: selectedPeriod
      }
    });
    document.dispatchEvent(event);
  }
  if (datepicker) {
    datepicker.setDate(selectedPeriod, true);
  }
  if (document.querySelector('.masterstudy-date-field-value')) {
    document.querySelector('.masterstudy-date-field-value').textContent = "".concat(formatDate(selectedPeriod[0]), " - ").concat(formatDate(selectedPeriod[1]));
  }
  var _periodKey = 'AnalyticsSelectedPeriodKey';
  var _period = 'AnalyticsSelectedPeriod';
  if (typeof stats_data.is_students !== "undefined" && stats_data.is_students) {
    _periodKey = 'StudentsListSelectedPeriodKey';
    _period = 'StudentsListSelectedPeriod';
  } else if (typeof stats_data.is_student !== "undefined" && stats_data.is_student) {
    _periodKey = 'StudentListSelectedPeriodKey';
    _period = 'StudentListSelectedPeriod';
  }
  if (isDefaultPeriod && saveToLocale) {
    localStorage.setItem(_periodKey, defaultPeriodKey);
  } else {
    document.querySelectorAll('.masterstudy-datepicker-modal__single-item').forEach(function (item) {
      item.classList.remove('masterstudy-datepicker-modal__single-item_fill');
    });
    if (document.querySelector('.masterstudy-date-field-label')) {
      document.querySelector('.masterstudy-date-field-label').textContent = stats_data.custom_period;
    }
    if (saveToLocale) {
      localStorage.setItem(_period, JSON.stringify(selectedPeriod));
      localStorage.removeItem(_periodKey);
    }
  }
}
function initializeDatepicker(selector) {
  var datepickerElement = document.querySelector(selector);
  if (!datepickerElement) {
    console.error("Element not found for selector: ".concat(selector));
    return;
  }
  var datepicker = createDatepicker(selector, {
    dateFormat: 'M d, Y',
    defaultDate: selectedPeriod,
    maxDate: new Date(),
    onClose: function onClose(selectedDates, dateStr, instance) {
      updateDates(selectedDates, datepicker);
      closeDatepickerModal();
    }
  });
  if (!(selectedPeriod[0] instanceof Date)) {
    selectedPeriod = selectedPeriod.map(function (dateStr) {
      return new Date(dateStr);
    });
  }
  updateDates(selectedPeriod, datepicker, true);
  document.querySelector('.masterstudy-datepicker-modal__reset').addEventListener('click', function () {
    datepicker.setDate(defaultDateRanges.this_week, true);
    updateDates(defaultDateRanges.this_week, datepicker);
    document.querySelector('#masterstudy-datepicker-modal-this_week').classList.add('masterstudy-datepicker-modal__single-item_fill');
    Array.from(document.querySelector('#masterstudy-datepicker-modal-this_week').parentNode.children).forEach(function (sibling) {
      if (sibling !== document.querySelector('#masterstudy-datepicker-modal-this_week')) {
        sibling.classList.remove('masterstudy-datepicker-modal__single-item_fill');
      }
    });
  });
  document.querySelector('.masterstudy-datepicker-modal__close').addEventListener('click', function () {
    closeDatepickerModal();
  });
  document.querySelectorAll('.masterstudy-datepicker-modal__single-item').forEach(function (item) {
    item.addEventListener('click', function () {
      var period = this.id.replace('masterstudy-datepicker-modal-', '');
      if (defaultDateRanges[period]) {
        datepicker.setDate(defaultDateRanges[period], true);
        updateDates(defaultDateRanges[period], datepicker);
        if (document.querySelector('.masterstudy-date-field-label')) {
          document.querySelector('.masterstudy-date-field-label').textContent = this.textContent.trim();
        }
        closeDatepickerModal();
      }
    });
  });
  if (document.querySelector('.masterstudy-date-field')) {
    document.querySelector('.masterstudy-date-field').addEventListener('click', function () {
      document.querySelector('.masterstudy-datepicker-modal').classList.add('masterstudy-datepicker-modal_open');
      document.body.classList.add('masterstudy-datepicker-body-hidden');
    });
  }
  document.querySelector('.masterstudy-datepicker-modal').addEventListener('click', function (event) {
    if (event.target === this) {
      closeDatepickerModal();
    }
  });
}