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

require_once 'models/db_connect.php';
class shurjopay extends NonmerchantGateway
{
	/**
	 * @var string The version of this gateway
	 */

	private $meta;


	/**
	 * Construct a new merchant gateway
	 */
	public function __construct()
	{

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
	public function setCurrency($currency)
	{
		$this->currency = $currency;
	}

	/**
	 * Create and return the view content required to modify the settings of this gateway
	 *
	 * @param array $meta An array of meta (settings) data belonging to this gateway
	 * @return string HTML content containing the fields to update the meta data for this gateway
	 */
	public function getSettings(array $meta = null)
	{
		$this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

		// Load the helpers required for this view
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
	public function editSettings(array $meta)
	{

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

		// Set checkbox if not set
		if (!isset($meta['dev_mode'])) {
			$meta['dev_mode'] = 'false';
		}

		$this->Input->setRules($rules);

		// Validate the given meta data to ensure it meets the requirements
		$this->Input->validates($meta);

		// Return the meta data, no changes required regardless of success or failure for this gateway
		return $meta;
	}

	/**
	 * Returns an array of all fields to encrypt when storing in the database
	 *
	 * @return array An array of the field names to encrypt when storing in the database
	 */
	public function encryptableFields()
	{

		return ['store_id', 'store_password'];
	}

	/**
	 * Sets the meta data for this particular gateway
	 *
	 * @param array $meta An array of meta data to set for this gateway
	 */
	public function setMeta(array $meta = null)
	{
		$this->meta = $meta;
	}

	/**
	 * Returns all HTML markup required to render an authorization and capture payment form
	 *
	 * @param array $contact_info An array of contact info including:
	 * 	- id The contact ID
	 * 	- client_id The ID of the client this contact belongs to
	 * 	- user_id The user ID this contact belongs to (if any)
	 * 	- contact_type The type of contact
	 * 	- contact_type_id The ID of the contact type
	 * 	- first_name The first name on the contact
	 * 	- last_name The last name on the contact
	 * 	- title The title of the contact
	 * 	- company The company name of the contact
	 * 	- address1 The address 1 line of the contact
	 * 	- address2 The address 2 line of the contact
	 * 	- city The city of the contact
	 * 	- state An array of state info including:
	 * 		- code The 2 or 3-character state code
	 * 		- name The local name of the country
	 * 	- country An array of country info including:
	 * 		- alpha2 The 2-character country code
	 * 		- alpha3 The 3-cahracter country code
	 * 		- name The english name of the country
	 * 		- alt_name The local name of the country
	 * 	- zip The zip/postal code of the contact
	 * @param float $amount The amount to charge this contact
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	- id The ID of the invoice being processed
	 * 	- amount The amount being processed for this invoice (which is included in $amount)
	 * @param array $options An array of options including:
	 * 	- description The Description of the charge
	 * 	- return_url The URL to redirect users to after a successful payment
	 * 	- recur An array of recurring info including:
	 * 		- amount The amount to recur
	 * 		- term The term to recur
	 * 		- period The recurring period (day, week, month, year, onetime) used in conjunction with term in order to determine the next recurring payment
	 * @return string HTML markup required to render an authorization and capture payment form
	 */
	public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
	{
		// Load the models required
		Loader::loadModels($this, ['Clients', 'Contacts']);

		// Load the helpers required
		Loader::loadHelpers($this, ['Html']);
		// Force 2-decimal places only
		$amount = number_format($amount, 2, '.', '');

		// Get client data
		$client = $this->Clients->get($contact_info['client_id']);
		//print_r($client);
		// Get client phone number
		$contact_numbers = $this->Contacts->getNumbers($client->contact_id);

		$client_phone = '';
		foreach ($contact_numbers as $contact_number) {
			switch ($contact_number->location) {
				case 'home':
					// Set home phone number
					if ($contact_number->type == 'phone') {
						$client_phone = $contact_number->number;
					}
					break;
				case 'work':
					// Set work phone/fax number
					if ($contact_number->type == 'phone') {
						$client_phone = $contact_number->number;
					}
					// No break?
				case 'mobile':
					// Set mobile phone number
					if ($contact_number->type == 'phone') {
						$client_phone = $contact_number->number;
					}
					break;
			}
		}
		if (!empty($client_phone)) {
			$client_phone = preg_replace('/[^0-9]/', '', $client_phone);
		}

		// Set all invoices to pay
		if (isset($invoice_amounts) && is_array($invoice_amounts)) {
			$invoices = $this->serializeInvoices($invoice_amounts);
		}
		$return_url = (isset($options['return_url']) ? $options['return_url'] : null);

		// Parse the URL
		$url_components = parse_url($return_url);

		// Remove the 'client_id' parameter
		if (isset($url_components['query'])) {
			// Parse the query string into an array
			parse_str($url_components['query'], $query_params);

			// Remove the 'client_id' parameter
			unset($query_params['client_id']);

			// Rebuild the query string
			$new_query = http_build_query($query_params);

			// Update the URL with the new query string
			$url_components['query'] = !empty($new_query) ? $new_query : null;
		}

		// Rebuild the URL
		$new_url = $url_components['scheme'] . '://' . $url_components['host'] . $url_components['path'];

		// Add back the 'query' component if it exists
		if (!empty($url_components['query'])) {
			$new_url .= '?' . $url_components['query'];
		}

		// Add back the 'fragment' component if it exists
		if (!empty($url_components['fragment'])) {
			$new_url .= '#' . $url_components['fragment'];
		}

		// Trim any extra characters (optional)
		$return_url = trim($new_url);

		if ($this->meta['dev_mode'] == 'false') {

			$this->url = 'https://www.engine.shurjopayment.com/';
		} else {
			$this->url = 'https://www.sandbox.shurjopayment.com/';
		}
		$token = json_decode($this->gettoken($this->meta['store_id'], $this->meta['store_password'], $this->url), true);
		$bear_token = $token['token'];
		$store_id = $token['store_id'];
		$params = json_encode(
			array(
				'token' => $bear_token,
				'store_id' => $store_id,
				'currency' => ($this->currency ?? null),
				'return_url' => $return_url,
				'cancel_url' => $return_url,
				'amount' => ($amount ?? null),
				// Order information
				'prefix' => $this->meta['store_prefix'],
				'order_id' => $this->meta['store_prefix'] . uniqid(),
				'discsount_amount' => 0,
				'disc_percent' => 0,
				// Customer information
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
			)
		);

		$header = array(
			'Content-Type:application/json',
			'Authorization: Bearer ' . $bear_token
		);
		$this->log((isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null), serialize($params), 'input', true);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url . 'api/secret-pay');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);
		if ($response === false) {
			echo json_encode(curl_error($ch));
		}

		$request = json_decode($response);
		curl_close($ch);
		//header('Location: '.$request->checkout_url);

		try {
			if ($request->checkout_url) {
				$this->log((isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null), serialize($request), 'output', true);

				return $this->buildForm($request->checkout_url);
			} else {
				// The api has been responded with an error, set the error
				$this->log((isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null), serialize($request), 'output', false);
				$this->Input->setErrors(
					['api' => ['response' => $response]]
				);

				return null;
			}
		} catch (Exception $e) {
			$this->Input->setErrors(
				['internal' => ['response' => $e->getMessage()]]
			);
		}
	}
	private function serializeInvoices(array $invoices)
	{
		$str = '';
		foreach ($invoices as $i => $invoice) {
			$str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
		}

		return $str;
	}
	public function gettoken($username, $password, $url)
	{
		$url = $url . 'api/get_token';
		$postFields = json_encode(array(
			'username' => $username,
			'password' => $password,
		));

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $postFields,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			),
		));

		$response = curl_exec($curl);

		curl_close($curl);

		return $response;
	}
	private function unserializeInvoices($str)
	{
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
	private function buildForm($post_to)
	{
		$this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

		// Load the helpers required for this view
		Loader::loadHelpers($this, ['Form', 'Html']);

		$this->view->set('post_to', $post_to);

		return $this->view->fetch();
	}
	/**
	 * Validates the incoming POST/GET response from the gateway to ensure it is
	 * legitimate and can be trusted.
	 *
	 * @param array $get The GET data for this request
	 * @param array $post The POST data for this request
	 * @return array An array of transaction data, sets any errors using Input if the data fails to validate
	 *  - client_id The ID of the client that attempted the payment
	 *  - amount The amount of the payment
	 *  - currency The currency of the payment
	 *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
	 *  	- id The ID of the invoice to apply to
	 *  	- amount The amount to apply to the invoice
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the gateway to identify this transaction
	 */
	public function validate(array $get, array $post)
	{ print_r("comming in validate");
		if ($this->meta['dev_mode'] == 'false') {

			$this->url = 'https://engine.shurjopayment.com/';
		} else {
			$this->url = 'https://www.sandbox.shurjopayment.com/';
		}

		$order_id = (isset($post['order_id']) ? $post['order_id'] : null);


		$token = json_decode($this->gettoken($this->meta['store_id'], $this->meta['store_password'], $this->url), true);
		$bear_token = $token['token'];

		$header = array(
			'Content-Type:application/json',
			'Authorization: Bearer ' . $bear_token
		);
		$postFields = json_encode(
			array(
				'order_id' => $order_id
			)
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,  $this->url . 'api/verification/');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/0 (Windows; U; Windows NT 0; zh-CN; rv:3)");
		$response = curl_exec($ch);
		if ($response === false) {
			echo json_encode(curl_error($ch));
		}
		curl_close($ch);
		$data = json_decode($response, true);
      print_r('validate');
     print_r($data);
		if ($data[0]['sp_code']) {
			if ($data[0]['sp_code'] == '1000') {
				$invoices = $data[0]['value1'];
				return [
					'client_id' => $data[0]['value2'],
					'amount' => $data,
					'currency' => $data[0]['currency'],
					'status' => "approved",
					'reference_id' => null,
					'transaction_id' => $data[0]['bank_trx_id'],
					'invoices' => $this->unserializeInvoices($invoices),
				];
			}
		}
	}

	/**
	 * Returns data regarding a success transaction. This method is invoked when
	 * a client returns from the non-merchant gateway's web site back to Blesta.
	 *
	 * @param array $get The GET data for this request
	 * @param array $post The POST data for this request
	 * @return array An array of transaction data, may set errors using Input if the data appears invalid
	 *  - client_id The ID of the client that attempted the payment
	 *  - amount The amount of the payment
	 *  - currency The currency of the payment
	 *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
	 *  	- id The ID of the invoice to apply to
	 *  	- amount The amount to apply to the invoice
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- transaction_id The ID returned by the gateway to identify this transaction
	 */
	public function success(array $get, array $post)
	{   print_r("comming in success");
		if ($this->meta['dev_mode'] == 'false') {

			$this->url = 'https://engine.shurjopayment.com/';
		} else {
			$this->url = 'https://www.sandbox.shurjopayment.com/';
		}

		$order_id = (isset($get['order_id']) ? $get['order_id'] : null);
		$client_id = (isset($get['client_id']) ? $get['client_id'] : null);

		$token = json_decode($this->gettoken($this->meta['store_id'], $this->meta['store_password'], $this->url), true);
		$bear_token = $token['token'];

		$header = array(
			'Content-Type:application/json',
			'Authorization: Bearer ' . $bear_token
		);
		$postFields = json_encode(
			array(
				'order_id' => $order_id
			)
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,  $this->url . 'api/verification/');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/0 (Windows; U; Windows NT 0; zh-CN; rv:3)");
		$response = curl_exec($ch);
		if ($response === false) {
			echo json_encode(curl_error($ch));
		}
		curl_close($ch);
		$data = json_decode($response, true);
     print_r('success');
		print_r($data);
		if ($data[0]['sp_code']) {
			if ($data[0]['sp_code'] == '1000') {


				$trxdata = [
					'client_id' => (isset($data[0]['value2']) ? $data[0]['value2'] : null),
					'amount' => (isset($data[0]['amount']) ? $data[0]['amount'] : null),
					'currency' => (isset($data[0]['currency']) ? $data[0]['currency'] : null),
					'status' => 'approved',
					'transaction_id' => (isset($data[0]['bank_trx_id']) ? $data[0]['bank_trx_id'] : null),
					'sp_order_id' => (isset($data[0]['invoice_no']) ? $data[0]['invoice_no'] : null),
					'invoices' => $this->unserializeInvoices((isset($data[0]['value1']) ? $data[0]['value1'] : null)),
				];
				$this->updateDatabase($trxdata);
				return $trxdata;
			} elseif ($data[0]['sp_code'] == '1002') {
				$this->Input->setErrors([
					'payment' => ['canceled' => Language::_('shurjopay.!error.payment.canceled', true)]
				]);
			} elseif ($data[0]['sp_code'] == '1068') {
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
	private function updateDatabase($transaction_data)
	{
		$date_closed = date('Y-m-d H:i:s');
		$amount = $transaction_data['amount'];
		$currency = $transaction_data['currency'];
		$id_value = $transaction_data['invoices'][0]['id'];
		$client_id = $transaction_data['client_id'];
		$note_private = "shurjopay";

		$type = "other";
		$transaction_type_id = 5;
		$account_id = null;
		$gateway_id = 3;
		$transaction_id = $transaction_data['transaction_id'];
		$parent_transaction_id = isset($transaction_data['parent_transaction_id']) ? $transaction_data['parent_transaction_id'] : 'NULL'; // Handle NULL values
		$reference_id = isset($transaction_data['sp_order_id']) ? $transaction_data['reference_id'] : 'NULL'; // Handle NULL values
		$message = "shurjoPay transection";
		$status = "approved";
		$date_added = date('Y-m-d H:i:s'); // Use current date and time for the added date
		
		$sqlinsert = "INSERT INTO transactions (client_id, amount, currency, type, transaction_type_id, account_id, gateway_id, transaction_id, parent_transaction_id, reference_id, message, status, date_added) 
		VALUES (
			$client_id,
			$amount,
			'$currency',
			'$type',
			$transaction_type_id,
			" . ($account_id === null ? 'NULL' : $account_id) . ",
			" . ($gateway_id === null ? 'NULL' : $gateway_id) . ",
			'$transaction_id',
			" . ($parent_transaction_id === null ? 'NULL' : $parent_transaction_id) . ",
			" . ($reference_id === null ? 'NULL' : "'$reference_id'") . ",
			'$message',
			'$status',
			'$date_added'
		)";
		execute($sqlinsert);
		// Prepare the SQL query with proper quoting
		$sql = "UPDATE invoices SET 
			currency = '$currency',
			note_private = '$note_private',
			date_closed = '$date_closed',
			paid = $amount
			WHERE id_value = $id_value AND client_id = $client_id";
	
		execute($sql);
	}
	
	/**
	 * Captures a previously authorized payment
	 *
	 * @param string $reference_id The reference ID for the previously authorized transaction
	 * @param string $transaction_id The transaction ID for the previously authorized transaction
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function capture($reference_id, $transaction_id, $amount, array $invoice_amounts = null)
	{

		#
		# TODO: Return transaction data, if possible
		#

		$this->Input->setErrors($this->getCommonError("unsupported"));
	}

	/**
	 * Void a payment or authorization
	 *
	 * @param string $reference_id The reference ID for the previously submitted transaction
	 * @param string $transaction_id The transaction ID for the previously submitted transaction
	 * @param string $notes Notes about the void that may be sent to the client by the gateway
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function void($reference_id, $transaction_id, $notes = null)
	{

		#
		# TODO: Return transaction data, if possible
		#

		$this->Input->setErrors($this->getCommonError("unsupported"));
	}

	/**
	 * Refund a payment
	 *
	 * @param string $reference_id The reference ID for the previously submitted transaction
	 * @param string $transaction_id The transaction ID for the previously submitted transaction
	 * @param float $amount The amount to refund this card
	 * @param string $notes Notes about the refund that may be sent to the client by the gateway
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function refund($reference_id, $transaction_id, $amount, $notes = null)
	{

		#
		# TODO: Return transaction data, if possible
		#

		$this->Input->setErrors($this->getCommonError("unsupported"));
	}
}
