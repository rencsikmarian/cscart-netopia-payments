<?php

use Tygh\Addons\RvNetopia\Payments\NetopiaGateway;
use Tygh\Addons\RvNetopia\Request\PaymentNotify;
use Tygh\Enum\NotificationSeverity;
use Tygh\Enum\ObjectStatuses;
use Tygh\Enum\OrderStatuses;
use Tygh\Registry;

defined('BOOTSTRAP') or die('Access denied');

/** @var array $order_info */
/** @var array $processor_data */

// Include addon config for URL constants
include_once(Registry::get('config.dir.addons') . 'rv__netopia/config.php');

if (defined('PAYMENT_NOTIFICATION')) {

    // ── IPN CALLBACK / CUSTOMER RETURN ──

    if ($mode === 'notify') {
        // Server-to-server IPN from Netopia
        $envKey = isset($_POST['env_key']) ? (string) $_POST['env_key'] : '';
        $data   = isset($_POST['data']) ? (string) $_POST['data'] : '';
        $cipher = isset($_POST['cipher']) ? (string) $_POST['cipher'] : '';
        $iv     = isset($_POST['iv']) ? (string) $_POST['iv'] : null;

        if (empty($envKey) || empty($data)) {
            header('Content-Type: application/xml');
            echo PaymentNotify::buildCrcResponse(
                __('rv__netopia.ipn_missing_params'),
                'PERMANENT',
                0x300000f0
            );
            die();
        }

        try {
            // Find the payment method to get processor_params (private key)
            $processor_id = db_get_field(
                "SELECT processor_id FROM ?:payment_processors WHERE processor_script = ?s",
                NetopiaGateway::getScriptName()
            );

            $payment_ids = db_get_fields(
                "SELECT payment_id FROM ?:payments WHERE processor_id = ?i AND status = ?s",
                $processor_id,
                ObjectStatuses::ACTIVE
            );

            $pp_response = null;
            $order_id = 0;

            foreach ($payment_ids as $pid) {
                $pdata = fn_get_processor_data($pid);
                if (empty($pdata['processor_params']['private_key_content'])) {
                    continue;
                }

                try {
                    $gateway = new NetopiaGateway($pdata['processor_params']);
                    $pp_response = $gateway->processIpn($envKey, $data, $cipher, $iv);

                    // Extract order_id from the response
                    if (!empty($pp_response['netopia_order_id'])) {
                        $order_id = (int) $pp_response['netopia_order_id'];
                    }
                    break; // Decryption succeeded with this key
                } catch (\Exception $e) {
                    continue; // Try next payment method's key
                }
            }

            if ($pp_response === null || $order_id === 0) {
                header('Content-Type: application/xml');
                echo PaymentNotify::buildCrcResponse(
                    __('rv__netopia.ipn_decryption_failed'),
                    'PERMANENT',
                    0x300000f0
                );
                die();
            }

            // Verify order exists and payment script matches
            if (!fn_check_payment_script(NetopiaGateway::getScriptName(), $order_id)) {
                header('Content-Type: application/xml');
                echo PaymentNotify::buildCrcResponse(
                    __('rv__netopia.ipn_order_mismatch'),
                    'PERMANENT',
                    0x300000f4
                );
                die();
            }

            // Update order
            fn_finish_payment($order_id, $pp_response);

            // Return success CRC to Netopia
            header('Content-Type: application/xml');
            echo PaymentNotify::buildCrcResponse('OK');
            die();

        } catch (\Exception $e) {
            header('Content-Type: application/xml');
            echo PaymentNotify::buildCrcResponse(
                $e->getMessage(),
                'TEMPORARY',
                0x300000f0
            );
            die();
        }

    } elseif ($mode === 'return') {
        // Customer returning from Netopia payment page
        $order_id = isset($_REQUEST['order_id']) ? (int) $_REQUEST['order_id'] : 0;

        if ($order_id > 0) {
            fn_order_placement_routines('route', $order_id);
        }

    } elseif ($mode === 'cancel') {
        // Customer cancelled on Netopia page
        $order_id = isset($_REQUEST['order_id']) ? (int) $_REQUEST['order_id'] : 0;

        if ($order_id > 0) {
            $pp_response = [
                'order_status' => OrderStatuses::INCOMPLETED,
                'reason_text'  => __('text_transaction_cancelled'),
            ];
            fn_finish_payment($order_id, $pp_response);
            fn_order_placement_routines('route', $order_id);
        }
    }

    exit;

} else {

    // ── INITIAL PAYMENT: BUILD FORM AND REDIRECT TO NETOPIA ──

    $gateway = new NetopiaGateway($processor_data['processor_params']);

    // Build callback URLs
    $confirmUrl = fn_url(
        "payment_notification.notify?payment=rv__netopia",
        AREA,
        'current'
    );
    $returnUrl = fn_url(
        "payment_notification.return?payment=rv__netopia&order_id={$order_id}",
        AREA,
        'current'
    );

    try {
        $formData = $gateway->buildPaymentFormData($order_info, $confirmUrl, $returnUrl);

        // Build POST data for auto-submit form
        $postData = [
            'env_key' => $formData['env_key'],
            'data'    => $formData['data'],
        ];
        if (!empty($formData['cipher'])) {
            $postData['cipher'] = $formData['cipher'];
        }
        if (!empty($formData['iv'])) {
            $postData['iv'] = $formData['iv'];
        }

        // Create auto-submit form redirecting to Netopia
        fn_create_payment_form($formData['gateway_url'], $postData, 'Netopia Payments');

    } catch (\Exception $e) {
        fn_set_notification(NotificationSeverity::ERROR, __('error'), $e->getMessage());

        $pp_response = [
            'order_status' => OrderStatuses::FAILED,
            'reason_text'  => $e->getMessage(),
        ];
    }
}
