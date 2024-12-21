<?php

defined( 'ABSPATH' ) || exit;

class GreatTransfer_Admin {

	public function __construct() {
		add_action( 'init', array( $this, 'includes' ) );

		add_filter( 'admin_body_class', array( $this, 'include_admin_body_class' ), 9999 );
	}

	public function includes() {
		include_once __DIR__ . '/greattransfer-admin-functions.php';
		include_once __DIR__ . '/class-greattransfer-admin-assets.php';
		include_once __DIR__ . '/class-greattransfer-admin-importers.php';
		include_once __DIR__ . '/class-greattransfer-admin-exporters.php';
	}

	public function include_admin_body_class( $classes ) {
		if ( in_array( array( 'greattransfer-wp-version-gte-53', 'greattransfer-wp-version-gte-55' ), explode( ' ', $classes ), true ) ) {
			return $classes;
		}

		$raw_version   = get_bloginfo( 'version' );
		$version_parts = explode( '-', $raw_version );
		$version       = count( $version_parts ) > 1 ? $version_parts[0] : $raw_version;

		if ( $raw_version && version_compare( $version, '5.3', '>=' ) ) {
			$classes .= ' greattransfer-wp-version-gte-53';
		}

		if ( $raw_version && version_compare( $version, '5.5', '>=' ) ) {
			$classes .= ' greattransfer-wp-version-gte-55';
		}

		return $classes;
	}
}

return new GreatTransfer_Admin();
