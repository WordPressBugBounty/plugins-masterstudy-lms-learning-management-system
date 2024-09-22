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
        }
      }
    });
  });
})(jQuery);