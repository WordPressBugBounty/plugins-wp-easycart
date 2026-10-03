<?php
/**
 * Order screen › Edit order › Shipping address ( free from 6.0.2 ). See order-edit-address.php.
 *
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpec_address_type = 'shipping';
include EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-edit-address.php';
