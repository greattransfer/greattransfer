<?php
declare( strict_types = 1 );

namespace GreatTransfer\GreatTransfer\Internal\Admin\ImportExport;

use GreatTransfer\GreatTransfer\Internal\Traits\AccessiblePrivateMethods;
use GreatTransfer\GreatTransfer\Internal\Utilities\FilesystemUtil;

class CSVUploadHelper {

	use AccessiblePrivateMethods;

	protected static function add_filter( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		self::process_callback_before_hooking( $callback );
		add_filter( $hook_name, $callback, $priority, $accepted_args );
	}

	protected static function process_callback_before_hooking( $callback ): void {
		if ( ! is_array( $callback ) || count( $callback ) < 2 ) {
			return;
		}

		$first_item = $callback[0];
		if ( __CLASS__ === $first_item ) {
			static::mark_static_method_as_accessible( $callback[1] );
		} elseif ( is_object( $first_item ) && get_class( $first_item ) === __CLASS__ ) {
			$first_item->mark_method_as_accessible( $callback[1] );
		}
	}

	protected function get_import_subdir_name() {
		return 'greattransfer-imports';
	}

	public function get_import_dir( $create = true ) {
		$wp_upload_dir = wp_upload_dir( null, $create );
		if ( $wp_upload_dir['error'] ) {
			throw new \Exception( esc_html( $wp_upload_dir['error'] ) );
		}

		$upload_dir = trailingslashit( $wp_upload_dir['basedir'] ) . $this->get_import_subdir_name();
		if ( $create ) {
			FilesystemUtil::mkdir_p_not_indexable( $upload_dir );
		}
		return $upload_dir;
	}

	public function handle_csv_upload( $import_type, $files_index = 'import', $allowed_mime_types = null ) {
		$import_type = sanitize_key( $import_type );
		if ( ! $import_type ) {
			throw new \Exception( 'Import type is invalid.' );
		}

		if ( ! $allowed_mime_types ) {
			$allowed_mime_types = array(
				'csv' => 'text/csv',
				'txt' => 'text/plain',
			);
		}

		$file = $_FILES[ $files_index ] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
		if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			throw new \Exception( esc_html__( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.', 'greattransfer' ) );
		}

		if ( ! function_exists( 'wp_import_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/import.php';
		}

		$this->get_import_dir();

		$file['name'] = $import_type . '-' . $file['name'];

		$overrides_callback = function ( $overrides_ ) use ( $allowed_mime_types ) {
			$overrides_['test_form'] = false;
			$overrides_['test_type'] = true;
			$overrides_['mimes']     = $allowed_mime_types;
			return $overrides_;
		};

		self::add_filter( 'upload_dir', array( $this, 'override_upload_dir' ) );
		self::add_filter( 'wp_unique_filename', array( $this, 'override_unique_filename' ), 0, 2 );
		self::add_filter( 'wp_handle_upload_overrides', $overrides_callback, 999 );
		self::add_filter( 'wp_handle_upload_prefilter', array( $this, 'remove_txt_from_uploaded_file' ), 0 );

		$orig_files_import = $_FILES['import'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
		$_FILES['import']  = $file;

		$upload = wp_import_handle_upload();

		remove_filter( 'upload_dir', array( $this, 'override_upload_dir' ) );
		remove_filter( 'wp_unique_filename', array( $this, 'override_unique_filename' ), 0 );
		remove_filter( 'wp_handle_upload_overrides', $overrides_callback, 999 );
		remove_filter( 'wp_handle_upload_prefilter', array( $this, 'remove_txt_from_uploaded_file' ), 0 );

		if ( $orig_files_import ) {
			$_FILES['import'] = $orig_files_import;
		} else {
			unset( $_FILES['import'] );
		}

		if ( ! empty( $upload['error'] ) ) {
			throw new \Exception( esc_html( $upload['error'] ) );
		}

		if ( ! wc_is_file_valid_csv( $upload['file'], false ) ) {
			wp_delete_attachment( $file['id'], true );
			throw new \Exception( esc_html__( 'Invalid file type for a CSV import.', 'greattransfer' ) );
		}

		return $upload;
	}

	private function override_upload_dir( $uploads ): array {
		$new_subdir = '/' . $this->get_import_subdir_name();

		$uploads['path']   = $uploads['basedir'] . $new_subdir;
		$uploads['url']    = $uploads['baseurl'] . $new_subdir;
		$uploads['subdir'] = $new_subdir;

		return $uploads;
	}

	private function override_unique_filename( string $filename, string $ext ): string {
		$length = min( 10, 255 - strlen( $filename ) - 1 );
		if ( 1 < $length ) {
			$suffix   = strtolower( wp_generate_password( $length, false, false ) );
			$filename = substr( $filename, 0, strlen( $filename ) - strlen( $ext ) ) . '-' . $suffix . $ext;
		}

		return $filename;
	}

	private function remove_txt_from_uploaded_file( array $file ): array {
		$file['name'] = substr( $file['name'], 0, -4 );
		return $file;
	}
}
