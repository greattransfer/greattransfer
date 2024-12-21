<?php

defined( 'ABSPATH' ) || exit;

function greattransfer_is_file_valid_csv( $file, $check_path = true ) {
	$check_import_file_path = apply_filters( 'greattransfer_csv_importer_check_import_file_path', true, $file );

	if ( $check_path && $check_import_file_path && false !== stripos( $file, '://' ) ) {
		return false;
	}

	$valid_filetypes = apply_filters(
		'greattransfer_csv_import_valid_filetypes',
		array(
			'csv' => 'text/csv',
			'txt' => 'text/plain',
		)
	);

	$filetype = wp_check_filetype( $file, $valid_filetypes );

	if ( in_array( $filetype['type'], $valid_filetypes, true ) ) {
		return true;
	}

	return false;
}
