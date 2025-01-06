<?php
/**
 * Plugin Name: GreatTransfer
 * Description: A content transfer tool to migrate or edit externally. Easily.
 * Version: 1.1.0
 * Author: GreatTransfer
 * Text Domain: greattransfer
 * Domain Path: /languages/
 * Requires at least: 6.6
 * Requires PHP: 7.4
 *
 * @package GreatTransfer
 */
declare(strict_types=1);

use GilbertoTavares\GreatTransfer\ImportExport;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'GREATTRANSFER_PLUGIN_FILE' ) ) {
	define( 'GREATTRANSFER_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/vendor/autoload.php';

function GreatTransfer(): GreatTransfer { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return GreatTransfer::get_instance();
}

final class GreatTransfer {

	private static ?GreatTransfer $instance = null;

	public function __construct() {
		new ImportExport( GREATTRANSFER_PLUGIN_FILE );
	}

	public static function get_instance(): GreatTransfer {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

GreatTransfer();
