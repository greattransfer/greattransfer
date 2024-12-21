<?php
declare( strict_types = 1 );

namespace GreatTransfer\GreatTransfer\Internal\Utilities;

use GreatTransfer\GreatTransfer\Constants;
use GreatTransfer\GreatTransfer\Proxies\LegacyProxy;
use Exception;
use WP_Filesystem_Base;

class FilesystemUtil {

	public static function get_wp_filesystem(): WP_Filesystem_Base {
		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			$initialized = self::initialize_wp_filesystem();

			if ( false === $initialized ) {
				throw new Exception( 'The GreatTransfer filesystem could not be initialized.' );
			}
		}

		return $wp_filesystem;
	}

	public static function get_wp_filesystem_method_or_direct() {
		$proxy = greattransfer_get_container()->get( LegacyProxy::class );
		if ( ! self::constant_exists( 'FS_METHOD' ) && false === $proxy->call_function( 'get_option', 'ftp_credentials' ) && ! self::constant_exists( 'FTP_HOST' ) ) {
			return 'direct';
		}

		$method = $proxy->call_wc_function( 'get_filesystem_method' );
		if ( $method ) {
			return $method;
		}

		return 'direct';
	}

	private static function constant_exists( string $name ): bool {
		return Constants::is_defined( $name ) && ! is_null( Constants::get_constant( $name ) );
	}

	public static function mkdir_p_not_indexable( string $path ): void {
		$wp_fs = self::get_wp_filesystem();

		if ( $wp_fs->is_dir( $path ) ) {
			return;
		}

		if ( ! wp_mkdir_p( $path ) ) {
			throw new \Exception( esc_html( sprintf( 'Could not create directory: %s.', wp_basename( $path ) ) ) );
		}

		$files = array(
			'.htaccess'  => 'deny from all',
			'index.html' => '',
		);

		foreach ( $files as $name => $content ) {
			$wp_fs->put_contents( trailingslashit( $path ) . $name, $content );
		}
	}

	protected static function initialize_wp_filesystem(): bool {
		global $wp_filesystem;

		if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$method      = self::get_wp_filesystem_method_or_direct();
		$initialized = false;

		if ( 'direct' === $method ) {
			$initialized = WP_Filesystem();
		} elseif ( false !== $method ) {
			ob_start();
			$credentials = request_filesystem_credentials( '' );
			ob_end_clean();

			$initialized = $credentials && WP_Filesystem( $credentials );
		}

		return is_null( $initialized ) ? false : $initialized;
	}
}
