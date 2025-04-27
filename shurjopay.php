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

class shurjopay extends NonmerchantGateway {
    /**
     * @var string The version of this gateway
     */
    private $meta;

    /**
     * Construct a new merchant gateway
     */
    public function __construct() {
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');
        // Load components required by this gateway
        Loader::loadComponents($this, array("Input"));
        // Load the language required by this gateway
        Language::loadLang("shurjopay", null, dirname(__FILE__) . DS . "language" . DS);
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency) {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null) {
        $this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));
        Loader::loadHelpers($this, array("Form", "Html"));
        $this->view->set("meta", $meta);
        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta) {
        $rules = [
            'store_id' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('shurjopay.!error.username.valid', true)
                ]
            ],
            'store_password' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('shurjopay.!error.password.valid', true)
                ]
            ]
        ];

        if (!isset($meta['dev_mode'])) {
            $meta['dev_mode'] = 'false';
        }

        $this->Input->setRules($rules);
        $this->Input->validates($meta);
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields() {
        return ['store_id', 'store_password'];
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null) {
        $this->meta = $meta;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null) {
        Loader::loadModels($this, ['Clients', 'Contacts']);
        Loader::loadHelpers($this, ['Html']);
        $amount = number_format($amount, 2, '.', '');

        $client = $this->Clients->get($contact_info['client_id']);
        $contact_numbers = $this->Contacts->getNumbers($client->contact_id);

        $client_phone = '';
        foreach ($contact_numbers as $contact_number) {
            switch ($contact_number->location) {
                case 'home':
                case 'work':
                case 'mobile':
                    if ($contact_number->type == 'phone') {
                        $client_phone = $contact_number->number;
                    }
                    break;
            }
        }
        if (!empty($client_phone)) {
            $client_phone = preg_replace('/[^0-9]/', '', $client_phone);
        }

        if (isset($invoice_amounts) && is_array($invoice_amounts)) {
            $invoices = $this->serializeInvoices($invoice_amounts);
        }
        $return_url = isset($options['return_url']) ? $options['return_url'] : null;

        $url_components = parse_url($return_url);
        if (isset($url_components['query'])) {
            parse_str($url_components['query'], $query_params);
            unset($query_params['client_id']);
            $new_query = http_build_query($query_params);
            $url_components['query'] = !empty($new_query) ? $new_query : null;
        }

        $new_url = $url_components['scheme'] . '://' . $url_components['host'] . $url_components['path'];
        if (!empty($url_components['query'])) {
            $new_url .= '?' . $url_components['query'];
        }
        if (!empty($url_components['fragment'])) {
            $new_url .= '#' . $url_components['fragment'];
        }
        $return_url = trim($new_url);

        // Set API URL (update if ShurjoPay provides a new endpoint, e.g., https://api.shurjopay.com/)
        if ($this->meta['dev_mode'] == 'false') {
            $this->url = 'https://www.engine.shurjopayment.com/';
        } else {
            $this->url = 'https://www.sandbox.shurjopayment.com/';
        }

        $token = json_decode($this->gettoken($this->meta['store_id'], $this->meta['store_password'], $this->url), true);
        $bear_token = $token['token'];
        $store_id = $token['store_id'];

        $params = json_encode([
            'token' => $bear_token,
            'store_id' => $store_id,
            'currency' => ($this->currency ?? null),
            'return_url' => $return_url,
            'cancel_url' => $return_url,
            'amount' => ($amount ?? null),
            'prefix' => $this->meta['store_prefix'],
            'order_id' => $this->meta['store_prefix'] . uniqid(),
            'discsount_amount' => 0,
            'disc_percent' => 0,
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'customer_name' => $this->Html->concat(
                ' ',
                (isset($contact_info['first_name']) ? $contact_info['first_name'] : null),
                (isset($contact_info['last_name']) ? $contact_info['last_name'] : null)
            ),
            'customer_phone' => ($client_phone ?? null),
            'customer_email' => ($client->email ?? null),
            'customer_address' => ($client->address1 ?? $client->address2),
            'customer_city' => ($client->city ?? 'no city'),
            'customer_state' => ($client->state ?? 'no state'),
            'customer_postcode' => ($client->zip ?? 'no zip'),
            'customer_country' => ($client->country ?? 'no country'),
            'value1' => ($invoices ?? null),
            'value2' => ($client->id ?? null),
            'value3' => 'value3',
            'value4' => 'value4'
        ]);

        $header = [
            'Content-Type:application/json',
            'Authorization: Bearer ' . $bear_token
        ];

        $this->log((isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null), serialize($params), 'input', true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url . 'api/secret-pay');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose = fopen('php://temp', 'w+'));

        $response = curl_exec($ch);
        if ($response === false) {
            rewind($verbose);
            $verbose_log = stream_get_contents($verbose);
            error_log('cURL Error in buildProcess: ' . curl_error($ch) . "\nVerbose Log: " . $verbose_log);
            $this->log($this->url . 'api/secret-pay', 'cURL Error: ' . curl_error($ch) . "\nVerbose Log: " . $verbose_log, 'error', false);
            echo json_encode(['error' => curl_error($ch), 'verbose' => $verbose_log]);
        }
        fclose($verbose);
        curl_close($ch);

        $request = json_decode($response);

        try {
            if ($request->checkout_url) {
                $this->log((isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null), serialize($request), 'output', true);
                return $this->buildForm($request->checkout_url);
            } else {
                $this->log((isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null), serialize($request), 'output', false);
                $this->Input->setErrors(['api' => ['response' => $response]]);
                return null;
            }
        } catch (Exception $e) {
            $this->Input->setErrors(['internal' => ['response' => $e->getMessage()]]);
        }
    }

    private function serializeInvoices(array $invoices) {
        $str = '';
        foreach ($invoices as $i => $invoice) {
            $str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }
        return $str;
    }

    public function gettoken($username, $password, $url) {
        $url = $url . 'api/get_token';
        $postFields = json_encode([
            'username' => $username,
            'password' => $password,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_VERBOSE => true,
            CURLOPT_STDERR => $verbose = fopen('php://temp', 'w+'),
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            rewind($verbose);
            $verbose_log = stream_get_contents($verbose);
            error_log('cURL Error in gettoken: ' . curl_error($curl) . "\nVerbose Log: " . $verbose_log);
            $this->log($url, 'cURL Error: ' . curl_error($curl) . "\nVerbose Log: " . $verbose_log, 'error', false);
            echo json_encode(['error' => curl_error($curl), 'verbose' => $verbose_log]);
        }
        fclose($verbose);
        curl_close($curl);

        return $response;
    }

    private function unserializeInvoices($str) {
        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }
            $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
        }
        return $invoices;
    }

    private function buildForm($post_to) {
        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('post_to', $post_to);
        return $this->view->fetch();
    }

    /**
     * Builds the notification URL with the given order ID.
     */
    private function buildNotificationURL(?string $order_id = null): string {
        $base_url = Configure::get('Blesta.gw_callback_url');
        $company_id = Configure::get('Blesta.company_id');

        if (empty($base_url) || empty($company_id)) {
            throw new Exception("Invalid configuration: Base URL or Company ID is missing.");
        }

        $base_url = rtrim($base_url, '/');
        $path = sprintf('%s/shurjopay/', $company_id);
        $query_param = $order_id ? http_build_query(['order_id' => $order_id]) : 'order_id=null';
        $notification_url = sprintf('%s/%s?%s', $base_url, $path, $query_param);

        if (!filter_var($notification_url, FILTER_VALIDATE_URL)) {
            throw new Exception("Failed to construct a valid notification URL.");
        }
        return $notification_url;
    }

    /**
     * Sends a callback notification to the specified URL using cURL.
     */
    private function callBackNotification(string $url): void {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:103.0) Gecko/20100101 Firefox/103.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_VERBOSE => true,
            CURLOPT_STDERR => $verbose = fopen('php://temp', 'w+'),
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            rewind($verbose);
            $verbose_log = stream_get_contents($verbose);
            error_log('cURL Error in callBackNotification: ' . curl_error($ch) . "\nVerbose Log: " . $verbose_log);
            echo json_encode(['error' => curl_error($ch), 'verbose' => $verbose_log]);
        }
        fclose($verbose);
        curl_close($ch);
    }

    /**
     * Validates the incoming POST/GET response from the gateway
     */
    public function validate(array $get, array $post) {
        if ($this->meta['dev_mode'] == 'false') {
            $this->url = 'https://www.engine.shurjopayment.com/';
        } else {
            $this->url = 'https://www.sandbox.shurjopayment.com/';
        }

        $order_id = isset($get['order_id']) ? $get['order_id'] : null;
        $token = json_decode($this->gettoken($this->meta['store_id'], $this->meta['store_password'], $this->url), true);
        $bear_token = $token['token'];

        $header = [
            'Content-Type:application/json',
            'Authorization: Bearer ' . $bear_token
        ];
        $postFields = json_encode(['order_id' => $order_id]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url . 'api/verification/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9)");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose = fopen('php://temp', 'w+'));

        $response = curl_exec($ch);
        if ($response === false) {
            rewind($verbose);
            $verbose_log = stream_get_contents($verbose);
            error_log('cURL Error in validate: ' . curl_error($ch) . "\nVerbose Log: " . $verbose_log);
            $this->log($this->url . 'api/verification/', 'cURL Error: ' . curl_error($ch) . "\nVerbose Log: " . $verbose_log, 'error', false);
            echo json_encode(['error' => curl_error($ch), 'verbose' => $verbose_log]);
        }
        fclose($verbose);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data[0]['sp_code'])) {
            if ($data[0]['sp_code'] == '1000') {
                $invoices = $data[0]['value1'];
                return [
                    'client_id' => $data[0]['value2'],
                    'amount' => $data[0]['amount'],
                    'currency' => $data[0]['currency'],
                    'status' => "approved",
                    'reference_id' => $data[0]['bank_trx_id'],
                    'transaction_id' => $data[0]['order_id'],
                    'invoices' => $this->unserializeInvoices($invoices),
                ];
            } elseif ($data[0]['sp_code'] == '1002' || $data[0]['sp_code'] == '1068') {
                $this->Input->setErrors([
                    'payment' => ['canceled' => Language::_('shurjopay.!error.payment.canceled', true)]
                ]);
            } else {
                $this->Input->setErrors([
                    'payment' => ['failed' => Language::_('shurjopay.!error.payment.failed', true)]
                ]);
            }
        }
    }

    /**
     * Returns data regarding a success transaction
     */
    public function success(array $get, array $post) {
        if ($this->meta['dev_mode'] == 'false') {
            $this->url = 'https://www.engine.shurjopayment.com/';
        } else {
            $this->url = 'https://www.sandbox.shurjopayment.com/';
        }

        $order_id = isset($get['order_id']) ? $get['order_id'] : null;
        $client_id = isset($get['client_id']) ? $get['client_id'] : null;

        $token = json_decode($this->gettoken($this->meta['store_id'], $this->meta['store_password'], $this->url), true);
        $bear_token = $token['token'];

        $header = [
            'Content-Type:application/json',
            'Authorization: Bearer ' . $bear_token
        ];
        $postFields = json_encode(['order_id' => $order_id]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url . 'api/verification/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9)");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose = fopen('php://temp', 'w+'));

        $response = curl_exec($ch);
        if ($response === false) {
            rewind($verbose);
            $verbose_log = stream_get_contents($verbose);
            error_log('cURL Error in success: ' . curl_error($ch) . "\nVerbose Log: " . $verbose_log);
            $this->log($this->url . 'api/verification/', 'cURL Error: ' . curl_error($ch) . "\nVerbose Log: " . $verbose_log, 'error', false);
            echo json_encode(['error' => curl_error($ch), 'verbose' => $verbose_log]);
        }
        fclose($verbose);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data[0]['sp_code'])) {
            if ($data[0]['sp_code'] == '1000') {
                $notification_url = $this->buildNotificationURL($order_id);
                $this->callBackNotification($notification_url);
                $invoices = $data[0]['value1'];
                return [
                    'client_id' => $data[0]['value2'],
                    'amount' => $data[0]['amount'],
                    'currency' => $data[0]['currency'],
                    'status' => "approved",
                    'reference_id' => $data[0]['bank_trx_id'],
                    'transaction_id' => $data[0]['order_id'],
                    'invoices' => $this->unserializeInvoices($invoices),
                ];
            } elseif ($data[0]['sp_code'] == '1002' || $data[0]['sp_code'] == '1068') {
                $this->Input->setErrors([
                    'payment' => ['canceled' => Language::_('shurjopay.!error.payment.canceled', true)]
                ]);
            } else {
                $this->Input->setErrors([
                    'payment' => ['failed' => Language::_('shurjopay.!error.payment.failed', true)]
                ]);
            }
        }
    }

    /**
     * Captures a previously authorized payment
     */
    public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts = null) {
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }

    /**
     * Void a payment or authorization
     */
    public function void($reference_id, $transaction_id, $notes = null) {
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }

    /**
     * Refund a payment
     */
    public function refund($reference_id, $transaction_id, $amount, $notes = null) {
        $this->Input->setErrors($this->getCommonError("unsupported"));
    }
}
?>
