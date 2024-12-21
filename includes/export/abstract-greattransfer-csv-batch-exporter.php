<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GreatTransfer_CSV_Exporter', false ) ) {
	require_once GREATTRANSFER_ABSPATH . 'includes/export/abstract-greattransfer-csv-exporter.php';
}

abstract class GreatTransfer_CSV_Batch_Exporter extends GreatTransfer_CSV_Exporter {

	protected $page = 1;

	public function __construct() {
		$this->column_names = $this->get_default_column_names();
	}

	protected function get_file_path() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . $this->get_filename();
	}

	protected function get_headers_row_file_path() {
		return $this->get_file_path() . '.headers';
	}

	public function get_file() {
		$file = '';
		if ( @file_exists( $this->get_file_path() ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$file = @file_get_contents( $this->get_file_path() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} else {
			@file_put_contents( $this->get_file_path(), '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@chmod( $this->get_file_path(), 0664 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return $file;
	}

	public function generate_file() {
		if ( 1 === $this->get_page() ) {
			@unlink( $this->get_file_path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged

			$this->get_file();
		}
		$this->prepare_data_to_export();
		$this->write_csv_data( $this->get_csv_data() );
	}

	protected function write_csv_data( $data ) {

		if ( ! file_exists( $this->get_file_path() ) || ! is_writeable( $this->get_file_path() ) ) {
			greattransfer_get_logger()->error(
				sprintf(
					/* translators: %s is file path. */
					__( 'Unable to create or write to %s during CSV export. Please check file permissions.', 'greattransfer' ),
					esc_html( $this->get_file_path() )
				)
			);
			return false;
		}

		$fopen_mode = apply_filters( 'greattransfer_csv_exporter_fopen_mode', 'a+' );
		$fp         = fopen( $this->get_file_path(), $fopen_mode );

		if ( $fp ) {
			fwrite( $fp, $data );
			fclose( $fp );
		}

		if ( 100 === $this->get_percent_complete() ) {
			$header = chr( 239 ) . chr( 187 ) . chr( 191 ) . $this->export_column_headers();

			@file_put_contents( $this->get_headers_row_file_path(), $header ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	public function get_page() {
		return $this->page;
	}

	public function set_page( $page ) {
		$this->page = absint( $page );
	}

	public function get_total_exported() {
		return ( ( $this->get_page() - 1 ) * $this->get_limit() ) + $this->exported_row_count;
	}

	public function get_percent_complete() {
		return $this->total_rows ? (int) floor( ( $this->get_total_exported() / $this->total_rows ) * 100 ) : 100;
	}
}
