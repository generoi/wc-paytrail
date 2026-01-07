jQuery(document).ready(function ($) {
  var wcPaytrailPpaSettings = {
    init: function () {
      this.addCheckApiCredentials();
      this.triggerCredentialCheck();
      this.toggleFields();
    },

    addCheckApiCredentials: function () {
      var form = $('label[for="woocommerce_paytrail_ppa_merchant_id"]').closest('form');

      if (form.length > 0) {
        var row = $('input[name="woocommerce_paytrail_ppa_merchant_key"]', form).closest('tr');

        var output = '<tr><th>' + wc_paytrail_settings.check_credentials + '</th><td><a href="#" id="wc-paytrail-ppa-check-credentials" class="button">' + wc_paytrail_settings.check_credentials + '</a></td></tr>';
        row.after(output);
      }
    },

    triggerCredentialCheck: function () {
      var self = this;

      $(document).on('click', 'a#wc-paytrail-ppa-check-credentials', function (e) {
        e.preventDefault();
        self.checkCredentials($(this).closest('form'));
      });
    },

    checkCredentials: function (form) {
      var self = this;
      var checkButton = $('a#wc-paytrail-ppa-check-credentials');

      var data = {
        'action': 'paytrail_ppa_check_api_credentials',
        'merchant_id': $('input#woocommerce_paytrail_ppa_merchant_id', form).val(),
        'merchant_secret': $('input#woocommerce_paytrail_ppa_merchant_key', form).val(),
        'transaction_settlements': $('input#woocommerce_paytrail_ppa_transaction_settlement_enable', form).is(':checked'),
      };

      this.showCheckProcessing(checkButton);

      jQuery.post(ajaxurl, data)
        .done(function (response) {
          if (response.status && response.status == 'ok') {
            self.showCheckSuccess(checkButton, wc_paytrail_settings.check_credentials_success);
          } else {
            self.showCheckFail(checkButton, response.error);
          }
        })
        .fail(function (response) {
          alert('Error checking API credentials');
        })
        .always(function (response) {
        });
    },

    showCheckProcessing: function (el) {
      $('span.wc-paytrail-throbber').remove();
      el.after('<span class="wc-paytrail-throbber processing"></span>');
    },

    showCheckSuccess: function (el, successMsg) {
      $('span.wc-paytrail-throbber').remove();
      el.after('<span class="wc-paytrail-throbber ok"><span class="dashicons dashicons-yes"></span>' + successMsg + '</span>');
    },

    showCheckFail: function (el, errorMsg) {
      $('span.wc-paytrail-throbber').remove();
      el.after('<span class="wc-paytrail-throbber error"><span class="dashicons dashicons-no"></span>' + errorMsg + '</span>');
    },

    toggleFields: function () {
      // Apple Pay settings
      $('select#woocommerce_paytrail_ppa_mode').change(function (e) {
        var isBypass = $(this).val() === 'bypass';

        $('#woocommerce_paytrail_ppa_apple_pay_title').toggle(isBypass);
        $('#woocommerce_paytrail_ppa_enable_apple_pay').closest('table').toggle(isBypass);
      }).trigger('change');

      // Apple Pay verification file
      $('input#woocommerce_paytrail_ppa_enable_apple_pay').change(function (e) {
        var isChecked = $(this).is(':checked');

        $('tr.wc-paytrail-ap-ver-file').toggle(isChecked);
      }).trigger('change');

      // Transaction specific settlement
      $('input#woocommerce_paytrail_ppa_transaction_settlement_enable').change(function (e) {
        $('.paytrail-ppa-settlement-field').closest('tr').toggle($(this).is(':checked'));
      }).trigger('change');

      // Invoice capture
      $('select#woocommerce_paytrail_ppa_invoice_capture').change(function (e) {
        $('select#woocommerce_paytrail_ppa_invoice_capture_initial_status').closest('tr').toggle($(this).val() !== '');
      }).trigger('change');
    }
  };

  wcPaytrailPpaSettings.init();

  /**
   * Invoice functions
   */
  var wcPaytrailInvoice = {
    init: function() {
      this.triggerCapture();
    },

    /**
     * Trigger capture
     */
    triggerCapture: function() {
      var self = this;

      $( document ).on( 'click', 'a#wc-paytrail-capture-order', function( e ) {
        e.preventDefault();

        self.capture( $( this ).data( 'url' ), $( this ).data( 'order-id') );
      } );
    },

    /**
     * Capture invoice via AJAX
     */
    capture: function( url, orderId ) {
      jQuery.ajax({
        url: url,
        data: {
          order_id: orderId
        },
        method: 'POST',
        beforeSend: function() {
          $( '.wc-paytrail-throbber' ).remove();
          $( '.wc-paytrail-error' ).remove();

          $( '.wc-paytrail-label' ).after( '<span class="wc-paytrail-throbber processing"></span>' );
          $( '#wc-paytrail-capture-order' ).hide();
        },
        success: function( response ) {
          $( '.wc-paytrail-throbber' ).remove();

          $( '.wc-paytrail-invoice-status' ).replaceWith( response.html );

          $( '.wc-paytrail-invoice-status .wc-paytrail-label' ).after( '<span class="wc-paytrail-throbber ok"><span class="dashicons dashicons-yes"></span></span>' );
        },
        error: function( response ) {
          $( '.wc-paytrail-throbber' ).remove();

          $( '#wc-paytrail-capture-order' ).show();

          $( '#wc-paytrail-capture-order' ).after( '<span class="wc-paytrail-error"><span class="dashicons dashicons-no"></span>' + response.responseJSON.msg + '</span>' );
        },
        complete: function() {
        }
      });
    }
  };
  wcPaytrailInvoice.init();
});
