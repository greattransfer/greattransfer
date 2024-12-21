<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GREATTRANSFER_CSV_Batch_Exporter', false ) ) {
	include_once GREATTRANSFER_ABSPATH . 'includes/export/abstract-greattransfer-csv-batch-exporter.php';
}

class GreatTransfer_Post_CSV_Exporter extends GreatTransfer_CSV_Batch_Exporter {

	protected $export_type;

	protected $enable_meta_export = false;

	protected $post_taxonomies_to_export = array();

	protected $post_statuses_to_export = array();

	public function __construct( $export_type = null ) {
		global $typenow;

		$this->export_type = is_null( $export_type ) ? $typenow : $export_type;

		$this->post_statuses_to_export = array_keys( $this->get_default_post_statuses() );

		parent::__construct();
	}

	public function title() {
		$post_type_object = get_post_type_object( $this->export_type );

		/* translators: Post/Taxonomy labels name (plural) */
		$title = _x( 'Export %s', 'name (plural)', 'greattransfer' );

		$name = $post_type_object->labels->name;
		if ( ! str_starts_with( $name, '%s' ) ) {
			$name = mb_strtolower( $name );
		}

		return sprintf( $title, $name );
	}

	public function subtitle() {
		$post_type_object = get_post_type_object( $this->export_type );

		/* translators: Post/Taxonomy labels name (plural) */
		$subtitle = __( 'Export %s to a CSV file', 'greattransfer' );

		$name = $post_type_object->labels->name;
		if ( ! str_starts_with( $name, '%s' ) ) {
			$name = mb_strtolower( $name );
		}

		return sprintf( $subtitle, $name );
	}

	public function description() {
		$post_type_object = get_post_type_object( $this->export_type );

		/* translators: Post/Taxonomy labels all_items */
		$description = __(
			'This tool allows you to generate and download a CSV file containing a list of %s.',
			'greattransfer'
		);

		$all_items = $post_type_object->labels->all_items;
		if ( ! str_starts_with( $all_items, '%s' ) ) {
			$all_items = mb_strtolower( $all_items );
		}

		return sprintf( $description, $all_items );
	}

