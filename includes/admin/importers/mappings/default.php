<?php

defined( 'ABSPATH' ) || exit;

function greattransfer_importer_current_locale() {
	$locale = get_locale();
	if ( function_exists( 'get_user_locale' ) ) {
		$locale = get_user_locale();
	}

	return $locale;
}

function greattransfer_importer_default_english_mappings( $mappings ) {
	if ( 'en_US' === greattransfer_importer_current_locale() && is_array( $mappings ) && count( $mappings ) > 0 ) {
		return $mappings;
	}

	$new_mappings = array(
		'ID'    => 'ID',
		'Name'  => 'post_title',
		'Title' => 'post_title',
	);

	return array_merge( $mappings, $new_mappings );
}
add_filter( 'greattransfer_csv_post_import_mapping_default_columns', 'greattransfer_importer_default_english_mappings', 100 );

function greattransfer_importer_default_special_english_mappings( $mappings ) {
	if ( 'en_US' === greattransfer_importer_current_locale() && is_array( $mappings ) && count( $mappings ) > 0 ) {
		return $mappings;
	}

	$new_mappings = array(
		'Meta: %s' => 'meta:',
	);

	return array_merge( $mappings, $new_mappings );
}
add_filter( 'greattransfer_csv_post_import_mapping_special_columns', 'greattransfer_importer_default_special_english_mappings', 100 );
