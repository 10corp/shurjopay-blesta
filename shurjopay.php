<?php
/**
 * ShurjoPay Plugin for Blesta
 *
 * @package blesta
 * @subpackage blesta.plugins.shurjopay
 * @author Md Wali Mosnad Ayshik
 * @copyright Copyright (c) [2024], [shurjoMukhi LTD.]
 * @copyright Copyright (c) 1998-2024, Web Services LLC
 * @link http://www.10corp.com/ 10CORP
 * @license [Since 2024]
 * @link [https://github.com/10corp/shurjopay-blesta/]
 */
class Shurjopay extends NonmerchantGateway {
    /**
     * @var array Meta data for this gateway
     */
    private $meta;

    /**
     * @var string The currency code for payments
     */
    private $currency;

    /**
     * Constructor
     */
    public function __construct() {
        // Load components
        Loader::loadComponents($this, ['Input']);

        // Load configuration
        try {
            $this->loadConfig(dirname(__FILE__) . DS . 'config.json');
        } catch (Exception $e) {
            error_log("ShurjoPay: Failed to load config.json: " . $e->getMessage());
        }

        // Load language
        Language::loadLang('shurjopay', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Returns the name of this gateway
     *
     * @return string The common name for this gateway
     */
    public function getName() {
        return Language::_('Shurjopay.name', true);
    }

    /**
     * Returns a description of this gateway
     *
     * @return string The description for this gateway
     */
    public function getDescription() {
        return Language::_('Shurjopay.description', true);
    }

    /**
     * Returns settings view for the gateway
     *
     * @param array $meta An array of meta data
     * @return string HTML content for the settings form
     */
    public function getSettings(array $meta = null) {
        $this->view = $this->makeView('settings', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('meta', $meta);
        return $this->view->fetch();
    }

    /**
     * Validates and updates gateway settings
     *
     * @param array $meta Meta data to update
     * @return array Updated meta data or errors
     */
    public function editSettings(array $meta) {
        $rules = [
            'store_id' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Shurjopay.!error.username.valid', true)
                ]
            ],
            'store_password' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Shurjopay.!error.password.valid', true)
                ]
            ],
            'store_prefix' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Shurjopay.!error.prefix.valid', true)
                ]
            ]
        ];

        $meta['dev_mode'] = isset($meta['dev_mode']) && $meta['dev_mode'] === 'true' ? 'true' : 'false';
        $this->Input->setRules($rules);

        if ($this->Input->validates($meta)) {
            return $meta;
        }

        return $meta;
    }

    /**
     * Returns fields to encrypt
     *
     * @return array Fields to encrypt
     */
    public function encryptableFields() {
        return ['store_id', 'store_password'];
    }

    /**
     * Sets the currency code
     *
     * @param string $currency The ISO 4217 currency code
     */
    public function setCurrency($currency) {
        $this->currency = $currency;
    }

    /**
     * Sets meta data for the gateway
     *
     * @param array $meta Meta data to set
     */
    public function setMeta(array $meta = null) {
        $this->meta = $meta;
    }

    /**
     * Builds the payment process form
     *
     * @param array $contact_info Contact information
     * @param float $amount Payment amount
     * @param array $invoice_amounts Invoice amounts
     * @param array $options Additional options
     * @return string HTML form or null on error
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null) {
        Loader::loadModels($this, ['Clients', 'Contacts']);
        Loader::loadHelpers($this, ['Html']);

        $amount = number_format($amount, 2, '.', '');
        $client = $this->Clients->get($contact_info['client_id']);
        $client_phone = $this->getClientPhone($client);

        $invoices = $invoice_amounts ? $this->serializeInvoices($invoice_amounts) : '';
        $return_url = $this->cleanReturnUrl($options['return_url'] ?? '');

        $api_url = $this->getApiUrl();
        $token_data = $this->getToken($api_url);
        if (!$token_data) {
            $this->Input->setErrors(['api' => ['token' => Language::_('Shurjopay.!error.token.failed', true)]]);
            return null;
        }

        $params = [
            'token' => $token_data['token'],
            'store_id' => $token_data['store_id'],
            'currency' => $this->currency ?? 'BDT',
            'return_url' => $return_url,
            'cancel_url' => $return_url,
            'amount' => $amount,
            'prefix' => $this->meta['store_prefix'] ?? 'SP',
            'order_id' => ($this->meta['store_prefix'] ?? 'SP') . uniqid(),
            'discsount_amount' => 0,
            'disc_percent' => 0,
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'customer_name' => $this->Html->concat(' ', $contact_info['first_name'] ?? '', $contact_info['last_name'] ?? ''),
            'customer_phone' => $client_phone,
            'customer_email' => $client->email ?? '',
            'customer_address' => $client->address1 ?? ($client->address2 ?? ''),
            'customer_city' => $client->city ?? '',
            'customer_state' => $client->state ?? '',
            'customer_postcode' => $client->zip ?? '',
            'customer_country' => $client->country ?? 'BD',
            'value1' => $invoices,
            'value2' => $client->id ?? '',
            'value3' => '',
            'value4' => ''
        ];

        $this->log($_SERVER['REQUEST_URI'] ?? '', json_encode($params), 'input', true);
        $response = $this->makeApiRequest($api_url . 'api/secret-pay', json_encode($params), $token_data['token']);

        if (!$response || empty($response['checkout_url'])) {
            $this->log($_SERVER['REQUEST_URI'] ?? '', json_encode($response), 'output', false);
            $this->Input->setErrors(['api' => ['response' => Language::_('Shurjopay.!error.api.response', true)]]);
            return null;
        }

        $this->log($_SERVER['REQUEST_URI'] ?? '', json_encode($response), 'output', true);
        return $this->buildForm($response['checkout_url']);
    }

    /**
     * Validates the payment response from ShurjoPay
     *
     * @param array $get GET data
     * @param array $post POST data
     * @return array Transaction data or null on error
     */
    public function validate(array $get, array $post) {
        $order_id = $get['order_id'] ?? null;
        if (!$order_id) {
            $this->Input->setErrors(['order' => ['missing' => Language::_('Shurjopay.!error.order.missing', true)]]);
            return null;
        }

        $response = $this->verifyPayment($order_id);
        return $this->processPaymentResponse($response, $order_id);
    }

    /**
     * Handles successful payment callback
     *
     * @param array $get GET data
     * @param array $post POST data
     * @return array Transaction data or null on error
     */
    public function success(array $get, array $post) {
        $order_id = $get['order_id'] ?? null;
        if (!$order_id) {
            $this->Input->setErrors(['order' => ['missing' => Language::_('Shurjopay.!error.order.missing', true)]]);
            return null;
        }

        $response = $this->verifyPayment($order_id);
        $data = $this->processPaymentResponse($response, $order_id);

        if ($data && $data['status'] === 'approved') {
            try {
                $this->callBackNotification($this->buildNotificationURL($order_id));
            } catch (Exception $e) {
                error_log("ShurjoPay: Callback notification failed: " . $e->getMessage());
            }
        }

        return $data;
    }

    /**
     * Returns the error URL
     *
     * @param array $contact_info Contact information
     * @param array $post POST data
     * @return string Error URL
     */
    public function error(array $contact_info, array $post) {
        return Configure::get('Blesta.gw_callback_url') . Configure::get('Blesta.company_id') . '/shurjopay/';
    }

    /**
     * Formats fields for input/output
     *
     * @param mixed $fields Fields to format
     * @param string $direction Direction ('input' or 'output')
     * @return mixed Formatted fields
     */
    public function formatFields($fields, $direction = 'output') {
        if (!in_array($direction, ['input', 'output'])) {
            $direction = 'output';
        }
        return parent::formatFields($fields, $direction);
    }

    /**
     * Gets client phone number
     *
     * @param object $client Client data
     * @return string Phone number
     */
    private function getClientPhone($client) {
        if (!$client || !$client->contact_id) {
            return '';
        }

        $contact_numbers = $this->Contacts->getNumbers($client->contact_id);
        foreach ($contact_numbers as $number) {
            if ($number->type === 'phone' && in_array($number->location, ['home', 'work', 'mobile'])) {
                return preg_replace('/[^0-9]/', '', $number->number);
            }
        }
        return '';
    }

    /**
     * Cleans return URL by removing client_id
     *
     * @param string $return_url Return URL
     * @return string Cleaned URL
     */
    private function cleanReturnUrl($return_url) {
        $url_components = parse_url($return_url);
        if (empty($url_components)) {
            return Configure::get('Blesta.gw_callback_url') . Configure::get('Blesta.company_id') . '/shurjopay/';
        }

        if (isset($url_components['query'])) {
            parse_str($url_components['query'], $query_params);
            unset($query_params['client_id']);
            $url_components['query'] = http_build_query($query_params);
        }

        $new_url = $url_components['scheme'] . '://' . $url_components['host'] . ($url_components['path'] ?? '');
        if (!empty($url_components['query'])) {
            $new_url .= '?' . $url_components['query'];
        }
        if (!empty($url_components['fragment'])) {
            $new_url .= '#' . $url_components['fragment'];
        }

        return $new_url;
    }

    /**
     * Gets API URL based on dev_mode
     *
     * @return string API URL
     */
    private function getApiUrl() {
        return ($this->meta['dev_mode'] ?? 'false') === 'true'
            ? 'https://sandbox.shurjopayment.com/'
            : 'https://engine.shurjopayment.com/';
    }

    /**
     * Fetches authentication token
     *
     * @param string $api_url API URL
     * @return array|null Token data or null on error
     */
    private function getToken($api_url) {
        $post_fields = json_encode([
            'username' => $this->meta['store_id'] ?? '',
            'password' => $this->meta['store_password'] ?? ''
        ]);

        $response = $this->makeApiRequest($api_url . 'api/get_token', $post_fields);
        if ($response && !empty($response['token']) && !empty($response['store_id'])) {
            return $response;
        }

        error_log("ShurjoPay: Token request failed: " . json_encode($response));
        return null;
    }

    /**
     * Verifies payment status
     *
     * @param string $order_id Order ID
     * @return array|null API response or null on error
     */
    private function verifyPayment($order_id) {
        $api_url = $this->getApiUrl();
        $token_data = $this->getToken($api_url);
        if (!$token_data) {
            $this->Input->setErrors(['api' => ['token' => Language::_('Shurjopay.!error.token.failed', true)]]);
            return null;
        }

        $post_fields = json_encode(['order_id' => $order_id]);
        $response = $this->makeApiRequest($api_url . 'api/verification/', $post_fields, $token_data['token']);
        $this->log($_SERVER['REQUEST_URI'] ?? '', json_encode($response), 'input', !empty($response));

        return $response;
    }

    /**
     * Processes payment response
     *
     * @param array $data API response
     * @param string $order_id Order ID
     * @return array|null Transaction data or null on error
     */
    private function processPaymentResponse($data, $order_id) {
        if (!$data || empty($data[0]['sp_code'])) {
            $this->Input->setErrors(['api' => ['response' => Language::_('Shurjopay.!error.api.response', true)]]);
            return null;
        }

        $status = $data[0]['sp_code'];
        if ($status === '1000') {
            return [
                'client_id' => $data[0]['value2'] ?? null,
                'amount' => $data[0]['amount'] ?? 0,
                'currency' => $data[0]['currency'] ?? ($this->currency ?? 'BDT'),
                'status' => 'approved',
                'reference_id' => $data[0]['bank_trx_id'] ?? null,
                'transaction_id' => $data[0]['order_id'] ?? $order_id,
                'invoices' => $this->unserializeInvoices($data[0]['value1'] ?? '')
            ];
        }

        $error_messages = [
            '1002' => Language::_('Shurjopay.!error.payment.canceled', true),
            '1068' => Language::_('Shurjopay.!error.payment.canceled', true)
        ];
        $this->Input->setErrors([
            'payment' => [
                'status' => $error_messages[$status] ?? Language::_('Shurjopay.!error.payment.failed', true)
            ]
        ]);
        return null;
    }

    /**
     * Makes an API request
     *
     * @param string $url API endpoint
     * @param string $post_fields POST data
     * @param string $token Bearer token (optional)
     * @return array|null Decoded response or null on error
     */
    private function makeApiRequest($url, $post_fields, $token = null) {
        $headers = ['Content-Type: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_fields,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Blesta/ShurjoPay-Plugin'
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            error_log("ShurjoPay: cURL error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        return json_decode($response, true);
    }

    /**
     * Serializes invoices
     *
     * @param array $invoices Invoice data
     * @return string Serialized string
     */
    private function serializeInvoices(array $invoices) {
        $result = '';
        foreach ($invoices as $i => $invoice) {
            $result .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }
        return $result;
    }

    /**
     * Unserializes invoices
     *
     * @param string $str Serialized invoice string
     * @return array Invoice data
     */
    private function unserializeInvoices($str) {
        $invoices = [];
        if (empty($str)) {
            return $invoices;
        }

        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) === 2) {
                $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
            }
        }
        return $invoices;
    }

    /**
     * Builds the payment form
     *
     * @param string $post_to Form action URL
     * @return string HTML form
     */
    private function buildForm($post_to) {
        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('post_to', $post_to);
        return $this->view->fetch();
    }

    /**
     * Builds notification URL
     *
     * @param string|null $order_id Order ID
     * @return string Notification URL
     */
    private function buildNotificationURL(?string $order_id = null): string {
        $base_url = rtrim(Configure::get('Blesta.gw_callback_url'), '/');
        $company_id = Configure::get('Blesta.company_id');
        $query_param = $order_id ? http_build_query(['order_id' => $order_id]) : 'order_id=null';
        return sprintf('%s/%s/shurjopay/?%s', $base_url, $company_id, $query_param);
    }

    /**
     * Sends callback notification
     *
     * @param string $url Notification URL
     */
    private function callBackNotification(string $url): void {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Blesta/ShurjoPay-Plugin'
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            error_log("ShurjoPay: Callback notification failed: " . curl_error($ch));
        }
        curl_close($ch);
    }

    /**
     * Placeholder for capture (unsupported)
     */
    public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts = null) {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * Placeholder for void (unsupported)
     */
    public function void($reference_id, $transaction_id, $notes = null) {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * Placeholder for refund (unsupported)
     */
    public function refund($reference_id, $transaction_id, $amount, $notes = null) {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }
}
?>