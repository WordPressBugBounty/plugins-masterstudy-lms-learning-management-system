(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);throw new Error("Cannot find module '"+o+"'")}var f=n[o]={exports:{}};t[o][0].call(f.exports,function(e){var n=t[o][1][e];return s(n?n:e)},f,f.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
"use strict";

function _createForOfIteratorHelper(o, allowArrayLike) {
  var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"];

  if (!it) {
    if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") {
      if (it) o = it;
      var i = 0;

      var F = function F() {};

      return {
        s: F,
        n: function n() {
          if (i >= o.length) return {
            done: true
          };
          return {
            done: false,
            value: o[i++]
          };
        },
        e: function e(_e) {
          throw _e;
        },
        f: F
      };
    }

    throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
  }

  var normalCompletion = true,
      didErr = false,
      err;
  return {
    s: function s() {
      it = it.call(o);
    },
    n: function n() {
      var step = it.next();
      normalCompletion = step.done;
      return step;
    },
    e: function e(_e2) {
      didErr = true;
      err = _e2;
    },
    f: function f() {
      try {
        if (!normalCompletion && it["return"] != null) it["return"]();
      } finally {
        if (didErr) throw err;
      }
    }
  };
}

function _unsupportedIterableToArray(o, minLen) {
  if (!o) return;
  if (typeof o === "string") return _arrayLikeToArray(o, minLen);
  var n = Object.prototype.toString.call(o).slice(8, -1);
  if (n === "Object" && o.constructor) n = o.constructor.name;
  if (n === "Map" || n === "Set") return Array.from(o);
  if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen);
}

function _arrayLikeToArray(arr, len) {
  if (len == null || len > arr.length) len = arr.length;

  for (var i = 0, arr2 = new Array(len); i < len; i++) {
    arr2[i] = arr[i];
  }

  return arr2;
}

