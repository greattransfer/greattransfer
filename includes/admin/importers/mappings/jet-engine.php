<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Jet_Engine' ) ) {
	return;
}

function greattransfer_importer_jet_engine_mappings( $mappings, $raw, $controller ) {
	$import_type = $controller::get_import_type();
	$meta_fields = greattransfer_jet_engine_meta_fields( $import_type );
	$taxonomies  = greattransfer_jet_engine_taxonomies( $import_type );

	$jet_engine_mappings = array();

	foreach ( $meta_fields as $key => $value ) {
		$key                           = 'meta:' . $key;
		$jet_engine_mappings[ $value ] = $key;
	}

	foreach ( $taxonomies as $taxonomy_name => $taxonomy_labels ) {
		$key  = $taxonomy_name . '_ids';
		$name = $taxonomy_labels['name'];

		$jet_engine_mappings[ $name ] = $key;
	}

	return array_merge( $mappings, $jet_engine_mappings );
}
add_filter( 'greattransfer_csv_post_import_mapping_default_columns', 'greattransfer_importer_jet_engine_mappings', 10, 3 );

function greattransfer_jet_engine_meta_fields( $post_type ) {
	$meta_fields = array();
	if ( class_exists( 'Jet_Engine' ) ) {
		$cpt_items = jet_engine()->cpt->get_items();
		$cpt_items = array_combine( wp_list_pluck( $cpt_items, 'slug' ), $cpt_items );
		if ( isset( $cpt_items[ $post_type ] ) ) {
			$cpt = $cpt_items[ $post_type ];

			$meta_fields_all = $cpt['meta_fields'];
			$meta_boxes      = jet_engine()->meta_boxes->data->get_items();
			foreach ( $meta_boxes as $meta_box ) {
				$args = $meta_box['args'];
				if ( in_array( $cpt['slug'], $args['allowed_post_type'], true ) ) {
					$meta_fields_all = array_merge( $meta_fields_all, $meta_box['meta_fields'] );
				}
			}

			foreach ( $meta_fields_all as $meta_field ) {
				$key = $meta_field['name'];

				$meta_fields[ $key ] = $meta_field['title'];
			}
		}
	}

	return $meta_fields;
}

function greattransfer_jet_engine_taxonomies( $post_type ) {
	$taxonomies = array();

	if ( class_exists( 'Jet_Engine' ) ) {
		$cpt_items = jet_engine()->cpt->get_items();
		$cpt_items = array_combine( wp_list_pluck( $cpt_items, 'slug' ), $cpt_items );
		if ( isset( $cpt_items[ $post_type ] ) ) {
			$cpt = $cpt_items[ $post_type ];

			$taxonomies_all = jet_engine()->taxonomies->get_items();
			foreach ( $taxonomies_all as $taxonomy ) {
				if ( in_array( $cpt['slug'], $taxonomy['object_type'], true ) ) {
					$taxonomy_slug = $taxonomy['slug'];

					$taxonomy = get_taxonomy( $taxonomy_slug );

					$taxonomies[ $taxonomy_slug ] = array(
						'labels' => array(
							'name'          => $taxonomy->labels->name,
							'singular_name' => $taxonomy->labels->singular_name,
							'all_items'     => $taxonomy->labels->all_items,
						),
					);
				}
			}

			$state_taxonomy  = str_replace( '-', '_', sanitize_title( _x( 'State', 'taxonomy slug', 'greattransfer' ) ) );
			$coutry_taxonomy = str_replace( '-', '_', sanitize_title( _x( 'Country', 'taxonomy slug', 'greattransfer' ) ) );

			foreach ( $taxonomies as $key => &$taxonomy ) {
				$taxonomy['order'] = 0;
				if ( $state_taxonomy === $key || $coutry_taxonomy === $key ) {
					$taxonomy['order'] = 1;
				}
			}
			unset( $taxonomy );

			uasort(
				$taxonomies,
				function ( $a, $b ) {
					return $a['order'] <=> $b['order'];
				}
			);

			foreach ( $taxonomies as $key => &$taxonomy ) {
				$taxonomy = $taxonomy['labels'];
			}
		}
	}

	return $taxonomies;
}

function greattransfer_importer_jet_engine_options( $options, $item, $controller ) {
	$import_type  = $controller::get_import_type();
	$meta_fields  = greattransfer_jet_engine_meta_fields( $import_type );
	$taxonomies   = greattransfer_jet_engine_taxonomies( $import_type );
	$meta_options = array();
	foreach ( $options as $key => $value ) {
		if ( str_starts_with( $key, 'meta:' ) ) {
			$meta_options[ $key ] = $value;
			unset( $options[ $key ] );
		}
	}
	foreach ( $meta_fields as $key => $value ) {
		$key = 'meta:' . $key;
		if ( isset( $meta_options[ $key ] ) ) {
			unset( $meta_options[ $key ] );
		}
		$options[ $key ] = $value;
	}
	foreach ( $taxonomies as $taxonomy_name => $taxonomy_labels ) {
		$key = $taxonomy_name . '_ids';

		$options[ $key ] = $taxonomy_labels['name'];
	}
	$options = array_merge( $options, $meta_options );
	return $options;
}
add_filter( 'greattransfer_csv_post_import_mapping_options', 'greattransfer_importer_jet_engine_options', 10, 3 );
