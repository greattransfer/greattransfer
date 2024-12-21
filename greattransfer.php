<?php
/**
 * Plugin Name: GreatTransfer
 * Description: A content transfer tool to migrate or edit externally. Easily.
 * Version: 1.0.0
 * Author: GreatTransfer
 * Text Domain: greattransfer
 * Domain Path: /languages/
 * Requires at least: 6.6
 * Requires PHP: 7.4
 *
 * @package GreatTransfer
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'GREATTRANSFER_PLUGIN_FILE' ) ) {
	define( 'GREATTRANSFER_PLUGIN_FILE', __FILE__ );
}

require __DIR__ . '/src/Autoloader.php';

if ( ! \GreatTransfer\GreatTransfer\Autoloader::init() ) {
	return;
}

if ( ! class_exists( 'GreatTransfer', false ) ) {
	include_once dirname( GREATTRANSFER_PLUGIN_FILE ) . '/includes/class-greattransfer.php';
}

$GLOBALS['greattransfer_container'] = new GreatTransfer\GreatTransfer\Container();

function GreatTransfer() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return GreatTransfer::instance();
}

function greattransfer_get_container() {
	return $GLOBALS['greattransfer_container'];
}

GreatTransfer();
