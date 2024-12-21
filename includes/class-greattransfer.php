<?php

defined( 'ABSPATH' ) || exit;

final class GreatTransfer {

	public $version = '1.0.0';

	protected static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __clone() {
		greattransfer_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'greattransfer' ), '1.0' );
	}

	public function __wakeup() {
		greattransfer_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'greattransfer' ), '1.0' );
	}

	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	private function define_constants() {
		if ( ! defined( 'GREATTRANSFER_ABSPATH' ) ) {
			define( 'GREATTRANSFER_ABSPATH', dirname( GREATTRANSFER_PLUGIN_FILE ) . '/' );
		}
		if ( ! defined( 'GREATTRANSFER_PLUGIN_BASENAME' ) ) {
			define( 'GREATTRANSFER_PLUGIN_BASENAME', plugin_basename( GREATTRANSFER_PLUGIN_FILE ) );
		}
		if ( ! defined( 'GREATTRANSFER_VERSION' ) ) {
			define( 'GREATTRANSFER_VERSION', $this->version );
		}
	}

	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
		}
	}

	public function includes() {
		include_once GREATTRANSFER_ABSPATH . 'includes/greattransfer-core-functions.php';

		if ( $this->is_request( 'admin' ) ) {
			include_once GREATTRANSFER_ABSPATH . 'includes/admin/class-greattransfer-admin.php';
		}
	}

	public function init() {
		$this->load_plugin_textdomain();
	}

	public function load_plugin_textdomain() {
		$locale = determine_locale();

		$locale = apply_filters( 'plugin_locale', $locale, 'greattransfer' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		unload_textdomain( 'greattransfer', true );
		load_textdomain( 'greattransfer', dirname( GREATTRANSFER_PLUGIN_FILE ) . '/languages/' . $locale . '.mo' );
	}

	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', GREATTRANSFER_PLUGIN_FILE ) );
	}

	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( GREATTRANSFER_PLUGIN_FILE ) );
	}
}