Vue.component('wpcfto_color', {
  template: "\n        <div class=\"wpcfto_generic_field wpcfto_generic_field_color\">\n        \n            <wpcfto_fields_aside_before :fields=\"fields\" :field_label=\"field_label\"></wpcfto_fields_aside_before>\n            \n            <div class=\"wpcfto-field-content\">\n                        \n                <div class=\"stm_colorpicker_wrapper\" v-bind:class=\"['picker-position-' + position]\">\n\n                    <span v-bind:style=\"{'background-color': input_value}\" @click=\"$refs.field_name.focus()\"></span>\n    \n                    <input type=\"text\"\n                           v-bind:name=\"field_name\"\n                           v-bind:placeholder=\"field_label\"\n                           v-bind:id=\"field_id\"\n                           v-model=\"input_value\"\n                           ref=\"field_name\"\n                    />\n    \n                    <div @click=\"changeValueFormat\">\n                        <slider-picker ref=\"colorPicker\" v-model=\"value\"></slider-picker>\n                    </div>\n\n                      <a href=\"#\" @click=\"resetValue\" v-if=\"input_value\" class=\"wpcfto_generic_field_color__clear\">\n                        <i class=\"fa fa-times\"></i>\n                      </a>\n    \n                </div>\n            \n            </div>\n            \n            <wpcfto_fields_aside_after :fields=\"fields\"></wpcfto_fields_aside_after>\n            \n        </div>\n    ",
  props: ['fields', 'field_label', 'field_name', 'field_id', 'field_value', 'default_value', 'format'],
  components: {
    'slider-picker': VueColor.Chrome
  },
  data: function data() {
    return {
      input_value: '',
      position: 'bottom',
      current_format: 'hex',
      value: {
        hex: '#000000',
        rgba: {
          r: 0,
          g: 0,
          b: 0,
          a: 1
        },
        hsl: {
          a: 1,
          h: 1,
          l: 0,
          s: 1
        }
      }
    };
  },
  created: function created() {
    if (this.fields.position) {
      this.position = this.fields.position;
    }
  },
  mounted: function mounted() {
    var _this = this;

    this.$nextTick(function () {
      _this.updatePickerValue(_this.field_value);
    });
  },
  methods: {
    resetValue: function resetValue(event) {
      event.preventDefault();
      this.updateInputValue(this.default_value);
      this.updatePickerValue(this.default_value);
    },
    updatePickerValue: function updatePickerValue(value) {
      if (typeof value === 'string') {
        if (value.indexOf('rgb') !== -1) {
          var colors = value.replace('rgba(', '').slice(0, -1).split(',');
          this.current_format = 'rgba';
          this.value = {
            r: colors[0],
            g: colors[1],
            b: colors[2],
            a: colors[3],
            rgba: {
              r: colors[0],
              g: colors[1],
              b: colors[2],
              a: colors[3]
            }
          };
          this.$refs.colorPicker.fieldsIndex = 1;
        } else if (value.indexOf('hsl') !== -1) {
          var colors = value.replace('hsla(', '').slice(0, -1).split(',');
          this.current_format = 'hsl';
          this.value = {
            hsl: {
              h: colors[0],
              s: colors[1].replace('%', '') / 100,
              l: colors[2].replace('%', '') / 100,
              a: colors[3]
            }
          };
          this.$refs.colorPicker.fieldsIndex = 2;
        } else if (value.indexOf('#') !== -1) {
          this.current_format = 'hex';
          this.value = {
            hex: value
          };
          this.$refs.colorPicker.fieldsIndex = 0;
        }

        this.input_value = value;
      }
    },
    getValueFormat: function getValueFormat(value) {
      var format = 'hex';

      if (typeof value === 'string') {
        if (value.indexOf('rgb') !== -1) {
          format = 'rgba';
        } else if (value.indexOf('hsl') !== -1) {
          format = 'hsl';
        } else if (value.indexOf('#') !== -1) {
          format = 'hex';
        }
      }

      return format;
    },
    updateInputValue: function updateInputValue(value) {
      this.$set(this, 'input_value', value);
      this.$emit('wpcfto-get-value', value);
    },
    changeValueFormat: function changeValueFormat(event) {
      if (event.target.classList.contains('vc-chrome-toggle-icon') || event.target.closest('.vc-chrome-toggle-icon')) {
        var wrapper = event.target.closest('.vc-chrome-fields-wrap');

        if (wrapper) {
          var fields = wrapper.querySelectorAll('.vc-chrome-fields');

          var _iterator = _createForOfIteratorHelper(fields),
              _step;

          try {
            for (_iterator.s(); !(_step = _iterator.n()).done;) {
              var field = _step.value;

              if (field.style.display !== 'none') {
                var format = field.querySelector('.vc-input__label').textContent.toLowerCase().trim();
                var colorValue = '';

                switch (format) {
                  case 'hex':
                    this.current_format = 'hex';
                    colorValue = field.querySelector('.vc-input__input').getAttribute('aria-label');
                    break;

                  case 'r':
                    var rgba = field.querySelectorAll('.vc-input__input');
                    this.current_format = 'rgba';
                    colorValue = 'rgba(' + rgba[0].getAttribute('aria-label') + ',' + rgba[1].getAttribute('aria-label') + ',' + rgba[2].getAttribute('aria-label') + ',' + rgba[3].getAttribute('aria-label') + ')';
                    break;

                  case 'h':
                    var hsla = field.querySelectorAll('.vc-input__input');
                    this.current_format = 'hsla';
                    colorValue = 'hsla(' + hsla[0].getAttribute('aria-label') + ',' + hsla[1].getAttribute('aria-label') + ',' + hsla[2].getAttribute('aria-label') + ',' + hsla[3].getAttribute('aria-label') + ')';
                    break;
                }

                this.updateInputValue(colorValue);
                break;
              }
            }
          } catch (err) {
            _iterator.e(err);
          } finally {
            _iterator.f();
          }
        }
      }
    },
    hexToRgba: function hexToRgba(hex) {
      var c;
      hex = hex.trim();

      if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
        c = hex.substring(1).split('');

        if (c.length === 3) {
          c = [c[0], c[0], c[1], c[1], c[2], c[2]];
        }

        var r = parseInt(c[0] + c[1], 16);
        var g = parseInt(c[2] + c[3], 16);
        var b = parseInt(c[4] + c[5], 16);
        return {
          r: r,
          g: g,
          b: b,
          a: 1
        };
      }

      return null;
    }
  },
  watch: {
    input_value: function input_value(value) {
      var _this2 = this;

      var format = this.format;

      if (format === 'rgba' && typeof value === 'string' && value.startsWith('#')) {
        var rgba = this.hexToRgba(value);

        if (rgba) {
          var rgbaStr = "rgba(".concat(rgba.r, ",").concat(rgba.g, ",").concat(rgba.b, ",").concat(rgba.a, ")");
          this.$nextTick(function () {
            _this2.input_value = rgbaStr;

            _this2.$emit('wpcfto-get-value', rgbaStr);
          });
          return;
        }
      }

      this.$emit('wpcfto-get-value', value);
    },
    value: function value(_value) {
      if (_value.rgba && _value.rgba.a !== undefined && _value.rgba.a < 1 && this.current_format === 'hex') {
        this.current_format = 'rgba';
      }

      switch (this.current_format) {
        case 'hex':
          this.updateInputValue(_value.hex);
          break;

        case 'rgba':
          this.updateInputValue('rgba(' + _value.rgba.r + ',' + _value.rgba.g + ',' + _value.rgba.b + ',' + _value.rgba.a + ')');
          break;

        case 'hsl':
          this.updateInputValue('hsla(' + Math.ceil(_value.hsl.h) + ',' + _value.hsl.s * 100 + '%,' + _value.hsl.l * 100 + '%,' + _value.hsl.a + ')');
          break;
      }
    }
  }
});
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJuYW1lcyI6WyJfY3JlYXRlRm9yT2ZJdGVyYXRvckhlbHBlciIsIm8iLCJhbGxvd0FycmF5TGlrZSIsIml0IiwiU3ltYm9sIiwiaXRlcmF0b3IiLCJBcnJheSIsImlzQXJyYXkiLCJfdW5zdXBwb3J0ZWRJdGVyYWJsZVRvQXJyYXkiLCJsZW5ndGgiLCJpIiwiRiIsInMiLCJuIiwiZG9uZSIsInZhbHVlIiwiZSIsIl9lIiwiZiIsIlR5cGVFcnJvciIsIm5vcm1hbENvbXBsZXRpb24iLCJkaWRFcnIiLCJlcnIiLCJjYWxsIiwic3RlcCIsIm5leHQiLCJfZTIiLCJtaW5MZW4iLCJfYXJyYXlMaWtlVG9BcnJheSIsIk9iamVjdCIsInByb3RvdHlwZSIsInRvU3RyaW5nIiwic2xpY2UiLCJjb25zdHJ1Y3RvciIsIm5hbWUiLCJmcm9tIiwidGVzdCIsImFyciIsImxlbiIsImFycjIiLCJWdWUiLCJjb21wb25lbnQiLCJ0ZW1wbGF0ZSIsInByb3BzIiwiY29tcG9uZW50cyIsIlZ1ZUNvbG9yIiwiQ2hyb21lIiwiZGF0YSIsImlucHV0X3ZhbHVlIiwicG9zaXRpb24iLCJjdXJyZW50X2Zvcm1hdCIsImhleCIsInJnYmEiLCJyIiwiZyIsImIiLCJhIiwiaHNsIiwiaCIsImwiLCJjcmVhdGVkIiwiZmllbGRzIiwibW91bnRlZCIsIl90aGlzIiwiJG5leHRUaWNrIiwidXBkYXRlUGlja2VyVmFsdWUiLCJmaWVsZF92YWx1ZSIsIm1ldGhvZHMiLCJyZXNldFZhbHVlIiwiZXZlbnQiLCJwcmV2ZW50RGVmYXVsdCIsInVwZGF0ZUlucHV0VmFsdWUiLCJkZWZhdWx0X3ZhbHVlIiwiaW5kZXhPZiIsImNvbG9ycyIsInJlcGxhY2UiLCJzcGxpdCIsIiRyZWZzIiwiY29sb3JQaWNrZXIiLCJmaWVsZHNJbmRleCIsImdldFZhbHVlRm9ybWF0IiwiZm9ybWF0IiwiJHNldCIsIiRlbWl0IiwiY2hhbmdlVmFsdWVGb3JtYXQiLCJ0YXJnZXQiLCJjbGFzc0xpc3QiLCJjb250YWlucyIsImNsb3Nlc3QiLCJ3cmFwcGVyIiwicXVlcnlTZWxlY3RvckFsbCIsIl9pdGVyYXRvciIsIl9zdGVwIiwiZmllbGQiLCJzdHlsZSIsImRpc3BsYXkiLCJxdWVyeVNlbGVjdG9yIiwidGV4dENvbnRlbnQiLCJ0b0xvd2VyQ2FzZSIsInRyaW0iLCJjb2xvclZhbHVlIiwiZ2V0QXR0cmlidXRlIiwiaHNsYSIsImhleFRvUmdiYSIsImMiLCJzdWJzdHJpbmciLCJwYXJzZUludCIsIndhdGNoIiwiX3RoaXMyIiwic3RhcnRzV2l0aCIsInJnYmFTdHIiLCJjb25jYXQiLCJfdmFsdWUiLCJ1bmRlZmluZWQiLCJNYXRoIiwiY2VpbCJdLCJzb3VyY2VzIjpbImZha2VfN2MxMWQzY2MuanMiXSwic291cmNlc0NvbnRlbnQiOlsiXCJ1c2Ugc3RyaWN0XCI7XG5cbmZ1bmN0aW9uIF9jcmVhdGVGb3JPZkl0ZXJhdG9ySGVscGVyKG8sIGFsbG93QXJyYXlMaWtlKSB7IHZhciBpdCA9IHR5cGVvZiBTeW1ib2wgIT09IFwidW5kZWZpbmVkXCIgJiYgb1tTeW1ib2wuaXRlcmF0b3JdIHx8IG9bXCJAQGl0ZXJhdG9yXCJdOyBpZiAoIWl0KSB7IGlmIChBcnJheS5pc0FycmF5KG8pIHx8IChpdCA9IF91bnN1cHBvcnRlZEl0ZXJhYmxlVG9BcnJheShvKSkgfHwgYWxsb3dBcnJheUxpa2UgJiYgbyAmJiB0eXBlb2Ygby5sZW5ndGggPT09IFwibnVtYmVyXCIpIHsgaWYgKGl0KSBvID0gaXQ7IHZhciBpID0gMDsgdmFyIEYgPSBmdW5jdGlvbiBGKCkge307IHJldHVybiB7IHM6IEYsIG46IGZ1bmN0aW9uIG4oKSB7IGlmIChpID49IG8ubGVuZ3RoKSByZXR1cm4geyBkb25lOiB0cnVlIH07IHJldHVybiB7IGRvbmU6IGZhbHNlLCB2YWx1ZTogb1tpKytdIH07IH0sIGU6IGZ1bmN0aW9uIGUoX2UpIHsgdGhyb3cgX2U7IH0sIGY6IEYgfTsgfSB0aHJvdyBuZXcgVHlwZUVycm9yKFwiSW52YWxpZCBhdHRlbXB0IHRvIGl0ZXJhdGUgbm9uLWl0ZXJhYmxlIGluc3RhbmNlLlxcbkluIG9yZGVyIHRvIGJlIGl0ZXJhYmxlLCBub24tYXJyYXkgb2JqZWN0cyBtdXN0IGhhdmUgYSBbU3ltYm9sLml0ZXJhdG9yXSgpIG1ldGhvZC5cIik7IH0gdmFyIG5vcm1hbENvbXBsZXRpb24gPSB0cnVlLCBkaWRFcnIgPSBmYWxzZSwgZXJyOyByZXR1cm4geyBzOiBmdW5jdGlvbiBzKCkgeyBpdCA9IGl0LmNhbGwobyk7IH0sIG46IGZ1bmN0aW9uIG4oKSB7IHZhciBzdGVwID0gaXQubmV4dCgpOyBub3JtYWxDb21wbGV0aW9uID0gc3RlcC5kb25lOyByZXR1cm4gc3RlcDsgfSwgZTogZnVuY3Rpb24gZShfZTIpIHsgZGlkRXJyID0gdHJ1ZTsgZXJyID0gX2UyOyB9LCBmOiBmdW5jdGlvbiBmKCkgeyB0cnkgeyBpZiAoIW5vcm1hbENvbXBsZXRpb24gJiYgaXRbXCJyZXR1cm5cIl0gIT0gbnVsbCkgaXRbXCJyZXR1cm5cIl0oKTsgfSBmaW5hbGx5IHsgaWYgKGRpZEVycikgdGhyb3cgZXJyOyB9IH0gfTsgfVxuXG5mdW5jdGlvbiBfdW5zdXBwb3J0ZWRJdGVyYWJsZVRvQXJyYXkobywgbWluTGVuKSB7IGlmICghbykgcmV0dXJuOyBpZiAodHlwZW9mIG8gPT09IFwic3RyaW5nXCIpIHJldHVybiBfYXJyYXlMaWtlVG9BcnJheShvLCBtaW5MZW4pOyB2YXIgbiA9IE9iamVjdC5wcm90b3R5cGUudG9TdHJpbmcuY2FsbChvKS5zbGljZSg4LCAtMSk7IGlmIChuID09PSBcIk9iamVjdFwiICYmIG8uY29uc3RydWN0b3IpIG4gPSBvLmNvbnN0cnVjdG9yLm5hbWU7IGlmIChuID09PSBcIk1hcFwiIHx8IG4gPT09IFwiU2V0XCIpIHJldHVybiBBcnJheS5mcm9tKG8pOyBpZiAobiA9PT0gXCJBcmd1bWVudHNcIiB8fCAvXig/OlVpfEkpbnQoPzo4fDE2fDMyKSg/OkNsYW1wZWQpP0FycmF5JC8udGVzdChuKSkgcmV0dXJuIF9hcnJheUxpa2VUb0FycmF5KG8sIG1pbkxlbik7IH1cblxuZnVuY3Rpb24gX2FycmF5TGlrZVRvQXJyYXkoYXJyLCBsZW4pIHsgaWYgKGxlbiA9PSBudWxsIHx8IGxlbiA+IGFyci5sZW5ndGgpIGxlbiA9IGFyci5sZW5ndGg7IGZvciAodmFyIGkgPSAwLCBhcnIyID0gbmV3IEFycmF5KGxlbik7IGkgPCBsZW47IGkrKykgeyBhcnIyW2ldID0gYXJyW2ldOyB9IHJldHVybiBhcnIyOyB9XG5cblZ1ZS5jb21wb25lbnQoJ3dwY2Z0b19jb2xvcicsIHtcbiAgdGVtcGxhdGU6IFwiXFxuICAgICAgICA8ZGl2IGNsYXNzPVxcXCJ3cGNmdG9fZ2VuZXJpY19maWVsZCB3cGNmdG9fZ2VuZXJpY19maWVsZF9jb2xvclxcXCI+XFxuICAgICAgICBcXG4gICAgICAgICAgICA8d3BjZnRvX2ZpZWxkc19hc2lkZV9iZWZvcmUgOmZpZWxkcz1cXFwiZmllbGRzXFxcIiA6ZmllbGRfbGFiZWw9XFxcImZpZWxkX2xhYmVsXFxcIj48L3dwY2Z0b19maWVsZHNfYXNpZGVfYmVmb3JlPlxcbiAgICAgICAgICAgIFxcbiAgICAgICAgICAgIDxkaXYgY2xhc3M9XFxcIndwY2Z0by1maWVsZC1jb250ZW50XFxcIj5cXG4gICAgICAgICAgICAgICAgICAgICAgICBcXG4gICAgICAgICAgICAgICAgPGRpdiBjbGFzcz1cXFwic3RtX2NvbG9ycGlja2VyX3dyYXBwZXJcXFwiIHYtYmluZDpjbGFzcz1cXFwiWydwaWNrZXItcG9zaXRpb24tJyArIHBvc2l0aW9uXVxcXCI+XFxuXFxuICAgICAgICAgICAgICAgICAgICA8c3BhbiB2LWJpbmQ6c3R5bGU9XFxcInsnYmFja2dyb3VuZC1jb2xvcic6IGlucHV0X3ZhbHVlfVxcXCIgQGNsaWNrPVxcXCIkcmVmcy5maWVsZF9uYW1lLmZvY3VzKClcXFwiPjwvc3Bhbj5cXG4gICAgXFxuICAgICAgICAgICAgICAgICAgICA8aW5wdXQgdHlwZT1cXFwidGV4dFxcXCJcXG4gICAgICAgICAgICAgICAgICAgICAgICAgICB2LWJpbmQ6bmFtZT1cXFwiZmllbGRfbmFtZVxcXCJcXG4gICAgICAgICAgICAgICAgICAgICAgICAgICB2LWJpbmQ6cGxhY2Vob2xkZXI9XFxcImZpZWxkX2xhYmVsXFxcIlxcbiAgICAgICAgICAgICAgICAgICAgICAgICAgIHYtYmluZDppZD1cXFwiZmllbGRfaWRcXFwiXFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgdi1tb2RlbD1cXFwiaW5wdXRfdmFsdWVcXFwiXFxuICAgICAgICAgICAgICAgICAgICAgICAgICAgcmVmPVxcXCJmaWVsZF9uYW1lXFxcIlxcbiAgICAgICAgICAgICAgICAgICAgLz5cXG4gICAgXFxuICAgICAgICAgICAgICAgICAgICA8ZGl2IEBjbGljaz1cXFwiY2hhbmdlVmFsdWVGb3JtYXRcXFwiPlxcbiAgICAgICAgICAgICAgICAgICAgICAgIDxzbGlkZXItcGlja2VyIHJlZj1cXFwiY29sb3JQaWNrZXJcXFwiIHYtbW9kZWw9XFxcInZhbHVlXFxcIj48L3NsaWRlci1waWNrZXI+XFxuICAgICAgICAgICAgICAgICAgICA8L2Rpdj5cXG5cXG4gICAgICAgICAgICAgICAgICAgICAgPGEgaHJlZj1cXFwiI1xcXCIgQGNsaWNrPVxcXCJyZXNldFZhbHVlXFxcIiB2LWlmPVxcXCJpbnB1dF92YWx1ZVxcXCIgY2xhc3M9XFxcIndwY2Z0b19nZW5lcmljX2ZpZWxkX2NvbG9yX19jbGVhclxcXCI+XFxuICAgICAgICAgICAgICAgICAgICAgICAgPGkgY2xhc3M9XFxcImZhIGZhLXRpbWVzXFxcIj48L2k+XFxuICAgICAgICAgICAgICAgICAgICAgIDwvYT5cXG4gICAgXFxuICAgICAgICAgICAgICAgIDwvZGl2PlxcbiAgICAgICAgICAgIFxcbiAgICAgICAgICAgIDwvZGl2PlxcbiAgICAgICAgICAgIFxcbiAgICAgICAgICAgIDx3cGNmdG9fZmllbGRzX2FzaWRlX2FmdGVyIDpmaWVsZHM9XFxcImZpZWxkc1xcXCI+PC93cGNmdG9fZmllbGRzX2FzaWRlX2FmdGVyPlxcbiAgICAgICAgICAgIFxcbiAgICAgICAgPC9kaXY+XFxuICAgIFwiLFxuICBwcm9wczogWydmaWVsZHMnLCAnZmllbGRfbGFiZWwnLCAnZmllbGRfbmFtZScsICdmaWVsZF9pZCcsICdmaWVsZF92YWx1ZScsICdkZWZhdWx0X3ZhbHVlJywgJ2Zvcm1hdCddLFxuICBjb21wb25lbnRzOiB7XG4gICAgJ3NsaWRlci1waWNrZXInOiBWdWVDb2xvci5DaHJvbWVcbiAgfSxcbiAgZGF0YTogZnVuY3Rpb24gZGF0YSgpIHtcbiAgICByZXR1cm4ge1xuICAgICAgaW5wdXRfdmFsdWU6ICcnLFxuICAgICAgcG9zaXRpb246ICdib3R0b20nLFxuICAgICAgY3VycmVudF9mb3JtYXQ6ICdoZXgnLFxuICAgICAgdmFsdWU6IHtcbiAgICAgICAgaGV4OiAnIzAwMDAwMCcsXG4gICAgICAgIHJnYmE6IHtcbiAgICAgICAgICByOiAwLFxuICAgICAgICAgIGc6IDAsXG4gICAgICAgICAgYjogMCxcbiAgICAgICAgICBhOiAxXG4gICAgICAgIH0sXG4gICAgICAgIGhzbDoge1xuICAgICAgICAgIGE6IDEsXG4gICAgICAgICAgaDogMSxcbiAgICAgICAgICBsOiAwLFxuICAgICAgICAgIHM6IDFcbiAgICAgICAgfVxuICAgICAgfVxuICAgIH07XG4gIH0sXG4gIGNyZWF0ZWQ6IGZ1bmN0aW9uIGNyZWF0ZWQoKSB7XG4gICAgaWYgKHRoaXMuZmllbGRzLnBvc2l0aW9uKSB7XG4gICAgICB0aGlzLnBvc2l0aW9uID0gdGhpcy5maWVsZHMucG9zaXRpb247XG4gICAgfVxuICB9LFxuICBtb3VudGVkOiBmdW5jdGlvbiBtb3VudGVkKCkge1xuICAgIHZhciBfdGhpcyA9IHRoaXM7XG5cbiAgICB0aGlzLiRuZXh0VGljayhmdW5jdGlvbiAoKSB7XG4gICAgICBfdGhpcy51cGRhdGVQaWNrZXJWYWx1ZShfdGhpcy5maWVsZF92YWx1ZSk7XG4gICAgfSk7XG4gIH0sXG4gIG1ldGhvZHM6IHtcbiAgICByZXNldFZhbHVlOiBmdW5jdGlvbiByZXNldFZhbHVlKGV2ZW50KSB7XG4gICAgICBldmVudC5wcmV2ZW50RGVmYXVsdCgpO1xuICAgICAgdGhpcy51cGRhdGVJbnB1dFZhbHVlKHRoaXMuZGVmYXVsdF92YWx1ZSk7XG4gICAgICB0aGlzLnVwZGF0ZVBpY2tlclZhbHVlKHRoaXMuZGVmYXVsdF92YWx1ZSk7XG4gICAgfSxcbiAgICB1cGRhdGVQaWNrZXJWYWx1ZTogZnVuY3Rpb24gdXBkYXRlUGlja2VyVmFsdWUodmFsdWUpIHtcbiAgICAgIGlmICh0eXBlb2YgdmFsdWUgPT09ICdzdHJpbmcnKSB7XG4gICAgICAgIGlmICh2YWx1ZS5pbmRleE9mKCdyZ2InKSAhPT0gLTEpIHtcbiAgICAgICAgICB2YXIgY29sb3JzID0gdmFsdWUucmVwbGFjZSgncmdiYSgnLCAnJykuc2xpY2UoMCwgLTEpLnNwbGl0KCcsJyk7XG4gICAgICAgICAgdGhpcy5jdXJyZW50X2Zvcm1hdCA9ICdyZ2JhJztcbiAgICAgICAgICB0aGlzLnZhbHVlID0ge1xuICAgICAgICAgICAgcjogY29sb3JzWzBdLFxuICAgICAgICAgICAgZzogY29sb3JzWzFdLFxuICAgICAgICAgICAgYjogY29sb3JzWzJdLFxuICAgICAgICAgICAgYTogY29sb3JzWzNdLFxuICAgICAgICAgICAgcmdiYToge1xuICAgICAgICAgICAgICByOiBjb2xvcnNbMF0sXG4gICAgICAgICAgICAgIGc6IGNvbG9yc1sxXSxcbiAgICAgICAgICAgICAgYjogY29sb3JzWzJdLFxuICAgICAgICAgICAgICBhOiBjb2xvcnNbM11cbiAgICAgICAgICAgIH1cbiAgICAgICAgICB9O1xuICAgICAgICAgIHRoaXMuJHJlZnMuY29sb3JQaWNrZXIuZmllbGRzSW5kZXggPSAxO1xuICAgICAgICB9IGVsc2UgaWYgKHZhbHVlLmluZGV4T2YoJ2hzbCcpICE9PSAtMSkge1xuICAgICAgICAgIHZhciBjb2xvcnMgPSB2YWx1ZS5yZXBsYWNlKCdoc2xhKCcsICcnKS5zbGljZSgwLCAtMSkuc3BsaXQoJywnKTtcbiAgICAgICAgICB0aGlzLmN1cnJlbnRfZm9ybWF0ID0gJ2hzbCc7XG4gICAgICAgICAgdGhpcy52YWx1ZSA9IHtcbiAgICAgICAgICAgIGhzbDoge1xuICAgICAgICAgICAgICBoOiBjb2xvcnNbMF0sXG4gICAgICAgICAgICAgIHM6IGNvbG9yc1sxXS5yZXBsYWNlKCclJywgJycpIC8gMTAwLFxuICAgICAgICAgICAgICBsOiBjb2xvcnNbMl0ucmVwbGFjZSgnJScsICcnKSAvIDEwMCxcbiAgICAgICAgICAgICAgYTogY29sb3JzWzNdXG4gICAgICAgICAgICB9XG4gICAgICAgICAgfTtcbiAgICAgICAgICB0aGlzLiRyZWZzLmNvbG9yUGlja2VyLmZpZWxkc0luZGV4ID0gMjtcbiAgICAgICAgfSBlbHNlIGlmICh2YWx1ZS5pbmRleE9mKCcjJykgIT09IC0xKSB7XG4gICAgICAgICAgdGhpcy5jdXJyZW50X2Zvcm1hdCA9ICdoZXgnO1xuICAgICAgICAgIHRoaXMudmFsdWUgPSB7XG4gICAgICAgICAgICBoZXg6IHZhbHVlXG4gICAgICAgICAgfTtcbiAgICAgICAgICB0aGlzLiRyZWZzLmNvbG9yUGlja2VyLmZpZWxkc0luZGV4ID0gMDtcbiAgICAgICAgfVxuXG4gICAgICAgIHRoaXMuaW5wdXRfdmFsdWUgPSB2YWx1ZTtcbiAgICAgIH1cbiAgICB9LFxuICAgIGdldFZhbHVlRm9ybWF0OiBmdW5jdGlvbiBnZXRWYWx1ZUZvcm1hdCh2YWx1ZSkge1xuICAgICAgdmFyIGZvcm1hdCA9ICdoZXgnO1xuXG4gICAgICBpZiAodHlwZW9mIHZhbHVlID09PSAnc3RyaW5nJykge1xuICAgICAgICBpZiAodmFsdWUuaW5kZXhPZigncmdiJykgIT09IC0xKSB7XG4gICAgICAgICAgZm9ybWF0ID0gJ3JnYmEnO1xuICAgICAgICB9IGVsc2UgaWYgKHZhbHVlLmluZGV4T2YoJ2hzbCcpICE9PSAtMSkge1xuICAgICAgICAgIGZvcm1hdCA9ICdoc2wnO1xuICAgICAgICB9IGVsc2UgaWYgKHZhbHVlLmluZGV4T2YoJyMnKSAhPT0gLTEpIHtcbiAgICAgICAgICBmb3JtYXQgPSAnaGV4JztcbiAgICAgICAgfVxuICAgICAgfVxuXG4gICAgICByZXR1cm4gZm9ybWF0O1xuICAgIH0sXG4gICAgdXBkYXRlSW5wdXRWYWx1ZTogZnVuY3Rpb24gdXBkYXRlSW5wdXRWYWx1ZSh2YWx1ZSkge1xuICAgICAgdGhpcy4kc2V0KHRoaXMsICdpbnB1dF92YWx1ZScsIHZhbHVlKTtcbiAgICAgIHRoaXMuJGVtaXQoJ3dwY2Z0by1nZXQtdmFsdWUnLCB2YWx1ZSk7XG4gICAgfSxcbiAgICBjaGFuZ2VWYWx1ZUZvcm1hdDogZnVuY3Rpb24gY2hhbmdlVmFsdWVGb3JtYXQoZXZlbnQpIHtcbiAgICAgIGlmIChldmVudC50YXJnZXQuY2xhc3NMaXN0LmNvbnRhaW5zKCd2Yy1jaHJvbWUtdG9nZ2xlLWljb24nKSB8fCBldmVudC50YXJnZXQuY2xvc2VzdCgnLnZjLWNocm9tZS10b2dnbGUtaWNvbicpKSB7XG4gICAgICAgIHZhciB3cmFwcGVyID0gZXZlbnQudGFyZ2V0LmNsb3Nlc3QoJy52Yy1jaHJvbWUtZmllbGRzLXdyYXAnKTtcblxuICAgICAgICBpZiAod3JhcHBlcikge1xuICAgICAgICAgIHZhciBmaWVsZHMgPSB3cmFwcGVyLnF1ZXJ5U2VsZWN0b3JBbGwoJy52Yy1jaHJvbWUtZmllbGRzJyk7XG5cbiAgICAgICAgICB2YXIgX2l0ZXJhdG9yID0gX2NyZWF0ZUZvck9mSXRlcmF0b3JIZWxwZXIoZmllbGRzKSxcbiAgICAgICAgICAgICAgX3N0ZXA7XG5cbiAgICAgICAgICB0cnkge1xuICAgICAgICAgICAgZm9yIChfaXRlcmF0b3IucygpOyAhKF9zdGVwID0gX2l0ZXJhdG9yLm4oKSkuZG9uZTspIHtcbiAgICAgICAgICAgICAgdmFyIGZpZWxkID0gX3N0ZXAudmFsdWU7XG5cbiAgICAgICAgICAgICAgaWYgKGZpZWxkLnN0eWxlLmRpc3BsYXkgIT09ICdub25lJykge1xuICAgICAgICAgICAgICAgIHZhciBmb3JtYXQgPSBmaWVsZC5xdWVyeVNlbGVjdG9yKCcudmMtaW5wdXRfX2xhYmVsJykudGV4dENvbnRlbnQudG9Mb3dlckNhc2UoKS50cmltKCk7XG4gICAgICAgICAgICAgICAgdmFyIGNvbG9yVmFsdWUgPSAnJztcblxuICAgICAgICAgICAgICAgIHN3aXRjaCAoZm9ybWF0KSB7XG4gICAgICAgICAgICAgICAgICBjYXNlICdoZXgnOlxuICAgICAgICAgICAgICAgICAgICB0aGlzLmN1cnJlbnRfZm9ybWF0ID0gJ2hleCc7XG4gICAgICAgICAgICAgICAgICAgIGNvbG9yVmFsdWUgPSBmaWVsZC5xdWVyeVNlbGVjdG9yKCcudmMtaW5wdXRfX2lucHV0JykuZ2V0QXR0cmlidXRlKCdhcmlhLWxhYmVsJyk7XG4gICAgICAgICAgICAgICAgICAgIGJyZWFrO1xuXG4gICAgICAgICAgICAgICAgICBjYXNlICdyJzpcbiAgICAgICAgICAgICAgICAgICAgdmFyIHJnYmEgPSBmaWVsZC5xdWVyeVNlbGVjdG9yQWxsKCcudmMtaW5wdXRfX2lucHV0Jyk7XG4gICAgICAgICAgICAgICAgICAgIHRoaXMuY3VycmVudF9mb3JtYXQgPSAncmdiYSc7XG4gICAgICAgICAgICAgICAgICAgIGNvbG9yVmFsdWUgPSAncmdiYSgnICsgcmdiYVswXS5nZXRBdHRyaWJ1dGUoJ2FyaWEtbGFiZWwnKSArICcsJyArIHJnYmFbMV0uZ2V0QXR0cmlidXRlKCdhcmlhLWxhYmVsJykgKyAnLCcgKyByZ2JhWzJdLmdldEF0dHJpYnV0ZSgnYXJpYS1sYWJlbCcpICsgJywnICsgcmdiYVszXS5nZXRBdHRyaWJ1dGUoJ2FyaWEtbGFiZWwnKSArICcpJztcbiAgICAgICAgICAgICAgICAgICAgYnJlYWs7XG5cbiAgICAgICAgICAgICAgICAgIGNhc2UgJ2gnOlxuICAgICAgICAgICAgICAgICAgICB2YXIgaHNsYSA9IGZpZWxkLnF1ZXJ5U2VsZWN0b3JBbGwoJy52Yy1pbnB1dF9faW5wdXQnKTtcbiAgICAgICAgICAgICAgICAgICAgdGhpcy5jdXJyZW50X2Zvcm1hdCA9ICdoc2xhJztcbiAgICAgICAgICAgICAgICAgICAgY29sb3JWYWx1ZSA9ICdoc2xhKCcgKyBoc2xhWzBdLmdldEF0dHJpYnV0ZSgnYXJpYS1sYWJlbCcpICsgJywnICsgaHNsYVsxXS5nZXRBdHRyaWJ1dGUoJ2FyaWEtbGFiZWwnKSArICcsJyArIGhzbGFbMl0uZ2V0QXR0cmlidXRlKCdhcmlhLWxhYmVsJykgKyAnLCcgKyBoc2xhWzNdLmdldEF0dHJpYnV0ZSgnYXJpYS1sYWJlbCcpICsgJyknO1xuICAgICAgICAgICAgICAgICAgICBicmVhaztcbiAgICAgICAgICAgICAgICB9XG5cbiAgICAgICAgICAgICAgICB0aGlzLnVwZGF0ZUlucHV0VmFsdWUoY29sb3JWYWx1ZSk7XG4gICAgICAgICAgICAgICAgYnJlYWs7XG4gICAgICAgICAgICAgIH1cbiAgICAgICAgICAgIH1cbiAgICAgICAgICB9IGNhdGNoIChlcnIpIHtcbiAgICAgICAgICAgIF9pdGVyYXRvci5lKGVycik7XG4gICAgICAgICAgfSBmaW5hbGx5IHtcbiAgICAgICAgICAgIF9pdGVyYXRvci5mKCk7XG4gICAgICAgICAgfVxuICAgICAgICB9XG4gICAgICB9XG4gICAgfSxcbiAgICBoZXhUb1JnYmE6IGZ1bmN0aW9uIGhleFRvUmdiYShoZXgpIHtcbiAgICAgIHZhciBjO1xuICAgICAgaGV4ID0gaGV4LnRyaW0oKTtcblxuICAgICAgaWYgKC9eIyhbQS1GYS1mMC05XXszfSl7MSwyfSQvLnRlc3QoaGV4KSkge1xuICAgICAgICBjID0gaGV4LnN1YnN0cmluZygxKS5zcGxpdCgnJyk7XG5cbiAgICAgICAgaWYgKGMubGVuZ3RoID09PSAzKSB7XG4gICAgICAgICAgYyA9IFtjWzBdLCBjWzBdLCBjWzFdLCBjWzFdLCBjWzJdLCBjWzJdXTtcbiAgICAgICAgfVxuXG4gICAgICAgIHZhciByID0gcGFyc2VJbnQoY1swXSArIGNbMV0sIDE2KTtcbiAgICAgICAgdmFyIGcgPSBwYXJzZUludChjWzJdICsgY1szXSwgMTYpO1xuICAgICAgICB2YXIgYiA9IHBhcnNlSW50KGNbNF0gKyBjWzVdLCAxNik7XG4gICAgICAgIHJldHVybiB7XG4gICAgICAgICAgcjogcixcbiAgICAgICAgICBnOiBnLFxuICAgICAgICAgIGI6IGIsXG4gICAgICAgICAgYTogMVxuICAgICAgICB9O1xuICAgICAgfVxuXG4gICAgICByZXR1cm4gbnVsbDtcbiAgICB9XG4gIH0sXG4gIHdhdGNoOiB7XG4gICAgaW5wdXRfdmFsdWU6IGZ1bmN0aW9uIGlucHV0X3ZhbHVlKHZhbHVlKSB7XG4gICAgICB2YXIgX3RoaXMyID0gdGhpcztcblxuICAgICAgdmFyIGZvcm1hdCA9IHRoaXMuZm9ybWF0O1xuXG4gICAgICBpZiAoZm9ybWF0ID09PSAncmdiYScgJiYgdHlwZW9mIHZhbHVlID09PSAnc3RyaW5nJyAmJiB2YWx1ZS5zdGFydHNXaXRoKCcjJykpIHtcbiAgICAgICAgdmFyIHJnYmEgPSB0aGlzLmhleFRvUmdiYSh2YWx1ZSk7XG5cbiAgICAgICAgaWYgKHJnYmEpIHtcbiAgICAgICAgICB2YXIgcmdiYVN0ciA9IFwicmdiYShcIi5jb25jYXQocmdiYS5yLCBcIixcIikuY29uY2F0KHJnYmEuZywgXCIsXCIpLmNvbmNhdChyZ2JhLmIsIFwiLFwiKS5jb25jYXQocmdiYS5hLCBcIilcIik7XG4gICAgICAgICAgdGhpcy4kbmV4dFRpY2soZnVuY3Rpb24gKCkge1xuICAgICAgICAgICAgX3RoaXMyLmlucHV0X3ZhbHVlID0gcmdiYVN0cjtcblxuICAgICAgICAgICAgX3RoaXMyLiRlbWl0KCd3cGNmdG8tZ2V0LXZhbHVlJywgcmdiYVN0cik7XG4gICAgICAgICAgfSk7XG4gICAgICAgICAgcmV0dXJuO1xuICAgICAgICB9XG4gICAgICB9XG5cbiAgICAgIHRoaXMuJGVtaXQoJ3dwY2Z0by1nZXQtdmFsdWUnLCB2YWx1ZSk7XG4gICAgfSxcbiAgICB2YWx1ZTogZnVuY3Rpb24gdmFsdWUoX3ZhbHVlKSB7XG4gICAgICBpZiAoX3ZhbHVlLnJnYmEgJiYgX3ZhbHVlLnJnYmEuYSAhPT0gdW5kZWZpbmVkICYmIF92YWx1ZS5yZ2JhLmEgPCAxICYmIHRoaXMuY3VycmVudF9mb3JtYXQgPT09ICdoZXgnKSB7XG4gICAgICAgIHRoaXMuY3VycmVudF9mb3JtYXQgPSAncmdiYSc7XG4gICAgICB9XG5cbiAgICAgIHN3aXRjaCAodGhpcy5jdXJyZW50X2Zvcm1hdCkge1xuICAgICAgICBjYXNlICdoZXgnOlxuICAgICAgICAgIHRoaXMudXBkYXRlSW5wdXRWYWx1ZShfdmFsdWUuaGV4KTtcbiAgICAgICAgICBicmVhaztcblxuICAgICAgICBjYXNlICdyZ2JhJzpcbiAgICAgICAgICB0aGlzLnVwZGF0ZUlucHV0VmFsdWUoJ3JnYmEoJyArIF92YWx1ZS5yZ2JhLnIgKyAnLCcgKyBfdmFsdWUucmdiYS5nICsgJywnICsgX3ZhbHVlLnJnYmEuYiArICcsJyArIF92YWx1ZS5yZ2JhLmEgKyAnKScpO1xuICAgICAgICAgIGJyZWFrO1xuXG4gICAgICAgIGNhc2UgJ2hzbCc6XG4gICAgICAgICAgdGhpcy51cGRhdGVJbnB1dFZhbHVlKCdoc2xhKCcgKyBNYXRoLmNlaWwoX3ZhbHVlLmhzbC5oKSArICcsJyArIF92YWx1ZS5oc2wucyAqIDEwMCArICclLCcgKyBfdmFsdWUuaHNsLmwgKiAxMDAgKyAnJSwnICsgX3ZhbHVlLmhzbC5hICsgJyknKTtcbiAgICAgICAgICBicmVhaztcbiAgICAgIH1cbiAgICB9XG4gIH1cbn0pOyJdLCJtYXBwaW5ncyI6IkFBQUE7O0FBRUEsU0FBU0EsMEJBQVQsQ0FBb0NDLENBQXBDLEVBQXVDQyxjQUF2QyxFQUF1RDtFQUFFLElBQUlDLEVBQUUsR0FBRyxPQUFPQyxNQUFQLEtBQWtCLFdBQWxCLElBQWlDSCxDQUFDLENBQUNHLE1BQU0sQ0FBQ0MsUUFBUixDQUFsQyxJQUF1REosQ0FBQyxDQUFDLFlBQUQsQ0FBakU7O0VBQWlGLElBQUksQ0FBQ0UsRUFBTCxFQUFTO0lBQUUsSUFBSUcsS0FBSyxDQUFDQyxPQUFOLENBQWNOLENBQWQsTUFBcUJFLEVBQUUsR0FBR0ssMkJBQTJCLENBQUNQLENBQUQsQ0FBckQsS0FBNkRDLGNBQWMsSUFBSUQsQ0FBbEIsSUFBdUIsT0FBT0EsQ0FBQyxDQUFDUSxNQUFULEtBQW9CLFFBQTVHLEVBQXNIO01BQUUsSUFBSU4sRUFBSixFQUFRRixDQUFDLEdBQUdFLEVBQUo7TUFBUSxJQUFJTyxDQUFDLEdBQUcsQ0FBUjs7TUFBVyxJQUFJQyxDQUFDLEdBQUcsU0FBU0EsQ0FBVCxHQUFhLENBQUUsQ0FBdkI7O01BQXlCLE9BQU87UUFBRUMsQ0FBQyxFQUFFRCxDQUFMO1FBQVFFLENBQUMsRUFBRSxTQUFTQSxDQUFULEdBQWE7VUFBRSxJQUFJSCxDQUFDLElBQUlULENBQUMsQ0FBQ1EsTUFBWCxFQUFtQixPQUFPO1lBQUVLLElBQUksRUFBRTtVQUFSLENBQVA7VUFBdUIsT0FBTztZQUFFQSxJQUFJLEVBQUUsS0FBUjtZQUFlQyxLQUFLLEVBQUVkLENBQUMsQ0FBQ1MsQ0FBQyxFQUFGO1VBQXZCLENBQVA7UUFBd0MsQ0FBNUc7UUFBOEdNLENBQUMsRUFBRSxTQUFTQSxDQUFULENBQVdDLEVBQVgsRUFBZTtVQUFFLE1BQU1BLEVBQU47UUFBVyxDQUE3STtRQUErSUMsQ0FBQyxFQUFFUDtNQUFsSixDQUFQO0lBQStKOztJQUFDLE1BQU0sSUFBSVEsU0FBSixDQUFjLHVJQUFkLENBQU47RUFBK0o7O0VBQUMsSUFBSUMsZ0JBQWdCLEdBQUcsSUFBdkI7RUFBQSxJQUE2QkMsTUFBTSxHQUFHLEtBQXRDO0VBQUEsSUFBNkNDLEdBQTdDO0VBQWtELE9BQU87SUFBRVYsQ0FBQyxFQUFFLFNBQVNBLENBQVQsR0FBYTtNQUFFVCxFQUFFLEdBQUdBLEVBQUUsQ0FBQ29CLElBQUgsQ0FBUXRCLENBQVIsQ0FBTDtJQUFrQixDQUF0QztJQUF3Q1ksQ0FBQyxFQUFFLFNBQVNBLENBQVQsR0FBYTtNQUFFLElBQUlXLElBQUksR0FBR3JCLEVBQUUsQ0FBQ3NCLElBQUgsRUFBWDtNQUFzQkwsZ0JBQWdCLEdBQUdJLElBQUksQ0FBQ1YsSUFBeEI7TUFBOEIsT0FBT1UsSUFBUDtJQUFjLENBQTVIO0lBQThIUixDQUFDLEVBQUUsU0FBU0EsQ0FBVCxDQUFXVSxHQUFYLEVBQWdCO01BQUVMLE1BQU0sR0FBRyxJQUFUO01BQWVDLEdBQUcsR0FBR0ksR0FBTjtJQUFZLENBQTlLO0lBQWdMUixDQUFDLEVBQUUsU0FBU0EsQ0FBVCxHQUFhO01BQUUsSUFBSTtRQUFFLElBQUksQ0FBQ0UsZ0JBQUQsSUFBcUJqQixFQUFFLENBQUMsUUFBRCxDQUFGLElBQWdCLElBQXpDLEVBQStDQSxFQUFFLENBQUMsUUFBRCxDQUFGO01BQWlCLENBQXRFLFNBQStFO1FBQUUsSUFBSWtCLE1BQUosRUFBWSxNQUFNQyxHQUFOO01BQVk7SUFBRTtFQUE3UyxDQUFQO0FBQXlUOztBQUU1K0IsU0FBU2QsMkJBQVQsQ0FBcUNQLENBQXJDLEVBQXdDMEIsTUFBeEMsRUFBZ0Q7RUFBRSxJQUFJLENBQUMxQixDQUFMLEVBQVE7RUFBUSxJQUFJLE9BQU9BLENBQVAsS0FBYSxRQUFqQixFQUEyQixPQUFPMkIsaUJBQWlCLENBQUMzQixDQUFELEVBQUkwQixNQUFKLENBQXhCO0VBQXFDLElBQUlkLENBQUMsR0FBR2dCLE1BQU0sQ0FBQ0MsU0FBUCxDQUFpQkMsUUFBakIsQ0FBMEJSLElBQTFCLENBQStCdEIsQ0FBL0IsRUFBa0MrQixLQUFsQyxDQUF3QyxDQUF4QyxFQUEyQyxDQUFDLENBQTVDLENBQVI7RUFBd0QsSUFBSW5CLENBQUMsS0FBSyxRQUFOLElBQWtCWixDQUFDLENBQUNnQyxXQUF4QixFQUFxQ3BCLENBQUMsR0FBR1osQ0FBQyxDQUFDZ0MsV0FBRixDQUFjQyxJQUFsQjtFQUF3QixJQUFJckIsQ0FBQyxLQUFLLEtBQU4sSUFBZUEsQ0FBQyxLQUFLLEtBQXpCLEVBQWdDLE9BQU9QLEtBQUssQ0FBQzZCLElBQU4sQ0FBV2xDLENBQVgsQ0FBUDtFQUFzQixJQUFJWSxDQUFDLEtBQUssV0FBTixJQUFxQiwyQ0FBMkN1QixJQUEzQyxDQUFnRHZCLENBQWhELENBQXpCLEVBQTZFLE9BQU9lLGlCQUFpQixDQUFDM0IsQ0FBRCxFQUFJMEIsTUFBSixDQUF4QjtBQUFzQzs7QUFFaGEsU0FBU0MsaUJBQVQsQ0FBMkJTLEdBQTNCLEVBQWdDQyxHQUFoQyxFQUFxQztFQUFFLElBQUlBLEdBQUcsSUFBSSxJQUFQLElBQWVBLEdBQUcsR0FBR0QsR0FBRyxDQUFDNUIsTUFBN0IsRUFBcUM2QixHQUFHLEdBQUdELEdBQUcsQ0FBQzVCLE1BQVY7O0VBQWtCLEtBQUssSUFBSUMsQ0FBQyxHQUFHLENBQVIsRUFBVzZCLElBQUksR0FBRyxJQUFJakMsS0FBSixDQUFVZ0MsR0FBVixDQUF2QixFQUF1QzVCLENBQUMsR0FBRzRCLEdBQTNDLEVBQWdENUIsQ0FBQyxFQUFqRCxFQUFxRDtJQUFFNkIsSUFBSSxDQUFDN0IsQ0FBRCxDQUFKLEdBQVUyQixHQUFHLENBQUMzQixDQUFELENBQWI7RUFBbUI7O0VBQUMsT0FBTzZCLElBQVA7QUFBYzs7QUFFdkxDLEdBQUcsQ0FBQ0MsU0FBSixDQUFjLGNBQWQsRUFBOEI7RUFDNUJDLFFBQVEsRUFBRSxxN0NBRGtCO0VBRTVCQyxLQUFLLEVBQUUsQ0FBQyxRQUFELEVBQVcsYUFBWCxFQUEwQixZQUExQixFQUF3QyxVQUF4QyxFQUFvRCxhQUFwRCxFQUFtRSxlQUFuRSxFQUFvRixRQUFwRixDQUZxQjtFQUc1QkMsVUFBVSxFQUFFO0lBQ1YsaUJBQWlCQyxRQUFRLENBQUNDO0VBRGhCLENBSGdCO0VBTTVCQyxJQUFJLEVBQUUsU0FBU0EsSUFBVCxHQUFnQjtJQUNwQixPQUFPO01BQ0xDLFdBQVcsRUFBRSxFQURSO01BRUxDLFFBQVEsRUFBRSxRQUZMO01BR0xDLGNBQWMsRUFBRSxLQUhYO01BSUxuQyxLQUFLLEVBQUU7UUFDTG9DLEdBQUcsRUFBRSxTQURBO1FBRUxDLElBQUksRUFBRTtVQUNKQyxDQUFDLEVBQUUsQ0FEQztVQUVKQyxDQUFDLEVBQUUsQ0FGQztVQUdKQyxDQUFDLEVBQUUsQ0FIQztVQUlKQyxDQUFDLEVBQUU7UUFKQyxDQUZEO1FBUUxDLEdBQUcsRUFBRTtVQUNIRCxDQUFDLEVBQUUsQ0FEQTtVQUVIRSxDQUFDLEVBQUUsQ0FGQTtVQUdIQyxDQUFDLEVBQUUsQ0FIQTtVQUlIL0MsQ0FBQyxFQUFFO1FBSkE7TUFSQTtJQUpGLENBQVA7RUFvQkQsQ0EzQjJCO0VBNEI1QmdELE9BQU8sRUFBRSxTQUFTQSxPQUFULEdBQW1CO0lBQzFCLElBQUksS0FBS0MsTUFBTCxDQUFZWixRQUFoQixFQUEwQjtNQUN4QixLQUFLQSxRQUFMLEdBQWdCLEtBQUtZLE1BQUwsQ0FBWVosUUFBNUI7SUFDRDtFQUNGLENBaEMyQjtFQWlDNUJhLE9BQU8sRUFBRSxTQUFTQSxPQUFULEdBQW1CO0lBQzFCLElBQUlDLEtBQUssR0FBRyxJQUFaOztJQUVBLEtBQUtDLFNBQUwsQ0FBZSxZQUFZO01BQ3pCRCxLQUFLLENBQUNFLGlCQUFOLENBQXdCRixLQUFLLENBQUNHLFdBQTlCO0lBQ0QsQ0FGRDtFQUdELENBdkMyQjtFQXdDNUJDLE9BQU8sRUFBRTtJQUNQQyxVQUFVLEVBQUUsU0FBU0EsVUFBVCxDQUFvQkMsS0FBcEIsRUFBMkI7TUFDckNBLEtBQUssQ0FBQ0MsY0FBTjtNQUNBLEtBQUtDLGdCQUFMLENBQXNCLEtBQUtDLGFBQTNCO01BQ0EsS0FBS1AsaUJBQUwsQ0FBdUIsS0FBS08sYUFBNUI7SUFDRCxDQUxNO0lBTVBQLGlCQUFpQixFQUFFLFNBQVNBLGlCQUFULENBQTJCbEQsS0FBM0IsRUFBa0M7TUFDbkQsSUFBSSxPQUFPQSxLQUFQLEtBQWlCLFFBQXJCLEVBQStCO1FBQzdCLElBQUlBLEtBQUssQ0FBQzBELE9BQU4sQ0FBYyxLQUFkLE1BQXlCLENBQUMsQ0FBOUIsRUFBaUM7VUFDL0IsSUFBSUMsTUFBTSxHQUFHM0QsS0FBSyxDQUFDNEQsT0FBTixDQUFjLE9BQWQsRUFBdUIsRUFBdkIsRUFBMkIzQyxLQUEzQixDQUFpQyxDQUFqQyxFQUFvQyxDQUFDLENBQXJDLEVBQXdDNEMsS0FBeEMsQ0FBOEMsR0FBOUMsQ0FBYjtVQUNBLEtBQUsxQixjQUFMLEdBQXNCLE1BQXRCO1VBQ0EsS0FBS25DLEtBQUwsR0FBYTtZQUNYc0MsQ0FBQyxFQUFFcUIsTUFBTSxDQUFDLENBQUQsQ0FERTtZQUVYcEIsQ0FBQyxFQUFFb0IsTUFBTSxDQUFDLENBQUQsQ0FGRTtZQUdYbkIsQ0FBQyxFQUFFbUIsTUFBTSxDQUFDLENBQUQsQ0FIRTtZQUlYbEIsQ0FBQyxFQUFFa0IsTUFBTSxDQUFDLENBQUQsQ0FKRTtZQUtYdEIsSUFBSSxFQUFFO2NBQ0pDLENBQUMsRUFBRXFCLE1BQU0sQ0FBQyxDQUFELENBREw7Y0FFSnBCLENBQUMsRUFBRW9CLE1BQU0sQ0FBQyxDQUFELENBRkw7Y0FHSm5CLENBQUMsRUFBRW1CLE1BQU0sQ0FBQyxDQUFELENBSEw7Y0FJSmxCLENBQUMsRUFBRWtCLE1BQU0sQ0FBQyxDQUFEO1lBSkw7VUFMSyxDQUFiO1VBWUEsS0FBS0csS0FBTCxDQUFXQyxXQUFYLENBQXVCQyxXQUF2QixHQUFxQyxDQUFyQztRQUNELENBaEJELE1BZ0JPLElBQUloRSxLQUFLLENBQUMwRCxPQUFOLENBQWMsS0FBZCxNQUF5QixDQUFDLENBQTlCLEVBQWlDO1VBQ3RDLElBQUlDLE1BQU0sR0FBRzNELEtBQUssQ0FBQzRELE9BQU4sQ0FBYyxPQUFkLEVBQXVCLEVBQXZCLEVBQTJCM0MsS0FBM0IsQ0FBaUMsQ0FBakMsRUFBb0MsQ0FBQyxDQUFyQyxFQUF3QzRDLEtBQXhDLENBQThDLEdBQTlDLENBQWI7VUFDQSxLQUFLMUIsY0FBTCxHQUFzQixLQUF0QjtVQUNBLEtBQUtuQyxLQUFMLEdBQWE7WUFDWDBDLEdBQUcsRUFBRTtjQUNIQyxDQUFDLEVBQUVnQixNQUFNLENBQUMsQ0FBRCxDQUROO2NBRUg5RCxDQUFDLEVBQUU4RCxNQUFNLENBQUMsQ0FBRCxDQUFOLENBQVVDLE9BQVYsQ0FBa0IsR0FBbEIsRUFBdUIsRUFBdkIsSUFBNkIsR0FGN0I7Y0FHSGhCLENBQUMsRUFBRWUsTUFBTSxDQUFDLENBQUQsQ0FBTixDQUFVQyxPQUFWLENBQWtCLEdBQWxCLEVBQXVCLEVBQXZCLElBQTZCLEdBSDdCO2NBSUhuQixDQUFDLEVBQUVrQixNQUFNLENBQUMsQ0FBRDtZQUpOO1VBRE0sQ0FBYjtVQVFBLEtBQUtHLEtBQUwsQ0FBV0MsV0FBWCxDQUF1QkMsV0FBdkIsR0FBcUMsQ0FBckM7UUFDRCxDQVpNLE1BWUEsSUFBSWhFLEtBQUssQ0FBQzBELE9BQU4sQ0FBYyxHQUFkLE1BQXVCLENBQUMsQ0FBNUIsRUFBK0I7VUFDcEMsS0FBS3ZCLGNBQUwsR0FBc0IsS0FBdEI7VUFDQSxLQUFLbkMsS0FBTCxHQUFhO1lBQ1hvQyxHQUFHLEVBQUVwQztVQURNLENBQWI7VUFHQSxLQUFLOEQsS0FBTCxDQUFXQyxXQUFYLENBQXVCQyxXQUF2QixHQUFxQyxDQUFyQztRQUNEOztRQUVELEtBQUsvQixXQUFMLEdBQW1CakMsS0FBbkI7TUFDRDtJQUNGLENBOUNNO0lBK0NQaUUsY0FBYyxFQUFFLFNBQVNBLGNBQVQsQ0FBd0JqRSxLQUF4QixFQUErQjtNQUM3QyxJQUFJa0UsTUFBTSxHQUFHLEtBQWI7O01BRUEsSUFBSSxPQUFPbEUsS0FBUCxLQUFpQixRQUFyQixFQUErQjtRQUM3QixJQUFJQSxLQUFLLENBQUMwRCxPQUFOLENBQWMsS0FBZCxNQUF5QixDQUFDLENBQTlCLEVBQWlDO1VBQy9CUSxNQUFNLEdBQUcsTUFBVDtRQUNELENBRkQsTUFFTyxJQUFJbEUsS0FBSyxDQUFDMEQsT0FBTixDQUFjLEtBQWQsTUFBeUIsQ0FBQyxDQUE5QixFQUFpQztVQUN0Q1EsTUFBTSxHQUFHLEtBQVQ7UUFDRCxDQUZNLE1BRUEsSUFBSWxFLEtBQUssQ0FBQzBELE9BQU4sQ0FBYyxHQUFkLE1BQXVCLENBQUMsQ0FBNUIsRUFBK0I7VUFDcENRLE1BQU0sR0FBRyxLQUFUO1FBQ0Q7TUFDRjs7TUFFRCxPQUFPQSxNQUFQO0lBQ0QsQ0E3RE07SUE4RFBWLGdCQUFnQixFQUFFLFNBQVNBLGdCQUFULENBQTBCeEQsS0FBMUIsRUFBaUM7TUFDakQsS0FBS21FLElBQUwsQ0FBVSxJQUFWLEVBQWdCLGFBQWhCLEVBQStCbkUsS0FBL0I7TUFDQSxLQUFLb0UsS0FBTCxDQUFXLGtCQUFYLEVBQStCcEUsS0FBL0I7SUFDRCxDQWpFTTtJQWtFUHFFLGlCQUFpQixFQUFFLFNBQVNBLGlCQUFULENBQTJCZixLQUEzQixFQUFrQztNQUNuRCxJQUFJQSxLQUFLLENBQUNnQixNQUFOLENBQWFDLFNBQWIsQ0FBdUJDLFFBQXZCLENBQWdDLHVCQUFoQyxLQUE0RGxCLEtBQUssQ0FBQ2dCLE1BQU4sQ0FBYUcsT0FBYixDQUFxQix3QkFBckIsQ0FBaEUsRUFBZ0g7UUFDOUcsSUFBSUMsT0FBTyxHQUFHcEIsS0FBSyxDQUFDZ0IsTUFBTixDQUFhRyxPQUFiLENBQXFCLHdCQUFyQixDQUFkOztRQUVBLElBQUlDLE9BQUosRUFBYTtVQUNYLElBQUk1QixNQUFNLEdBQUc0QixPQUFPLENBQUNDLGdCQUFSLENBQXlCLG1CQUF6QixDQUFiOztVQUVBLElBQUlDLFNBQVMsR0FBRzNGLDBCQUEwQixDQUFDNkQsTUFBRCxDQUExQztVQUFBLElBQ0krQixLQURKOztVQUdBLElBQUk7WUFDRixLQUFLRCxTQUFTLENBQUMvRSxDQUFWLEVBQUwsRUFBb0IsQ0FBQyxDQUFDZ0YsS0FBSyxHQUFHRCxTQUFTLENBQUM5RSxDQUFWLEVBQVQsRUFBd0JDLElBQTdDLEdBQW9EO2NBQ2xELElBQUkrRSxLQUFLLEdBQUdELEtBQUssQ0FBQzdFLEtBQWxCOztjQUVBLElBQUk4RSxLQUFLLENBQUNDLEtBQU4sQ0FBWUMsT0FBWixLQUF3QixNQUE1QixFQUFvQztnQkFDbEMsSUFBSWQsTUFBTSxHQUFHWSxLQUFLLENBQUNHLGFBQU4sQ0FBb0Isa0JBQXBCLEVBQXdDQyxXQUF4QyxDQUFvREMsV0FBcEQsR0FBa0VDLElBQWxFLEVBQWI7Z0JBQ0EsSUFBSUMsVUFBVSxHQUFHLEVBQWpCOztnQkFFQSxRQUFRbkIsTUFBUjtrQkFDRSxLQUFLLEtBQUw7b0JBQ0UsS0FBSy9CLGNBQUwsR0FBc0IsS0FBdEI7b0JBQ0FrRCxVQUFVLEdBQUdQLEtBQUssQ0FBQ0csYUFBTixDQUFvQixrQkFBcEIsRUFBd0NLLFlBQXhDLENBQXFELFlBQXJELENBQWI7b0JBQ0E7O2tCQUVGLEtBQUssR0FBTDtvQkFDRSxJQUFJakQsSUFBSSxHQUFHeUMsS0FBSyxDQUFDSCxnQkFBTixDQUF1QixrQkFBdkIsQ0FBWDtvQkFDQSxLQUFLeEMsY0FBTCxHQUFzQixNQUF0QjtvQkFDQWtELFVBQVUsR0FBRyxVQUFVaEQsSUFBSSxDQUFDLENBQUQsQ0FBSixDQUFRaUQsWUFBUixDQUFxQixZQUFyQixDQUFWLEdBQStDLEdBQS9DLEdBQXFEakQsSUFBSSxDQUFDLENBQUQsQ0FBSixDQUFRaUQsWUFBUixDQUFxQixZQUFyQixDQUFyRCxHQUEwRixHQUExRixHQUFnR2pELElBQUksQ0FBQyxDQUFELENBQUosQ0FBUWlELFlBQVIsQ0FBcUIsWUFBckIsQ0FBaEcsR0FBcUksR0FBckksR0FBMklqRCxJQUFJLENBQUMsQ0FBRCxDQUFKLENBQVFpRCxZQUFSLENBQXFCLFlBQXJCLENBQTNJLEdBQWdMLEdBQTdMO29CQUNBOztrQkFFRixLQUFLLEdBQUw7b0JBQ0UsSUFBSUMsSUFBSSxHQUFHVCxLQUFLLENBQUNILGdCQUFOLENBQXVCLGtCQUF2QixDQUFYO29CQUNBLEtBQUt4QyxjQUFMLEdBQXNCLE1BQXRCO29CQUNBa0QsVUFBVSxHQUFHLFVBQVVFLElBQUksQ0FBQyxDQUFELENBQUosQ0FBUUQsWUFBUixDQUFxQixZQUFyQixDQUFWLEdBQStDLEdBQS9DLEdBQXFEQyxJQUFJLENBQUMsQ0FBRCxDQUFKLENBQVFELFlBQVIsQ0FBcUIsWUFBckIsQ0FBckQsR0FBMEYsR0FBMUYsR0FBZ0dDLElBQUksQ0FBQyxDQUFELENBQUosQ0FBUUQsWUFBUixDQUFxQixZQUFyQixDQUFoRyxHQUFxSSxHQUFySSxHQUEySUMsSUFBSSxDQUFDLENBQUQsQ0FBSixDQUFRRCxZQUFSLENBQXFCLFlBQXJCLENBQTNJLEdBQWdMLEdBQTdMO29CQUNBO2dCQWhCSjs7Z0JBbUJBLEtBQUs5QixnQkFBTCxDQUFzQjZCLFVBQXRCO2dCQUNBO2NBQ0Q7WUFDRjtVQUNGLENBL0JELENBK0JFLE9BQU85RSxHQUFQLEVBQVk7WUFDWnFFLFNBQVMsQ0FBQzNFLENBQVYsQ0FBWU0sR0FBWjtVQUNELENBakNELFNBaUNVO1lBQ1JxRSxTQUFTLENBQUN6RSxDQUFWO1VBQ0Q7UUFDRjtNQUNGO0lBQ0YsQ0FsSE07SUFtSFBxRixTQUFTLEVBQUUsU0FBU0EsU0FBVCxDQUFtQnBELEdBQW5CLEVBQXdCO01BQ2pDLElBQUlxRCxDQUFKO01BQ0FyRCxHQUFHLEdBQUdBLEdBQUcsQ0FBQ2dELElBQUosRUFBTjs7TUFFQSxJQUFJLDJCQUEyQi9ELElBQTNCLENBQWdDZSxHQUFoQyxDQUFKLEVBQTBDO1FBQ3hDcUQsQ0FBQyxHQUFHckQsR0FBRyxDQUFDc0QsU0FBSixDQUFjLENBQWQsRUFBaUI3QixLQUFqQixDQUF1QixFQUF2QixDQUFKOztRQUVBLElBQUk0QixDQUFDLENBQUMvRixNQUFGLEtBQWEsQ0FBakIsRUFBb0I7VUFDbEIrRixDQUFDLEdBQUcsQ0FBQ0EsQ0FBQyxDQUFDLENBQUQsQ0FBRixFQUFPQSxDQUFDLENBQUMsQ0FBRCxDQUFSLEVBQWFBLENBQUMsQ0FBQyxDQUFELENBQWQsRUFBbUJBLENBQUMsQ0FBQyxDQUFELENBQXBCLEVBQXlCQSxDQUFDLENBQUMsQ0FBRCxDQUExQixFQUErQkEsQ0FBQyxDQUFDLENBQUQsQ0FBaEMsQ0FBSjtRQUNEOztRQUVELElBQUluRCxDQUFDLEdBQUdxRCxRQUFRLENBQUNGLENBQUMsQ0FBQyxDQUFELENBQUQsR0FBT0EsQ0FBQyxDQUFDLENBQUQsQ0FBVCxFQUFjLEVBQWQsQ0FBaEI7UUFDQSxJQUFJbEQsQ0FBQyxHQUFHb0QsUUFBUSxDQUFDRixDQUFDLENBQUMsQ0FBRCxDQUFELEdBQU9BLENBQUMsQ0FBQyxDQUFELENBQVQsRUFBYyxFQUFkLENBQWhCO1FBQ0EsSUFBSWpELENBQUMsR0FBR21ELFFBQVEsQ0FBQ0YsQ0FBQyxDQUFDLENBQUQsQ0FBRCxHQUFPQSxDQUFDLENBQUMsQ0FBRCxDQUFULEVBQWMsRUFBZCxDQUFoQjtRQUNBLE9BQU87VUFDTG5ELENBQUMsRUFBRUEsQ0FERTtVQUVMQyxDQUFDLEVBQUVBLENBRkU7VUFHTEMsQ0FBQyxFQUFFQSxDQUhFO1VBSUxDLENBQUMsRUFBRTtRQUpFLENBQVA7TUFNRDs7TUFFRCxPQUFPLElBQVA7SUFDRDtFQTFJTSxDQXhDbUI7RUFvTDVCbUQsS0FBSyxFQUFFO0lBQ0wzRCxXQUFXLEVBQUUsU0FBU0EsV0FBVCxDQUFxQmpDLEtBQXJCLEVBQTRCO01BQ3ZDLElBQUk2RixNQUFNLEdBQUcsSUFBYjs7TUFFQSxJQUFJM0IsTUFBTSxHQUFHLEtBQUtBLE1BQWxCOztNQUVBLElBQUlBLE1BQU0sS0FBSyxNQUFYLElBQXFCLE9BQU9sRSxLQUFQLEtBQWlCLFFBQXRDLElBQWtEQSxLQUFLLENBQUM4RixVQUFOLENBQWlCLEdBQWpCLENBQXRELEVBQTZFO1FBQzNFLElBQUl6RCxJQUFJLEdBQUcsS0FBS21ELFNBQUwsQ0FBZXhGLEtBQWYsQ0FBWDs7UUFFQSxJQUFJcUMsSUFBSixFQUFVO1VBQ1IsSUFBSTBELE9BQU8sR0FBRyxRQUFRQyxNQUFSLENBQWUzRCxJQUFJLENBQUNDLENBQXBCLEVBQXVCLEdBQXZCLEVBQTRCMEQsTUFBNUIsQ0FBbUMzRCxJQUFJLENBQUNFLENBQXhDLEVBQTJDLEdBQTNDLEVBQWdEeUQsTUFBaEQsQ0FBdUQzRCxJQUFJLENBQUNHLENBQTVELEVBQStELEdBQS9ELEVBQW9Fd0QsTUFBcEUsQ0FBMkUzRCxJQUFJLENBQUNJLENBQWhGLEVBQW1GLEdBQW5GLENBQWQ7VUFDQSxLQUFLUSxTQUFMLENBQWUsWUFBWTtZQUN6QjRDLE1BQU0sQ0FBQzVELFdBQVAsR0FBcUI4RCxPQUFyQjs7WUFFQUYsTUFBTSxDQUFDekIsS0FBUCxDQUFhLGtCQUFiLEVBQWlDMkIsT0FBakM7VUFDRCxDQUpEO1VBS0E7UUFDRDtNQUNGOztNQUVELEtBQUszQixLQUFMLENBQVcsa0JBQVgsRUFBK0JwRSxLQUEvQjtJQUNELENBckJJO0lBc0JMQSxLQUFLLEVBQUUsU0FBU0EsS0FBVCxDQUFlaUcsTUFBZixFQUF1QjtNQUM1QixJQUFJQSxNQUFNLENBQUM1RCxJQUFQLElBQWU0RCxNQUFNLENBQUM1RCxJQUFQLENBQVlJLENBQVosS0FBa0J5RCxTQUFqQyxJQUE4Q0QsTUFBTSxDQUFDNUQsSUFBUCxDQUFZSSxDQUFaLEdBQWdCLENBQTlELElBQW1FLEtBQUtOLGNBQUwsS0FBd0IsS0FBL0YsRUFBc0c7UUFDcEcsS0FBS0EsY0FBTCxHQUFzQixNQUF0QjtNQUNEOztNQUVELFFBQVEsS0FBS0EsY0FBYjtRQUNFLEtBQUssS0FBTDtVQUNFLEtBQUtxQixnQkFBTCxDQUFzQnlDLE1BQU0sQ0FBQzdELEdBQTdCO1VBQ0E7O1FBRUYsS0FBSyxNQUFMO1VBQ0UsS0FBS29CLGdCQUFMLENBQXNCLFVBQVV5QyxNQUFNLENBQUM1RCxJQUFQLENBQVlDLENBQXRCLEdBQTBCLEdBQTFCLEdBQWdDMkQsTUFBTSxDQUFDNUQsSUFBUCxDQUFZRSxDQUE1QyxHQUFnRCxHQUFoRCxHQUFzRDBELE1BQU0sQ0FBQzVELElBQVAsQ0FBWUcsQ0FBbEUsR0FBc0UsR0FBdEUsR0FBNEV5RCxNQUFNLENBQUM1RCxJQUFQLENBQVlJLENBQXhGLEdBQTRGLEdBQWxIO1VBQ0E7O1FBRUYsS0FBSyxLQUFMO1VBQ0UsS0FBS2UsZ0JBQUwsQ0FBc0IsVUFBVTJDLElBQUksQ0FBQ0MsSUFBTCxDQUFVSCxNQUFNLENBQUN2RCxHQUFQLENBQVdDLENBQXJCLENBQVYsR0FBb0MsR0FBcEMsR0FBMENzRCxNQUFNLENBQUN2RCxHQUFQLENBQVc3QyxDQUFYLEdBQWUsR0FBekQsR0FBK0QsSUFBL0QsR0FBc0VvRyxNQUFNLENBQUN2RCxHQUFQLENBQVdFLENBQVgsR0FBZSxHQUFyRixHQUEyRixJQUEzRixHQUFrR3FELE1BQU0sQ0FBQ3ZELEdBQVAsQ0FBV0QsQ0FBN0csR0FBaUgsR0FBdkk7VUFDQTtNQVhKO0lBYUQ7RUF4Q0k7QUFwTHFCLENBQTlCIn0=
},{}]},{},[1])