	public function meta_fields() {
		$meta_fields = array();
		if ( class_exists( 'Jet_Engine' ) ) {
			$cpt_items = jet_engine()->cpt->get_items();
			$cpt_items = array_combine( wp_list_pluck( $cpt_items, 'slug' ), $cpt_items );
			if ( isset( $cpt_items[ $this->export_type ] ) ) {
				$cpt = $cpt_items[ $this->export_type ];

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

	public function taxonomies() {
		$taxonomies = array();

		if ( class_exists( 'Jet_Engine' ) ) {
			$cpt_items = jet_engine()->cpt->get_items();
			$cpt_items = array_combine( wp_list_pluck( $cpt_items, 'slug' ), $cpt_items );
			if ( isset( $cpt_items[ $this->export_type ] ) ) {
				$cpt = $cpt_items[ $this->export_type ];

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

	public function enable_meta_export( $enable_meta_export ) {
		$this->enable_meta_export = (bool) $enable_meta_export;
	}

	public function set_post_taxonomies_to_export( $post_taxonomies_to_export ) {
		$taxonomies = array();
		foreach ( $post_taxonomies_to_export as $taxonomy => $term_slugs ) {
			$taxonomies[ sanitize_title_with_dashes( $taxonomy ) ] = array_map(
				'sanitize_title_with_dashes',
				$term_slugs
			);
		}
		$this->post_taxonomies_to_export = $taxonomies;
	}

	public function set_post_statuses_to_export( $post_statuses_to_export ) {
		$this->post_statuses_to_export = array_map( 'sanitize_title_with_dashes', $post_statuses_to_export );
	}

	public function get_default_column_names() {
		$column_names = array(
			'id'         => __( 'ID', 'greattransfer' ),
			'post_title' => __( 'Title', 'greattransfer' ),
		);

		$meta_fields = $this->meta_fields();
		foreach ( $meta_fields as $meta_field_name => $meta_field_title ) {
			$key = 'meta:' . $meta_field_name;

			$column_names[ $key ] = $meta_field_title;
		}

		$taxonomies = $this->taxonomies();
		foreach ( $taxonomies as $taxonomy_name => $taxonomy_labels ) {
			$key = $taxonomy_name . '_ids';

			$column_names[ $key ] = $taxonomy_labels['name'];
		}

		return apply_filters(
			"greattransfer_post_export_{$this->export_type}_default_columns",
			$column_names
		);
	}

	public function get_default_post_statuses() {
		$args          = array( 'show_in_admin_all_list' => true );
		$post_statuses = wp_list_pluck( get_post_stati( $args, 'objects' ), 'label', 'name' );

		if ( class_exists( 'WooCommerce' ) ) {
			$wc_get_order_statuses = array_keys( wc_get_order_statuses() );
			$post_statuses         = array_diff_key( $post_statuses, array_flip( $wc_get_order_statuses ) );
		}

		return apply_filters(
			"greattransfer_post_export_{$this->export_type}_default_post_statuses",
			$post_statuses
		);
	}

	public function prepare_data_to_export() {
		$args = array(
			'post_status'    => $this->post_statuses_to_export,
			'post_type'      => $this->export_type,
			'posts_per_page' => $this->get_limit(),
			'paged'          => $this->get_page(),
			'orderby'        => array(
				'ID' => 'ASC',
			),
			'fields'         => 'all',
		);

		$tax_query = array();

		foreach ( $this->post_taxonomies_to_export as $taxonomy => $terms ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $terms,
			);
		}
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		$the_query = new WP_Query( apply_filters( "greattransfer_post_export_{$this->export_type}_query_args", $args ) );

		$this->total_rows = $the_query->found_posts;
		$this->row_data   = array();

		foreach ( $the_query->posts as $post ) {
			$this->row_data[] = $this->generate_row_data( $post );
		}

		wp_reset_postdata();
	}

	protected function generate_row_data( $post ) {
		$columns = $this->get_column_names();
		$row     = array();

		$default_columns = array_keys( $this->get_default_column_names() );
		foreach ( $columns as $column_id => $column_name ) {
			$value = '';

			if ( strstr( $column_id, ':' ) && ! in_array( $column_id, $default_columns, true ) ) {
				$column_id = current( explode( ':', $column_id ) );
			}

			if ( in_array( $column_id, array( 'meta' ), true ) || ! $this->is_column_exporting( $column_id ) ) {
				continue;
			}

			$taxonomy = str_ends_with( $column_id, '_ids' ) ? preg_replace( '/_ids$/', '', $column_id ) : null;
			if ( $taxonomy ) {
				$column_id = 'taxonomy_' . $taxonomy;
			}
			if ( has_filter( "greattransfer_post_export_{$this->export_type}_column_{$column_id}" ) ) {
				$value = apply_filters( "greattransfer_post_export_{$this->export_type}_column_{$column_id}", '', $post, $column_id );
			} elseif ( is_callable( array( $this, "get_column_value_{$column_id}" ) ) ) {
				$value = $this->{"get_column_value_{$column_id}"}( $post );
			} elseif ( $taxonomy && is_callable( array( $this, 'get_column_value_taxonomy_ids' ) ) ) {
				$value = $this->{'get_column_value_taxonomy_ids'}( $taxonomy, $post );
			}
			if ( $taxonomy ) {
				$column_id = $taxonomy . '_ids';
			}

			if ( 'description' === $column_id || 'short_description' === $column_id ) {
				$value = $this->filter_description_field( $value );
			}

			$row[ $column_id ] = $value;
		}

		$this->prepare_meta_for_export( $post, $row );

		return apply_filters( 'greattransfer_post_export_row_data', $row, $post, $this );
	}

	protected function prepare_meta_for_export( $post, &$row ) {
		$columns = $this->get_column_names();

		$columns_to_export = $this->get_columns_to_export();

		$default_meta = array();
		foreach ( $columns as $column_id => $column_name ) {
			if ( $columns_to_export && ! in_array( $column_id, $columns_to_export, true ) ) {
				continue;
			}

			if ( ! strstr( $column_id, ':' ) ) {
				continue;
			}
			$parts = explode( ':', $column_id );
			if ( 'meta' === current( $parts ) ) {
				$meta_key = end( $parts );

				$default_meta[ $meta_key ] = $column_name;
			}
		}

		if ( $default_meta || $this->enable_meta_export ) {
			$meta_data_all = array_map( 'current', get_post_meta( $post->ID ) );
		}

		$meta_data = array();
		if ( $default_meta ) {
			foreach ( $meta_data_all as $meta_key => $meta_value ) {
				if ( ! isset( $default_meta[ $meta_key ] ) ) {
					continue;
				}
				$meta_data[ $meta_key ] = $meta_value;
			}
		}

		if ( $this->enable_meta_export ) {
			$meta_data = array_merge( $meta_data, $meta_data_all );
		}

		if ( count( $meta_data ) ) {
			$meta_keys_to_skip = apply_filters( 'greattransfer_post_export_skip_meta_keys', array(), $post );

			$i = 1;
			foreach ( $meta_data as $meta_key => $meta_value ) {
				if ( in_array( $meta_key, $meta_keys_to_skip, true ) ) {
					continue;
				}

				$meta_value = apply_filters( 'greattransfer_post_export_meta_value', $meta_value, $meta_key, $post, $row );

				if ( ! is_scalar( $meta_value ) ) {
					continue;
				}

				$column_key = 'meta:' . esc_attr( $meta_key );
				/* translators: %s: Meta name/number */
				$column_name = sprintf( __( 'Meta: %s', 'greattransfer' ), $meta_key );
				if ( isset( $default_meta[ $meta_key ] ) ) {
					$column_name = $default_meta[ $meta_key ];
				}
				$this->column_names[ $column_key ] = $column_name;
				$row[ $column_key ]                = $meta_value;
				++$i;
			}
		}
	}

	protected function get_column_value_id( $post ) {
		return $post->ID;
	}

	protected function get_column_value_name( $post ) {
		return $post->post_title;
	}

	protected function get_column_value_taxonomy_ids( $taxonomy, $post ) {
		$terms    = wp_get_post_terms( $post->ID, $taxonomy );
		$term_ids = array();
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$term_ids = wp_list_pluck( $terms, 'term_id' );
		}
		return $this->format_term_ids( $term_ids, $taxonomy );
	}
}
