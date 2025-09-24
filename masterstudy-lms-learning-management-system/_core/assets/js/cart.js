"use strict";

(function ($) {
  $(document).ready(function () {
    new Vue({
      el: '#stm_lms_checkout',
      data: function data() {
        return {
          loading: false,
          message: '',
          status: 'error',
          payment_code: '',
          stripe: '',
          stripe_card: '',
          stripe_complete: false,
          agree_with_policy: false
        };
      },
      mounted: function mounted() {
        var gdpr = $('.masterstudy-checkout-gdpr');
        if (!gdpr.length) {
          this.agree_with_policy = true;
        }
      },
      methods: {
        toggle_policy: function toggle_policy() {
          this.agree_with_policy = !this.agree_with_policy;
        },
        purchase_courses: function purchase_courses() {
          if (this.loading || !this.agree_with_policy) return false;
          var vm = this;
          vm.loading = true;
          function handleResponse(response) {
            vm.status = response.body.status;
            vm.message = response.body.message;
            var data = {
              event_type: "order_created",
              payment_code: vm.payment_code,
              url: response.body.url || ""
            };
            stm_lms_print_message(data);
            if (response.body.url) {
              window.location = response.body.url;
            } else {
              vm.loading = false;
            }
          }
          function handleError(error) {
            vm.loading = false;
            vm.message = error.message || 'An error occurred';
            vm.status = 'error';
          }
          if (vm.payment_code === 'stripe') {
            vm.stripe.createToken(vm.stripe_card).then(function (result) {
              if (result.error) {
                handleError(result.error);
              } else {
                vm.$http.get(stm_lms_ajaxurl + '?action=stm_lms_purchase&nonce=' + stm_lms_nonces['stm_lms_purchase'] + '&payment_code=' + vm.payment_code + '&token_id=' + result.token.id).then(handleResponse)["catch"](handleError);
              }
            });
          } else {
            vm.$http.get(stm_lms_ajaxurl + '?action=stm_lms_purchase&nonce=' + stm_lms_nonces['stm_lms_purchase'] + '&payment_code=' + vm.payment_code).then(handleResponse)["catch"](handleError);
          }
        },
        generateStripe: function generateStripe() {
          var vm = this;
          Vue.nextTick(function () {
            vm.stripe = Stripe(stripe_id);
            var elements = vm.stripe.elements();
            vm.stripe_card = elements.create('card');
            vm.stripe_card.mount('#stm-lms-stripe');
            vm.stripe_card.addEventListener('change', function (event) {
              vm.stripe_complete = event.complete;
            });
          });
        }
      },
      watch: {
        payment_code: function payment_code(value) {
          if (value === 'stripe') {
            this.generateStripe();
          }
        }
      }
    });
  });
})(jQuery);