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

$lang['Shurjopay.name'] = 'ShurjoPay';
$lang['Shurjopay.description'] = 'ShurjoPay is the first online payment gateway in Bangladesh, founded in 2010, enabling seamless e-commerce transactions.';
$lang['Shurjopay.username'] = 'API Username';
$lang['Shurjopay.password'] = 'API Password';
$lang['Shurjopay.prefix'] = 'Merchant Prefix';
$lang['Shurjopay.sandbox'] = 'Test Mode';
$lang['Shurjopay.sandbox_on'] = 'Enable Test Mode';
$lang['Shurjopay.submit'] = 'Pay with ShurjoPay';
$lang['Shurjopay.ipn'] = 'ShurjoPay IPN';
$lang['Shurjopay.ipn_note'] = 'Configure the IPN settings in your ShurjoPay merchant dashboard. Uncheck Test Mode for live transactions.';

// Error messages
$lang['Shurjopay.!error.username.valid'] = 'Please enter a valid API Username.';
$lang['Shurjopay.!error.password.valid'] = 'Please enter a valid API Password.';
$lang['Shurjopay.!error.prefix.valid'] = 'Please enter a valid Merchant Prefix.';
$lang['Shurjopay.!error.token.failed'] = 'Failed to obtain authentication token from ShurjoPay.';
$lang['Shurjopay.!error.api.response'] = 'Invalid or unexpected response from ShurjoPay API.';
$lang['Shurjopay.!error.order.missing'] = 'Order ID is missing or invalid.';
$lang['Shurjopay.!error.payment.canceled'] = 'The payment was canceled. Please visit the client dashboard to retry payment on the invoice.';
$lang['Shurjopay.!error.payment.failed'] = 'The payment failed. Please try again or contact support.';
?>
