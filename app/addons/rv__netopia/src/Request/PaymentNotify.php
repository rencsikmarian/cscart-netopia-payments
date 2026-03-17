<?php

namespace Tygh\Addons\RvNetopia\Request;

use DOMDocument;

class PaymentNotify
{
    /**
     * Parse decrypted IPN XML into a structured array.
     *
     * @param string $xml The decrypted XML string
     *
     * @return array Parsed notification data
     *
     * @throws \RuntimeException If XML parsing fails
     */
    public static function parse($xml)
    {
        $doc = new DOMDocument();
        if (!$doc->loadXML($xml)) {
            throw new \RuntimeException('Failed to parse IPN XML');
        }

        $orderElem = $doc->documentElement;
        $result = [
            'type'      => $orderElem->getAttribute('type'),
            'id'        => $orderElem->getAttribute('id'),
            'timestamp' => $orderElem->getAttribute('timestamp'),
        ];

        // Initialize defaults
        $result['mobilpay_reference'] = '';
        $result['action'] = '';
        $result['error_code'] = 0;
        $result['error_message'] = '';
        $result['purchase_id'] = '';
        $result['original_amount'] = 0;
        $result['processed_amount'] = 0;
        $result['pan_masked'] = '';
        $result['token_id'] = '';
        $result['params'] = [];

        // Parse child elements
        $mobilpayNodes = $orderElem->childNodes;
        foreach ($mobilpayNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            switch ($node->nodeName) {
                case 'mobilpay_refference':
                    $result['mobilpay_reference'] = trim($node->nodeValue);
                    break;

                case 'mobilpay_refference_action':
                    // This contains the actual IPN notification data
                    foreach ($node->childNodes as $child) {
                        if ($child->nodeType !== XML_ELEMENT_NODE) {
                            continue;
                        }
                        switch ($child->nodeName) {
                            case 'action':
                                $result['action'] = trim($child->nodeValue);
                                break;
                            case 'customer':
                                // Customer data (optional)
                                break;
                            case 'purchase_id':
                                $result['purchase_id'] = trim($child->nodeValue);
                                break;
                            case 'original_amount':
                                $result['original_amount'] = (float) $child->nodeValue;
                                break;
                            case 'processed_amount':
                                $result['processed_amount'] = (float) $child->nodeValue;
                                break;
                            case 'pan_masked':
                                $result['pan_masked'] = trim($child->nodeValue);
                                break;
                            case 'token_id':
                                $result['token_id'] = trim($child->nodeValue);
                                break;
                            case 'error':
                                $result['error_code'] = (int) $child->getAttribute('code');
                                $result['error_message'] = trim($child->nodeValue);
                                break;
                        }
                    }
                    break;

                case 'params':
                    foreach ($node->getElementsByTagName('param') as $paramNode) {
                        $pName = '';
                        $pValue = '';
                        foreach ($paramNode->childNodes as $pChild) {
                            if ($pChild->nodeType !== XML_ELEMENT_NODE) {
                                continue;
                            }
                            if ($pChild->nodeName === 'name') {
                                $pName = trim($pChild->nodeValue);
                            } elseif ($pChild->nodeName === 'value') {
                                $pValue = trim($pChild->nodeValue);
                            }
                        }
                        if ($pName !== '') {
                            $result['params'][$pName] = $pValue;
                        }
                    }
                    break;
            }
        }

        return $result;
    }

    /**
     * Build the CRC XML response to send back to Netopia.
     *
     * @param string      $message    Response message
     * @param string|null $errorType  'PERMANENT' or 'TEMPORARY' (null for success)
     * @param int|null    $errorCode  Error code (null for success)
     *
     * @return string XML response
     */
    public static function buildCrcResponse($message, $errorType = null, $errorCode = null)
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $crcElem = $doc->createElement('crc', $message);

        if ($errorType !== null) {
            $crcElem->setAttribute('error_type', $errorType);
            $crcElem->setAttribute('error_code', sprintf('0x%08x', $errorCode));
        }

        $doc->appendChild($crcElem);

        return $doc->saveXML();
    }
}
