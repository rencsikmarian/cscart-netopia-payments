<?php

namespace Tygh\Addons\RvNetopia\HookHandlers;

use Tygh\Enum\NotificationSeverity;

class PaymentsHookHandler
{
    /**
     * Hook: update_payment_pre
     *
     * Preserves previously uploaded certificate/key content in processor_params
     * before the core serializes and saves them. Without this, the core overwrites
     * processor_params with only the POST data, losing any stored cert content.
     *
     * @param array  $payment_data                    Payment data (by reference)
     * @param int    $payment_id                      Payment ID
     * @param string $lang_code                       Language code
     * @param array  $certificate_file                Certificate files
     * @param string $certificates_dir                Certificates directory
     * @param bool   $can_remove_offline_payment_params Whether offline params can be removed
     */
    public function onUpdatePaymentPre(
        array &$payment_data,
        $payment_id,
        $lang_code,
        array $certificate_file,
        $certificates_dir,
        $can_remove_offline_payment_params
    ) {
        if (empty($payment_data['processor_params']['is_rv_netopia']) || empty($payment_id)) {
            return;
        }

        $old_data = fn_get_processor_data($payment_id);

        if (empty($old_data['processor_params'])) {
            return;
        }

        // Preserve existing cert content unless a new file is being uploaded
        if (
            !empty($old_data['processor_params']['public_cert_content'])
            && empty($_FILES['rv_netopia_cer']['tmp_name'])
        ) {
            $payment_data['processor_params']['public_cert_content'] = $old_data['processor_params']['public_cert_content'];
        }

        if (
            !empty($old_data['processor_params']['private_key_content'])
            && empty($_FILES['rv_netopia_key']['tmp_name'])
        ) {
            $payment_data['processor_params']['private_key_content'] = $old_data['processor_params']['private_key_content'];
        }
    }

    /**
     * Hook: update_payment_post
     *
     * Processes NEW certificate file uploads for Netopia payment method.
     * Reads uploaded .cer/.key files and stores their content in processor_params.
     *
     * @param array  $payment_data     Payment data
     * @param int    $payment_id       Payment ID
     * @param string $lang_code        Language code
     * @param array  $certificate_file Certificate files
     * @param string $certificates_dir Certificates directory
     * @param array  $processor_params Processor params
     */
    public function onUpdatePaymentPost(
        array $payment_data,
        $payment_id,
        $lang_code,
        array $certificate_file,
        $certificates_dir,
        array $processor_params
    ) {
        if (empty($processor_params['is_rv_netopia'])) {
            return;
        }

        $updated = false;

        // Handle public certificate upload
        if (!empty($_FILES['rv_netopia_cer']['tmp_name']) && $_FILES['rv_netopia_cer']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['rv_netopia_cer']['name'], PATHINFO_EXTENSION);
            if (strtolower($ext) === 'cer') {
                $content = file_get_contents($_FILES['rv_netopia_cer']['tmp_name']);
                if ($content !== false) {
                    $processor_params['public_cert_content'] = $content;
                    $updated = true;
                }
            } else {
                fn_set_notification(NotificationSeverity::ERROR, __('error'), __('rv__netopia.invalid_certificate'));
            }
        }

        // Handle private key upload
        if (!empty($_FILES['rv_netopia_key']['tmp_name']) && $_FILES['rv_netopia_key']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['rv_netopia_key']['name'], PATHINFO_EXTENSION);
            if (strtolower($ext) === 'key') {
                $content = file_get_contents($_FILES['rv_netopia_key']['tmp_name']);
                if ($content !== false) {
                    $processor_params['private_key_content'] = $content;
                    $updated = true;
                }
            } else {
                fn_set_notification(NotificationSeverity::ERROR, __('error'), __('rv__netopia.invalid_key'));
            }
        }

        // Save updated processor_params back to the database
        if ($updated) {
            db_query(
                'UPDATE ?:payments SET processor_params = ?s WHERE payment_id = ?i',
                serialize($processor_params),
                $payment_id
            );
        }
    }
}
