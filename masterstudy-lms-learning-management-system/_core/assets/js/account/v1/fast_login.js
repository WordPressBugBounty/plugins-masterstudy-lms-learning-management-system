"use strict";

(function ($) {
  /**
   * @var stm_lms_fast_login
   */
  $(document).ready(function () {
    new Vue({
      el: '#stm_lms_fast_login',
      data: function data() {
        return {
          loading: false,
          login: false,
          translations: stm_lms_fast_login['translations'],
          restrict_registration: stm_lms_fast_login['restrict_registration'],
          email: '',
          password: '',
          errors: [],
          status: ''
        };
      },
      computed: {
        passwordStrength: function passwordStrength() {
          return this.getPasswordStrength(this.password);
        },
        passwordStrengthText: function passwordStrengthText() {
          var _masterstudy_authoriz, _masterstudy_authoriz2, _masterstudy_authoriz3, _masterstudy_authoriz4;
          var strengthLevels = {
            1: ((_masterstudy_authoriz = masterstudy_authorization_data) === null || _masterstudy_authoriz === void 0 ? void 0 : _masterstudy_authoriz.bad) || "Bad",
            2: ((_masterstudy_authoriz2 = masterstudy_authorization_data) === null || _masterstudy_authoriz2 === void 0 ? void 0 : _masterstudy_authoriz2.normal) || "Normal",
            3: ((_masterstudy_authoriz3 = masterstudy_authorization_data) === null || _masterstudy_authoriz3 === void 0 ? void 0 : _masterstudy_authoriz3.good) || "Good",
            4: ((_masterstudy_authoriz4 = masterstudy_authorization_data) === null || _masterstudy_authoriz4 === void 0 ? void 0 : _masterstudy_authoriz4.hard) || "Hard"
          };
          return strengthLevels[this.passwordStrength] || "";
        },
        passwordStrengthClass: function passwordStrengthClass() {
          return {
            'bad': this.passwordStrength === 1,
            'normal': this.passwordStrength === 2,
            'good': this.passwordStrength === 3,
            'hard': this.passwordStrength === 4
          };
        }
      },
      methods: {
        logIn: function logIn() {
          var vm = this;
          vm.loading = true;
          vm.$http.post(stm_lms_ajaxurl + '?action=stm_lms_fast_login&nonce=' + stm_lms_nonces['stm_lms_fast_login'], {
            user_login: vm.email,
            user_password: vm.password
          }).then(function (response) {
            vm.errors = response.body['errors'];
            vm.status = response.body['status'];
            vm.loading = false;
            if (vm.status !== 'error') {
              $.removeCookie('stm_lms_notauth_cart', {
                path: '/'
              });
              location.reload();
            }
          });
        },
        register: function register() {
          var vm = this;
          vm.loading = true;
          vm.$http.post(stm_lms_ajaxurl + '?action=stm_lms_fast_register&nonce=' + stm_lms_nonces['stm_lms_fast_register'], {
            email: vm.email,
            password: vm.password
          }).then(function (response) {
            vm.errors = response.body['errors'];
            vm.status = response.body['status'];
            vm.loading = false;
            if (vm.status !== 'error') {
              $.removeCookie('stm_lms_notauth_cart', {
                path: '/'
              });
              location.reload();
            }
          });
        },
        showPass: function showPass(event) {
          event.currentTarget.classList.toggle('stm_lms_fast_login__input-show-pass_open');
          var parent = event.currentTarget.closest('.stm_lms_fast_login__field');
          var field = parent ? parent.querySelector('input') : null;
          if (field && field.tagName === 'INPUT') {
            field.type = field.type === 'password' ? 'text' : 'password';
          }
        },
        changeForm: function changeForm(login) {
          this.login = login;
          this.email = '';
          this.password = '';
          this.errors = [];
        },
        hasError: function hasError(fieldName) {
          return this.errors.some(function (error) {
            return error.field === fieldName;
          });
        },
        getPasswordStrength: function getPasswordStrength(password) {
          if (!password) return 0;
          var length = password.length;
          var hasLower = /[a-z]/.test(password);
          var hasUpper = /[A-Z]/.test(password);
          var hasNumber = /[0-9]/.test(password);
          if (length >= 8 && length <= 11 && hasLower && hasUpper && hasNumber) {
            return 2;
          }
          if (length >= 12 && length <= 15 && hasLower && hasUpper && hasNumber) {
            return 3;
          }
          if (length >= 16 && hasLower && hasUpper && hasNumber) {
            return 4;
          }
          return 1;
        }
      }
    });
  });
})(jQuery);