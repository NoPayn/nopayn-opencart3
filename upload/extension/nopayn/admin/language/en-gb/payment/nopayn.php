<?php
// Heading — OpenCart uses $code . '_heading_title' on the extensions listing page
$_['heading_title']                = 'NoPayn Checkout';
$_['nopayn_heading_title']         = 'NoPayn Checkout';

// Text
$_['text_extension']               = 'Extensions';
$_['text_success']                 = 'Success: You have modified NoPayn Checkout settings!';
$_['text_edit']                    = 'Edit NoPayn Checkout';
$_['text_all_zones']               = 'All Zones';
$_['text_payment_methods']         = 'Payment Methods';
$_['text_payment_methods_help']    = 'Enable the payment methods you have been approved for in your NoPayn dashboard.';

// Entry
$_['entry_api_key']                = 'API Key';
$_['entry_api_key_help']           = 'Your NoPayn API key. Found in the NoPayn merchant portal under Settings &gt; API Key.';
$_['entry_order_status']           = 'Completed Order Status';
$_['entry_order_status_help']      = 'Order status after successful payment.';
$_['entry_pending_status']         = 'Pending Order Status';
$_['entry_pending_status_help']    = 'Order status while payment is being processed.';
$_['entry_cancelled_status']       = 'Cancelled Order Status';
$_['entry_cancelled_status_help']  = 'Order status when payment is cancelled, expired, or failed.';
$_['entry_geo_zone']               = 'Geo Zone';
$_['entry_status']                 = 'Status';
$_['entry_sort_order']             = 'Sort Order';
$_['entry_creditcard']             = 'Credit / Debit Card';
$_['entry_creditcard_manual_capture'] = 'Manual Capture (Credit Card)';
$_['entry_creditcard_manual_capture_help'] = 'When enabled, credit card payments are authorized but not captured automatically. Capture happens when the order is completed.';
$_['entry_applepay']               = 'Apple Pay';
$_['entry_googlepay']              = 'Google Pay';
$_['entry_mobilepay']              = 'Vipps / MobilePay';
$_['entry_debug_logging']          = 'Debug Logging';
$_['entry_debug_logging_help']     = 'Enable detailed logging of all NoPayn API requests, responses, and webhook events. Logs are written to the OpenCart log directory.';

// Error
$_['error_permission']             = 'Warning: You do not have permission to modify NoPayn Checkout settings!';
