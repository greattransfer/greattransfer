<?php

defined( 'ABSPATH' ) || exit;

function greattransfer_importer_generic_mappings( $mappings ) {
	$generic_mappings = array(
		__( 'Title', 'greattransfer' ) => 'name',
	);

	return array_merge( $mappings, $generic_mappings );
}
add_filter( 'greattransfer_csv_post_import_mapping_default_columns', 'greattransfer_importer_generic_mappings' );
