<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2020 osCommerce

  Released under the GNU General Public License
*/

  if ( !class_exists('OSCOM_PayPal') ) {
    include(DIR_FS_CATALOG . 'includes/apps/paypal/OSCOM_PayPal.php');
  }

  class paypal_express {

    public $code = 'paypal_express';
    public $title, $description, $enabled, $_app;

    function __construct() {
      global $PHP_SELF, $oscTemplate, $order, $payment, $request_type;

      $this->_app = new OSCOM_PayPal();
      $this->_app->loadLanguageFile('modules/EC/EC.php');

      $this->signature = 'paypal|paypal_express|' . $this->_app->getVersion() . '|2.3';
      $this->api_version = $this->_app->getApiVersion();

      $this->title = $this->_app->getDef('module_ec_title');
      $this->public_title = $this->_app->getDef('module_ec_public_title');
      $this->description = '<div align="center">' . $this->_app->drawButton($this->_app->getDef('module_ec_legacy_admin_app_button'), tep_href_link('paypal.php', 'action=configure&module=EC'), 'primary', null, true) . '</div>';
      $this->sort_order = defined('OSCOM_APP_PAYPAL_EC_SORT_ORDER') ? OSCOM_APP_PAYPAL_EC_SORT_ORDER : 0;
      $this->enabled = defined('OSCOM_APP_PAYPAL_EC_STATUS') && in_array(OSCOM_APP_PAYPAL_EC_STATUS, ['1', '0']);
      $this->order_status = defined('OSCOM_APP_PAYPAL_EC_ORDER_STATUS_ID') && ((int)OSCOM_APP_PAYPAL_EC_ORDER_STATUS_ID > 0) ? (int)OSCOM_APP_PAYPAL_EC_ORDER_STATUS_ID : 0;

      if ( defined('OSCOM_APP_PAYPAL_EC_STATUS') ) {
        if ( OSCOM_APP_PAYPAL_EC_STATUS == '0' ) {
          $this->title .= ' [Sandbox]';
          $this->public_title .= ' (' . $this->code . '; Sandbox)';
        }
      }

      if ( !function_exists('curl_init') ) {
        $this->description .= '<div class="secWarning">' . $this->_app->getDef('module_ec_error_curl') . '</div>';

        $this->enabled = false;
      }

      if ( $this->enabled === true ) {
        if ( OSCOM_APP_PAYPAL_GATEWAY == '1' ) { // PayPal
          if ( !$this->_app->hasCredentials('EC') ) {
            $this->description .= '<div class="secWarning">' . $this->_app->getDef('module_ec_error_credentials') . '</div>';

            $this->enabled = false;
          }
        } else { // Payflow
          if ( !$this->_app->hasCredentials('EC', 'payflow') ) {
            $this->description .= '<div class="secWarning">' . $this->_app->getDef('module_ec_error_credentials_payflow') . '</div>';

            $this->enabled = false;
          }
        }
      }

      if ( $this->enabled === true ) {
        if ( isset($order) && is_object($order) ) {
          $this->update_status();
        }
      }

      if ( (basename($PHP_SELF) == 'shopping_cart.php') ) {
        if ( (OSCOM_APP_PAYPAL_GATEWAY == '1') && (OSCOM_APP_PAYPAL_EC_CHECKOUT_FLOW == '1') ) {
          if ( isset($request_type) && ($request_type != 'SSL') && (ENABLE_SSL == true) ) {
            tep_redirect(tep_href_link('shopping_cart.php', tep_get_all_get_params(), 'SSL'));
          }

          header('X-UA-Compatible: IE=edge', true);
        }
      }

// When changing the shipping address due to no shipping rates being available, head straight to the checkout confirmation page
      if ((basename($PHP_SELF) == 'checkout_payment.php') && tep_session_is_registered('appPayPalEcRightTurn') ) {
        tep_session_unregister('appPayPalEcRightTurn');

        if ( tep_session_is_registered('payment') && ($payment == $this->code) ) {
          tep_redirect(tep_href_link('checkout_confirmation.php', '', 'SSL'));
        }
      }

      if ( $this->enabled === true ) {
        if (basename($PHP_SELF) == 'shopping_cart.php') {
          if ( $this->templateClassExists() ) {
            if ( (OSCOM_APP_PAYPAL_GATEWAY == '1') && (OSCOM_APP_PAYPAL_EC_CHECKOUT_FLOW == '1') ) {
              $oscTemplate->addBlock('<style>#ppECButton { display: inline-block; }</style>', 'header_tags');
            }

            if ( file_exists(DIR_FS_CATALOG . 'ext/modules/payment/paypal/express.css') ) {
              $oscTemplate->addBlock('<link rel="stylesheet" type="text/css" href="ext/modules/payment/paypal/express.css" />', 'header_tags');
            }
          }
        }
      }
    }

    function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)OSCOM_APP_PAYPAL_EC_ZONE > 0) ) {
        $check_query = tep_db_query("SELECT zone_id FROM zones_to_geo_zones WHERE geo_zone_id = '" . OSCOM_APP_PAYPAL_EC_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if (($check['zone_id'] < 1) || ($check['zone_id'] == $order->delivery['zone_id'])) {
            return;
          }
        }

        $this->enabled = false;
      }
    }

    function checkout_initialization_method() {
      global $cart;

      $string = '';

      if (OSCOM_APP_PAYPAL_GATEWAY == '1') {
        if (OSCOM_APP_PAYPAL_EC_CHECKOUT_FLOW == '0') {
          if (OSCOM_APP_PAYPAL_EC_CHECKOUT_IMAGE == '1') {
            if (OSCOM_APP_PAYPAL_EC_STATUS == '1') {
              $image_button = 'https://fpdbs.paypal.com/dynamicimageweb?cmd=_dynamic-image';
            } else {
              $image_button = 'https://fpdbs.sandbox.paypal.com/dynamicimageweb?cmd=_dynamic-image';
            }

            $params = ['locale=' . $this->_app->getDef('module_ec_button_locale')];

            if ( $this->_app->hasCredentials('EC') ) {
              $response_array = $this->_app->getApiResult('EC', 'GetPalDetails');

              if ( isset($response_array['PAL']) ) {
                $params[] = 'pal=' . $response_array['PAL'];
                $params[] = 'ordertotal=' . $this->_app->formatCurrencyRaw($cart->show_total());
              }
            }

            if ( !empty($params) ) {
              $image_button .= '&' . implode('&', $params);
            }
          } else {
            $image_button = $this->_app->getDef('module_ec_button_url');
          }

          $button_title = tep_output_string_protected($this->_app->getDef('module_ec_button_title'));

          if ( OSCOM_APP_PAYPAL_EC_STATUS == '0' ) {
            $button_title .= ' (' . $this->code . '; Sandbox)';
          }

          $string .= '<a id="ppECButtonClassicLink" href="' . tep_href_link('ext/modules/payment/paypal/express.php', '', 'SSL') . '"><img id="ppECButtonClassic" src="' . $image_button . '" border="0" alt="" title="' . $button_title . '" /></a>';
        } else {
          $string .= '<script src="https://www.paypalobjects.com/api/checkout.js"></script>';

          $merchant_id = (OSCOM_APP_PAYPAL_EC_STATUS === '1') ? OSCOM_APP_PAYPAL_LIVE_MERCHANT_ID : OSCOM_APP_PAYPAL_SANDBOX_MERCHANT_ID;
          if (empty($merchant_id)) $merchant_id = ' ';

          $server = (OSCOM_APP_PAYPAL_EC_STATUS === '1') ? 'production' : 'sandbox';

          $ppecset_url = tep_href_link('ext/modules/payment/paypal/express.php', 'format=json', 'SSL');

          $ppecerror_url = tep_href_link('ext/modules/payment/paypal/express.php', 'osC_Action=setECError', 'SSL');

          switch (OSCOM_APP_PAYPAL_EC_INCONTEXT_BUTTON_COLOR) {
            case '3':
              $button_color = 'silver';
              break;

            case '2':
              $button_color = 'blue';
              break;

            default:
            case '1':
              $button_color = 'gold';
              break;
          }

          switch (OSCOM_APP_PAYPAL_EC_INCONTEXT_BUTTON_SIZE) {
            case '3':
              $button_size = 'medium';
              break;

            default:
            case '2':
              $button_size = 'small';
              break;

            case '1':
              $button_size = 'tiny';
              break;
          }

          switch (OSCOM_APP_PAYPAL_EC_INCONTEXT_BUTTON_SHAPE) {
            case '2':
              $button_shape = 'rect';
              break;

            default:
            case '1':
              $button_shape = 'pill';
              break;
          }

          $string .= <<<EOD
<span id="ppECButton"></span>
<script>
paypal.Button.render({
  env: '{$server}',
  style: {
    size: '${button_size}',
    color: '${button_color}',
    shape: '${button_shape}'
  },
  payment: function(resolve, reject) {
    paypal.request.post('${ppecset_url}')
      .then(function(data) {
        if ((data.token !== undefined) && (data.token.length > 0)) {
          resolve(data.token);
        } else {
          window.location = '${ppecerror_url}';
        }
      })
      .catch(function(err) {
        reject(err);

        window.location = '${ppecerror_url}';
      });
  },
  onAuthorize: function(data, actions) {
    return actions.redirect();
  },
  onCancel: function(data, actions) {
    return actions.redirect();
  }
}, '#ppECButton');
</script>
EOD;
        }
      } else {
        $image_button = $this->_app->getDef('module_ec_button_url');

        $button_title = tep_output_string_protected($this->_app->getDef('module_ec_button_title'));

        if (OSCOM_APP_PAYPAL_EC_STATUS == '0') {
          $button_title .= ' (' . $this->code . '; Sandbox)';
        }

        $string .= '<a id="ppECButtonPfLink" href="' . tep_href_link('ext/modules/payment/paypal/express.php', '', 'SSL') . '"><img id="ppECButtonPf" src="' . $image_button . '" border="0" alt="" title="' . $button_title . '" /></a>';
      }

      return $string;
    }

    function javascript_validation() {
      return false;
    }

    function selection() {
      return [
        'id' => $this->code,
        'module' => $this->public_title
      ];
    }

    function pre_confirmation_check() {
      global $appPayPalEcResult, $appPayPalEcSecret, $order;

      if ( !tep_session_is_registered('appPayPalEcResult') ) {
        tep_redirect(tep_href_link('ext/modules/payment/paypal/express.php', '', 'SSL'));
      }

      if ( OSCOM_APP_PAYPAL_GATEWAY == '1' ) { // PayPal
        if ( !in_array($appPayPalEcResult['ACK'], ['Success', 'SuccessWithWarning']) ) {
          tep_redirect(tep_href_link('shopping_cart.php', 'error_message=' . stripslashes($appPayPalEcResult['L_LONGMESSAGE0']), 'SSL'));
        } elseif ( !tep_session_is_registered('appPayPalEcSecret') || ($appPayPalEcResult['PAYMENTREQUEST_0_CUSTOM'] != $appPayPalEcSecret) ) {
          tep_redirect(tep_href_link('shopping_cart.php', '', 'SSL'));
        }
      } else { // Payflow
        if ($appPayPalEcResult['RESULT'] != '0') {
          tep_redirect(tep_href_link('shopping_cart.php', 'error_message=' . urlencode($appPayPalEcResult['OSCOM_ERROR_MESSAGE']), 'SSL'));
        } elseif ( !tep_session_is_registered('appPayPalEcSecret') || ($appPayPalEcResult['CUSTOM'] != $appPayPalEcSecret) ) {
          tep_redirect(tep_href_link('shopping_cart.php', '', 'SSL'));
        }
      }

      $order->info['payment_method'] = '<img src="https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png" border="0" alt="PayPal Logo" style="padding: 3px;" />';
    }

    function confirmation() {
      global $comments;

      if (!isset($comments)) {
        $comments = null;
      }

      $confirmation = false;

      if (empty($comments)) {
        $confirmation = [
          'fields' => [ [
            'title' => $this->_app->getDef('module_ec_field_comments'),
            'field' => tep_draw_textarea_field('ppecomments', 'soft', '60', '5', $comments),
            ] ],
        ];
      }

      return $confirmation;
    }

    function process_button() {
      return false;
    }

    function before_process() {
      if ( OSCOM_APP_PAYPAL_GATEWAY == '1' ) {
        $this->before_process_paypal();
      } else {
        $this->before_process_payflow();
      }
    }

    function before_process_paypal() {
      global $customer_id, $order, $sendto, $appPayPalEcResult, $appPayPalEcSecret, $response_array, $comments;

      if ( !tep_session_is_registered('appPayPalEcResult') ) {
        tep_redirect(tep_href_link('ext/modules/payment/paypal/express.php', '', 'SSL'));
      }

      if ( in_array($appPayPalEcResult['ACK'], ['Success', 'SuccessWithWarning']) ) {
        if ( !tep_session_is_registered('appPayPalEcSecret') || ($appPayPalEcResult['PAYMENTREQUEST_0_CUSTOM'] != $appPayPalEcSecret) ) {
          tep_redirect(tep_href_link('shopping_cart.php', '', 'SSL'));
        }
      } else {
        tep_redirect(tep_href_link('shopping_cart.php', 'error_message=' . stripslashes($appPayPalEcResult['L_LONGMESSAGE0']), 'SSL'));
      }

      if (empty($comments)) {
        if (isset($_POST['ppecomments']) && tep_not_null($_POST['ppecomments'])) {
          $comments = tep_db_prepare_input($_POST['ppecomments']);

          $order->info['comments'] = $comments;
        }
      }

      $params = [
        'TOKEN' => $appPayPalEcResult['TOKEN'],
        'PAYERID' => $appPayPalEcResult['PAYERID'],
        'PAYMENTREQUEST_0_AMT' => $this->_app->formatCurrencyRaw($order->info['total']),
        'PAYMENTREQUEST_0_CURRENCYCODE' => $order->info['currency']
      ];

      if (is_numeric($sendto) && ($sendto > 0)) {
        $params['PAYMENTREQUEST_0_SHIPTONAME'] = $order->delivery['name'];
        $params['PAYMENTREQUEST_0_SHIPTOSTREET'] = $order->delivery['street_address'];
        $params['PAYMENTREQUEST_0_SHIPTOSTREET2'] = $order->delivery['suburb'];
        $params['PAYMENTREQUEST_0_SHIPTOCITY'] = $order->delivery['city'];
        $params['PAYMENTREQUEST_0_SHIPTOSTATE'] = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], $order->delivery['state']);
        $params['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'] = $order->delivery['country']['iso_code_2'];
        $params['PAYMENTREQUEST_0_SHIPTOZIP'] = $order->delivery['postcode'];
      }

      $response_array = $this->_app->getApiResult('EC', 'DoExpressCheckoutPayment', $params);

      if ( !in_array($response_array['ACK'], ['Success', 'SuccessWithWarning']) ) {
        if ( $response_array['L_ERRORCODE0'] == '10486' ) {
          if ( OSCOM_APP_PAYPAL_EC_STATUS == '1' ) {
            $paypal_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout';
          } else {
            $paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout';
          }

          $paypal_url .= '&token=' . $appPayPalEcResult['TOKEN'];

          tep_redirect($paypal_url);
        }

        tep_redirect(tep_href_link('shopping_cart.php', 'error_message=' . stripslashes($response_array['L_LONGMESSAGE0']), 'SSL'));
      }
    }

    function before_process_payflow() {
      global $customer_id, $order, $sendto, $appPayPalEcResult, $appPayPalEcSecret, $response_array, $comments;

      if ( !tep_session_is_registered('appPayPalEcResult') ) {
        tep_redirect(tep_href_link('ext/modules/payment/paypal/express.php', '', 'SSL'));
      }

      if ( $appPayPalEcResult['RESULT'] == '0' ) {
        if ( !tep_session_is_registered('appPayPalEcSecret') || ($appPayPalEcResult['CUSTOM'] != $appPayPalEcSecret) ) {
          tep_redirect(tep_href_link('shopping_cart.php', '', 'SSL'));
        }
      } else {
        tep_redirect(tep_href_link('shopping_cart.php', 'error_message=' . urlencode($appPayPalEcResult['OSCOM_ERROR_MESSAGE']), 'SSL'));
      }

      if ( empty($comments) ) {
        if ( isset($_POST['ppecomments']) && tep_not_null($_POST['ppecomments']) ) {
          $comments = tep_db_prepare_input($_POST['ppecomments']);

          $order->info['comments'] = $comments;
        }
      }

      $params = [
        'EMAIL' => $order->customer['email_address'],
        'TOKEN' => $appPayPalEcResult['TOKEN'],
        'PAYERID' => $appPayPalEcResult['PAYERID'],
        'AMT' => $this->_app->formatCurrencyRaw($order->info['total']),
        'CURRENCY' => $order->info['currency'],
      ];

      if ( is_numeric($sendto) && ($sendto > 0) ) {
        $params['SHIPTONAME'] = $order->delivery['name'];
        $params['SHIPTOSTREET'] = $order->delivery['street_address'];
        $params['SHIPTOSTREET2'] = $order->delivery['suburb'];
        $params['SHIPTOCITY'] = $order->delivery['city'];
        $params['SHIPTOSTATE'] = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], $order->delivery['state']);
        $params['SHIPTOCOUNTRY'] = $order->delivery['country']['iso_code_2'];
        $params['SHIPTOZIP'] = $order->delivery['postcode'];
      }

      $response_array = $this->_app->getApiResult('EC', 'PayflowDoExpressCheckoutPayment', $params);

      if ( $response_array['RESULT'] != '0' ) {
        tep_redirect(tep_href_link('shopping_cart.php', 'error_message=' . urlencode($response_array['OSCOM_ERROR_MESSAGE']), 'SSL'));
      }
    }

    function after_process() {
      if ( OSCOM_APP_PAYPAL_GATEWAY == '1' ) {
        $this->after_process_paypal();
      } else {
        $this->after_process_payflow();
      }
    }

    function after_process_paypal() {
      global $response_array, $order_id, $appPayPalEcResult;

      $pp_result = 'Transaction ID: ' . tep_output_string_protected($response_array['PAYMENTINFO_0_TRANSACTIONID']) . "\n" .
                   'Payer Status: ' . tep_output_string_protected($appPayPalEcResult['PAYERSTATUS']) . "\n" .
                   'Address Status: ' . tep_output_string_protected($appPayPalEcResult['ADDRESSSTATUS']) . "\n" .
                   'Payment Status: ' . tep_output_string_protected($response_array['PAYMENTINFO_0_PAYMENTSTATUS']) . "\n" .
                   'Payment Type: ' . tep_output_string_protected($response_array['PAYMENTINFO_0_PAYMENTTYPE']) . "\n" .
                   'Pending Reason: ' . tep_output_string_protected($response_array['PAYMENTINFO_0_PENDINGREASON']);

      $sql_data = [
        'orders_id' => $order_id,
        'orders_status_id' => OSCOM_APP_PAYPAL_TRANSACTIONS_ORDER_STATUS_ID,
        'date_added' => 'NOW()',
        'customer_notified' => '0',
        'comments' => $pp_result,
      ];

      tep_db_perform('orders_status_history', $sql_data);

      tep_session_unregister('appPayPalEcResult');
      tep_session_unregister('appPayPalEcSecret');
    }

    function after_process_payflow() {
      global $response_array, $order_id, $appPayPalEcResult;

      $pp_result = 'Transaction ID: ' . tep_output_string_protected($response_array['PNREF']) . "\n" .
                   'Gateway: Payflow' . "\n" .
                   'PayPal ID: ' . tep_output_string_protected($response_array['PPREF']) . "\n" .
                   'Payer Status: ' . tep_output_string_protected($appPayPalEcResult['PAYERSTATUS']) . "\n" .
                   'Address Status: ' . tep_output_string_protected($appPayPalEcResult['ADDRESSSTATUS']) . "\n" .
                   'Payment Status: ' . tep_output_string_protected($response_array['PENDINGREASON']) . "\n" .
                   'Payment Type: ' . tep_output_string_protected($response_array['PAYMENTTYPE']) . "\n" .
                   'Response: ' . tep_output_string_protected($response_array['RESPMSG']) . "\n";

      $sql_data = [
        'orders_id' => $order_id,
        'orders_status_id' => OSCOM_APP_PAYPAL_TRANSACTIONS_ORDER_STATUS_ID,
        'date_added' => 'NOW()',
        'customer_notified' => '0',
        'comments' => $pp_result,
      ];

      tep_db_perform('orders_status_history', $sql_data);

      tep_session_unregister('appPayPalEcResult');
      tep_session_unregister('appPayPalEcSecret');

// Manually call PayflowInquiry to retrieve more details about the transaction and to allow admin post-transaction actions
      $response = $this->_app->getApiResult('APP', 'PayflowInquiry', ['ORIGID' => $response_array['PNREF']]);

      if ( isset($response['RESULT']) && ($response['RESULT'] == '0') ) {
        $result = 'Transaction ID: ' . tep_output_string_protected($response['ORIGPNREF']) . "\n" .
                  'Gateway: Payflow' . "\n";

        $pending_reason = $response['TRANSSTATE'];
        $payment_status = null;

        switch ( $response['TRANSSTATE'] ) {
          case '3':
            $pending_reason = 'authorization';
            $payment_status = 'Pending';
            break;

          case '4':
            $pending_reason = 'other';
            $payment_status = 'In-Progress';
            break;

          case '6':
            $pending_reason = 'scheduled';
            $payment_status = 'Pending';
            break;

          case '8':
          case '9':
            $pending_reason = 'None';
            $payment_status = 'Completed';
            break;
        }

        if ( isset($payment_status) ) {
          $result .= 'Payment Status: ' . tep_output_string_protected($payment_status) . "\n";
        }

        $result .= 'Pending Reason: ' . tep_output_string_protected($pending_reason) . "\n";

        switch ( $response['AVSADDR'] ) {
          case 'Y':
            $result .= 'AVS Address: Match' . "\n";
            break;

          case 'N':
            $result .= 'AVS Address: No Match' . "\n";
            break;
        }

        switch ( $response['AVSZIP'] ) {
          case 'Y':
            $result .= 'AVS ZIP: Match' . "\n";
            break;

          case 'N':
            $result .= 'AVS ZIP: No Match' . "\n";
            break;
        }

        switch ( $response['IAVS'] ) {
          case 'Y':
            $result .= 'IAVS: International' . "\n";
            break;

          case 'N':
            $result .= 'IAVS: USA' . "\n";
            break;
        }

        switch ( $response['CVV2MATCH'] ) {
          case 'Y':
            $result .= 'CVV2: Match' . "\n";
            break;

          case 'N':
            $result .= 'CVV2: No Match' . "\n";
            break;
        }

        $sql_data = [
          'orders_id' => $order_id,
          'orders_status_id' => OSCOM_APP_PAYPAL_TRANSACTIONS_ORDER_STATUS_ID,
          'date_added' => 'NOW()',
          'customer_notified' => '0',
          'comments' => $result,
        ];

        tep_db_perform('orders_status_history', $sql_data);
      }
    }

    function get_error() {
      return false;
    }

    function check() {
      $check_query = tep_db_query("SELECT configuration_value FROM configuration WHERE configuration_key = 'OSCOM_APP_PAYPAL_EC_STATUS'");
      if ( tep_db_num_rows($check_query) ) {
        $check = tep_db_fetch_array($check_query);

        return tep_not_null($check['configuration_value']);
      }

      return false;
    }

    function install() {
      tep_redirect(tep_href_link('paypal.php', 'action=configure&subaction=install&module=EC'));
    }

    function remove() {
      tep_redirect(tep_href_link('paypal.php', 'action=configure&subaction=uninstall&module=EC'));
    }

    function keys() {
      return ['OSCOM_APP_PAYPAL_EC_SORT_ORDER'];
    }

    function getProductType($id, $attributes) {
      foreach ( $attributes as $a ) {
        $virtual_check_query = tep_db_query("SELECT pad.products_attributes_id FROM products_attributes pa, products_attributes_download pad WHERE pa.products_id = '" . (int)$id . "' and pa.options_values_id = '" . (int)$a['value_id'] . "' AND pa.products_attributes_id = pad.products_attributes_id LIMIT 1");

        if ( tep_db_num_rows($virtual_check_query) == 1 ) {
          return 'Digital';
        }
      }

      return 'Physical';
    }

    function templateClassExists() {
      return class_exists('oscTemplate') && isset($GLOBALS['oscTemplate']) && is_object($GLOBALS['oscTemplate']) && (get_class($GLOBALS['oscTemplate']) == 'oscTemplate');
    }

  }
