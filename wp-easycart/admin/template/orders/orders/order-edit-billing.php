<?php
/**
 * Order screen › Edit order › Billing address ( free from 6.0.2 ). See order-edit-address.php.
 *
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpec_address_type = 'billing';
include EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-edit-address.php';
