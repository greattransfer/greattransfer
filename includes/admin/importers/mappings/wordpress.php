<?php

defined( 'ABSPATH' ) || exit;

function greattransfer_importer_wordpress_mappings( $mappings ) {
	$wp_mappings = array(
		'post_id'    => 'id',
		'post_title' => 'name',
	);

	return array_merge( $mappings, $wp_mappings );
}
add_filter( 'greattransfer_csv_post_import_mapping_default_columns', 'greattransfer_importer_wordpress_mappings' );
