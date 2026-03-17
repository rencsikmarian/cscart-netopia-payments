<?php

namespace Tygh\Addons\RvNetopia\Request;

use DOMDocument;

class PaymentRequest
{
    /**
     * Build XML for a card payment request.
     *
     * @param array  $order_info       CS-Cart order info array
     * @param string $signature        Merchant account signature
     * @param string $confirmUrl       IPN callback URL
     * @param string $returnUrl        Customer return URL
     * @param string $orderId          Unique payment order ID (for Netopia)
     *
     * @return string XML string
     */
    public static function build(array $order_info, $signature, $confirmUrl, $returnUrl, $orderId)
    {
        $doc = new DOMDocument('1.0', 'utf-8');

        $rootElem = $doc->createElement('order');
        $rootElem->setAttribute('type', 'card');
        $rootElem->setAttribute('id', $orderId);
        $rootElem->setAttribute('timestamp', gmdate('YmdHis'));

        $doc->appendChild($rootElem);

        // Signature
        $sigElem = $doc->createElement('signature');
        $sigElem->appendChild($doc->createCDATASection($signature));
        $rootElem->appendChild($sigElem);

        // URLs
        $urlElem = $doc->createElement('url');

        $confirmElem = $doc->createElement('confirm');
        $confirmElem->appendChild($doc->createCDATASection($confirmUrl));
        $urlElem->appendChild($confirmElem);

        $returnElem = $doc->createElement('return');
        $returnElem->appendChild($doc->createCDATASection($returnUrl));
        $urlElem->appendChild($returnElem);

        $rootElem->appendChild($urlElem);

        // Invoice
        $invoiceElem = $doc->createElement('invoice');
        $invoiceElem->setAttribute('currency', $order_info['secondary_currency'] ?: CART_PRIMARY_CURRENCY);
        $invoiceElem->setAttribute('amount', number_format((float) $order_info['total'], 2, '.', ''));

        // Invoice details
        $detailsElem = $doc->createElement('details');
        $detailsElem->appendChild($doc->createCDATASection(
            'Order #' . $order_info['order_id']
        ));
        $invoiceElem->appendChild($detailsElem);

        // Contact info
        $contactElem = $doc->createElement('contact_info');

        // Billing address
        $billingElem = self::buildAddressElement($doc, 'billing', [
            'first_name'   => $order_info['b_firstname'],
            'last_name'    => $order_info['b_lastname'],
            'address'      => $order_info['b_address'],
            'city'         => $order_info['b_city'],
            'zip_code'     => $order_info['b_zipcode'],
            'email'        => $order_info['email'],
            'mobile_phone' => $order_info['b_phone'] ?: $order_info['phone'],
        ]);
        $contactElem->appendChild($billingElem);

        // Shipping address
        $shippingElem = self::buildAddressElement($doc, 'shipping', [
            'first_name'   => $order_info['s_firstname'],
            'last_name'    => $order_info['s_lastname'],
            'address'      => $order_info['s_address'],
            'city'         => $order_info['s_city'],
            'zip_code'     => $order_info['s_zipcode'],
            'email'        => $order_info['email'],
            'mobile_phone' => $order_info['s_phone'] ?: $order_info['phone'],
        ]);
        $contactElem->appendChild($shippingElem);

        $invoiceElem->appendChild($contactElem);
        $rootElem->appendChild($invoiceElem);

        // Params (custom data passed through and returned in IPN)
        $paramsElem = $doc->createElement('params');
        self::addParam($doc, $paramsElem, 'order_id', (string) $order_info['order_id']);
        self::addParam($doc, $paramsElem, 'platform', 'cs-cart');
        self::addParam($doc, $paramsElem, 'customer_ip', $order_info['ip_address'] ?? '');
        $rootElem->appendChild($paramsElem);

        return $doc->saveXML();
    }

    /**
     * Build an address XML element.
     *
     * @param DOMDocument $doc
     * @param string      $type  'billing' or 'shipping'
     * @param array       $data  Address fields
     *
     * @return \DOMElement
     */
    private static function buildAddressElement(DOMDocument $doc, $type, array $data)
    {
        $elem = $doc->createElement($type);
        $elem->setAttribute('type', 'person');

        $fields = [
            'first_name'   => 'first_name',
            'last_name'    => 'last_name',
            'address'      => 'address',
            'city'         => 'city',
            'zip_code'     => 'zip_code',
            'email'        => 'email',
            'mobile_phone' => 'mobile_phone',
        ];

        foreach ($fields as $xmlTag => $dataKey) {
            $value = isset($data[$dataKey]) ? (string) $data[$dataKey] : '';
            $child = $doc->createElement($xmlTag);
            $child->appendChild($doc->createCDATASection($value));
            $elem->appendChild($child);
        }

        return $elem;
    }

    /**
     * Add a param element to the params container.
     *
     * @param DOMDocument $doc
     * @param \DOMElement $paramsElem
     * @param string      $name
     * @param string      $value
     */
    private static function addParam(DOMDocument $doc, \DOMElement $paramsElem, $name, $value)
    {
        $paramElem = $doc->createElement('param');

        $nameElem = $doc->createElement('name');
        $nameElem->appendChild($doc->createCDATASection($name));
        $paramElem->appendChild($nameElem);

        $valueElem = $doc->createElement('value');
        $valueElem->appendChild($doc->createCDATASection($value));
        $paramElem->appendChild($valueElem);

        $paramsElem->appendChild($paramElem);
    }
}
