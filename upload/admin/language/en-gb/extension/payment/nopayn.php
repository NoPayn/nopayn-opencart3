<?php
// Heading
$_['heading_title']                          = 'Cost+ - Global Settings';
$_['nopayn_heading_title']                   = 'Cost+ - Global Settings';

// Text
$_['text_extension']                         = 'Extensions';
$_['text_success']                           = 'Success: You have modified Cost+ global settings!';
$_['text_edit']                              = 'Edit Cost+ - Global Settings';
$_['text_nopayn']                            = '<a target="_blank" href="https://docs.costplus.io"><img src="view/image/payment/costpluslogo_blackgreen.png" alt="Cost+" title="Cost+" style="max-width: 140px; max-height: 32px; width: auto; height: auto; border: 1px solid #EEEEEE;" /></a>';
$_['text_all_zones']                         = 'All Zones';
$_['text_enabled']                           = 'Enabled';
$_['text_disabled']                          = 'Disabled';
$_['text_payment_methods']                   = 'Available Cost+ Methods';
$_['text_payment_methods_help']              = 'Enable the Cost+ methods your merchant account is approved to offer. The separate Cost+ checkout modules use these switches.';
$_['text_checkout_modules_help']             = 'Install and configure the separate Cost+ payment extensions to control checkout labels, geo zones, and sort order. Apple Pay and Google Pay now use separate checkout modules.';

// Entry
$_['entry_api_key']                          = 'API Key';
$_['entry_expiration_minutes']               = 'Checkout Expiry (Minutes)';
$_['entry_order_status']                     = 'Completed Order Status';
$_['entry_pending_status']                   = 'Pending Order Status';
$_['entry_cancelled_status']                 = 'Cancelled Order Status';
$_['entry_creditcard']                       = 'Credit / Debit Card';
$_['entry_creditcard_manual_capture']        = 'Manual Capture (Credit Card)';
$_['entry_applepay']                         = 'Apple Pay';
$_['entry_googlepay']                        = 'Google Pay';
$_['entry_mobilepay']                        = 'Vipps / MobilePay';
$_['entry_swish']                            = 'Swish';
$_['entry_debug_logging']                    = 'Debug Logging';

// Help
$_['help_api_key']                           = 'Your Cost+ API key from the Cost+ merchant portal.';
$_['help_expiration_minutes']                = 'How many minutes a hosted payment session may stay open before Cost+ expires it. Default: 5. The OpenCart order will move from pending to your cancelled status when the expiry webhook reaches the store.';
$_['help_order_status']                      = 'Order status applied after a successful payment.';
$_['help_pending_status']                    = 'Order status while the payment is processing.';
$_['help_cancelled_status']                  = 'Order status when the payment is cancelled, expired, or fails.';
$_['help_creditcard_manual_capture']         = 'When enabled, credit card payments are authorised first and captured after the payment completes.';
$_['help_debug_logging']                     = 'Write Cost+ API requests, responses, and webhook events to the store log.';

// Error
$_['error_permission']                       = 'Warning: You do not have permission to modify Cost+ global settings!';
