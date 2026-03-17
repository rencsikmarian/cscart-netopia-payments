<?php

namespace Tygh\Addons\RvNetopia\Payments;

use Tygh\Addons\RvNetopia\Encryption\NetopiaEncryption;
use Tygh\Addons\RvNetopia\Request\PaymentRequest;
use Tygh\Addons\RvNetopia\Request\PaymentNotify;
use Tygh\Enum\OrderStatuses;

class NetopiaGateway
{
    const ADDON_NAME = 'rv__netopia';
    const SCRIPT_NAME = 'rv__netopia.php';

    /** @var array */
    protected $processorParams;

    /**
     * @param array $processorParams Payment method processor_params
     */
    public function __construct(array $processorParams)
    {
        $this->processorParams = $processorParams;
    }

    /**
     * @return string
     */
    public static function getScriptName()
    {
        return self::SCRIPT_NAME;
    }

    /**
     * @return string
     */
    public static function getAddonName()
    {
        return self::ADDON_NAME;
    }

    /**
     * Get the Netopia gateway URL based on test/live mode.
     *
     * @return string
     */
    public function getGatewayUrl()
    {
        if (!empty($this->processorParams['mode']) && $this->processorParams['mode'] === 'test') {
            return RV_NETOPIA_SANDBOX_URL;
        }

        return RV_NETOPIA_LIVE_URL;
    }

    /**
     * Build encrypted payment form data for redirecting to Netopia.
     *
     * @param array  $orderInfo   CS-Cart order info
     * @param string $confirmUrl  IPN callback URL
     * @param string $returnUrl   Customer return URL
     *
     * @return array [env_key, data, cipher, iv, gateway_url]
     *
     * @throws \RuntimeException If encryption fails or certificates missing
     */
    public function buildPaymentFormData(array $orderInfo, $confirmUrl, $returnUrl)
    {
        $signature = $this->processorParams['signature'] ?? '';
        $publicCert = $this->processorParams['public_cert_content'] ?? '';

        if (empty($publicCert)) {
            throw new \RuntimeException(__('rv__netopia.missing_certificates'));
        }

        // Generate unique order ID for Netopia
        $netopiaOrderId = md5(uniqid((string) mt_rand(), true));

        // Build XML
        $xml = PaymentRequest::build($orderInfo, $signature, $confirmUrl, $returnUrl, $netopiaOrderId);

        // Encrypt
        $encrypted = NetopiaEncryption::encrypt($xml, $publicCert);
        $encrypted['gateway_url'] = $this->getGatewayUrl();

        return $encrypted;
    }

    /**
     * Process an IPN callback from Netopia.
     *
     * @param string      $envKey     Base64-encoded envelope key
     * @param string      $data       Base64-encoded encrypted data
     * @param string      $cipher     Cipher used
     * @param string|null $iv         Base64-encoded IV
     *
     * @return array $pp_response for fn_finish_payment()
     *
     * @throws \RuntimeException If decryption or parsing fails
     */
    public function processIpn($envKey, $data, $cipher, $iv = null)
    {
        $privateKey = $this->processorParams['private_key_content'] ?? '';

        if (empty($privateKey)) {
            throw new \RuntimeException(__('rv__netopia.missing_certificates'));
        }

        // Decrypt
        $xml = NetopiaEncryption::decrypt($envKey, $data, $cipher, $iv, $privateKey);

        // Parse
        $notify = PaymentNotify::parse($xml);

        // Map to CS-Cart response
        return $this->mapNotifyToResponse($notify);
    }

    /**
     * Map Netopia IPN notification to CS-Cart $pp_response array.
     *
     * @param array $notify Parsed IPN data
     *
     * @return array [order_status, transaction_id, reason_text, order_id, ...]
     */
    protected function mapNotifyToResponse(array $notify)
    {
        $ppResponse = [
            'transaction_id' => $notify['purchase_id'] ?: $notify['mobilpay_reference'] ?? '',
            'reason_text'    => '',
            'order_status'   => OrderStatuses::FAILED,
            'netopia_action' => $notify['action'],
            'pan_masked'     => $notify['pan_masked'] ?? '',
        ];

        // Extract our CS-Cart order_id from params
        if (!empty($notify['params']['order_id'])) {
            $ppResponse['netopia_order_id'] = $notify['params']['order_id'];
        }

        $errorCode = (int) ($notify['error_code'] ?? 0);

        if ($errorCode === 0) {
            switch ($notify['action']) {
                case 'confirmed':
                    $ppResponse['order_status'] = OrderStatuses::PAID;
                    $ppResponse['reason_text'] = __('rv__netopia.payment_confirmed');
                    break;

                case 'paid':
                    $ppResponse['order_status'] = OrderStatuses::OPEN;
                    $ppResponse['reason_text'] = __('rv__netopia.payment_authorized');
                    break;

                case 'confirmed_pending':
                case 'paid_pending':
                    $ppResponse['order_status'] = OrderStatuses::OPEN;
                    $ppResponse['reason_text'] = __('rv__netopia.payment_pending');
                    break;

                case 'canceled':
                    $ppResponse['order_status'] = OrderStatuses::INCOMPLETED;
                    $ppResponse['reason_text'] = __('rv__netopia.payment_canceled');
                    break;

                case 'credit':
                    $ppResponse['order_status'] = OrderStatuses::INCOMPLETED;
                    $ppResponse['reason_text'] = __('rv__netopia.refund_received');
                    break;

                default:
                    $ppResponse['order_status'] = OrderStatuses::OPEN;
                    $ppResponse['reason_text'] = __('rv__netopia.payment_unknown_action', ['[action]' => $notify['action']]);
                    break;
            }
        } else {
            $ppResponse['order_status'] = OrderStatuses::FAILED;
            $ppResponse['reason_text'] = __('rv__netopia.payment_failed', [
                '[error_message]' => $notify['error_message'] ?: 'Error code: ' . $errorCode,
            ]);
        }

        return $ppResponse;
    }
}
