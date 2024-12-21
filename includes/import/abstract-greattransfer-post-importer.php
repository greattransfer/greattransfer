<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GreatTransfer_Importer_Interface', false ) ) {
	include_once GREATTRANSFER_ABSPATH . 'includes/interfaces/class-greattransfer-importer-interface.php';
}

abstract class GreatTransfer_Post_Importer implements GreatTransfer_Importer_Interface {

	protected static $import_type;

	protected $file = '';

	protected $file_position = 0;

	protected $params = array();

	protected $raw_keys = array();

	protected $mapped_keys = array();

	protected $raw_data = array();

	protected $file_positions = array();

	protected $parsed_data = array();

	protected $start_time = 0;

	public function get_raw_keys() {
		return $this->raw_keys;
	}

	public function get_mapped_keys() {
		return ! empty( $this->mapped_keys ) ? $this->mapped_keys : $this->raw_keys;
	}

	public function get_raw_data() {
		return $this->raw_data;
	}

	public function get_parsed_data() {
		return apply_filters( 'greattransfer_post_importer_parsed_data', $this->parsed_data, $this );
	}

	public function get_params() {
		return $this->params;
	}

	public function get_file_position() {
		return $this->file_position;
	}

	public function get_percent_complete() {
		$size = filesize( $this->file );
		if ( ! $size ) {
			return 0;
		}

		return absint( min( floor( ( $this->file_position / $size ) * 100 ), 100 ) );
	}

	private function get_post_type_object() {
		$post_type_object = get_post_type_object( self::$import_type );
		return (object) array(
			'labels' => (object) array(
				'name'          => $post_type_object->labels->name,
				'singular_name' => $post_type_object->labels->singular_name,
				'all_items'     => $post_type_object->labels->all_items,
			),
		);
	}

	protected function get_post_object( $data ) {
		$id = isset( $data['ID'] ) ? absint( $data['ID'] ) : 0;

		if ( ! empty( $data['ID'] ) ) {
			$post = get_post( $id );

			if ( ! $post || self::$import_type !== $post->post_type ) {
				/* translators: %1$s: Post/Taxonomy singular_name, %2$d: post ID */
				$message = __( 'Invalid %1$s ID %2$d.', 'greattransfer' );

				$post_type_object = $this->get_post_type_object();

				$singular_name = $post_type_object->labels->singular_name;
				if ( ! str_starts_with( $message, '%s' ) ) {
					$singular_name = mb_strtolower( $singular_name );
				}

				return new WP_Error(
					'greattransfer_post_csv_importer_invalid_id',
					sprintf( $message, $singular_name, $id ),
					array(
						'id'     => $id,
						'status' => 401,
					)
				);
			}
		} else {
			$post = new WP_Post(
				(object) array(
					'post_type' => self::$import_type,
				)
			);
		}

		return apply_filters( 'greattransfer_post_import_get_post_object', $post, $data );
	}

	protected function process_item( $data ) {
		try {
			do_action( 'greattransfer_post_import_before_process_item', $data );
			$data = apply_filters( 'greattransfer_post_import_process_item_data', $data );

			$post     = $this->get_post_object( $data );
			$updating = false;

			if ( is_wp_error( $post ) ) {
				return $post;
			}

			if ( $post->ID && 'importing' !== $post->post_status ) {
				$updating = true;
			}

			if ( 'importing' === $post->post_status ) {
				$post->post_status = 'publish';
				$post->post_name   = '';
			}

			$taxonomies = $this->taxonomies();

			$taxonomy_props = array();

			$post_props = array_diff_key( $data, array_flip( array( 'meta_data' ) ) );
			foreach ( $post_props as $key => $value ) {
				if ( str_ends_with( $key, '_ids' ) ) {
					$maybe_taxonomy = preg_replace( '/_ids$/', '', $key );
					if ( isset( $taxonomies[ $maybe_taxonomy ] ) ) {
						$taxonomy_props[ $maybe_taxonomy ] = $value;
						unset( $post_props[ $key ] );
					}
				}
			}

			foreach ( $post_props as $key => $value ) {
				$post->$key = $value;
			}

			$object = (object) array(
				'post'       => $post,
				'taxonomies' => $taxonomy_props,
				'meta_input' => array(),
			);

			$this->set_meta_data( $object, $data );

			$object = apply_filters( 'greattransfer_post_import_pre_insert_post_object', $object, $data );

			$result = wp_update_post( $object->post );

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			foreach ( $object->taxonomies as $taxonomy => $term_ids ) {
				$result = wp_set_object_terms( $object->post->ID, $term_ids, $taxonomy );

				if ( is_wp_error( $result ) ) {
					throw new Exception( $result->get_error_message() );
				}
			}

			foreach ( $object->meta_input as $key => $value ) {
				$result = update_post_meta( $object->post->ID, $key, $value );

				if ( is_wp_error( $result ) ) {
					throw new Exception( $result->get_error_message() );
				}
			}

			do_action( 'greattransfer_post_import_inserted_post_object', $object, $data );

			return array(
				'id'      => $object->post->ID,
				'updated' => $updating,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'greattransfer_post_importer_error', $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

	protected function set_meta_data( &$post_object, $data ) {
		if ( isset( $data['meta_data'] ) ) {
			foreach ( $data['meta_data'] as $meta ) {
				$post_object->meta_input[ $meta['key'] ] = $meta['value'];
			}
		}
	}

	public function get_attachment_id_from_url( $url, $post_id ) {
		if ( empty( $url ) ) {
			return 0;
		}

		$id         = 0;
		$upload_dir = wp_upload_dir( null, false );
		$base_url   = $upload_dir['baseurl'] . '/';

		if ( false !== strpos( $url, $base_url ) || false === strpos( $url, '://' ) ) {
			$file = str_replace( $base_url, '', $url );
			$args = array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => '_wp_attached_file',
						'value'   => '^' . $file,
						'compare' => 'REGEXP',
					),
					array(
						'key'     => '_wp_attached_file',
						'value'   => '/' . $file,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_greattransfer_attachment_source',
						'value'   => '/' . $file,
						'compare' => 'LIKE',
					),
				),
			);
		} else {
			$args = array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'value' => $url,
						'key'   => '_greattransfer_attachment_source',
					),
				),
			);
		}

		$ids = get_posts( $args );

		if ( $ids ) {
			$id = current( $ids );
		}

		if ( ! $id && stristr( $url, '://' ) ) {
			$upload = greattransfer_rest_upload_image_from_url( $url );

			if ( is_wp_error( $upload ) ) {
				throw new Exception( esc_html( $upload->get_error_message() ), 400 );
			}

			$id = greattransfer_rest_set_uploaded_image_as_attachment( $upload, $post_id );

			if ( ! wp_attachment_is_image( $id ) ) {
				throw new Exception(
					/* translators: %s: image URL */
					esc_html( sprintf( __( 'Not able to attach "%s".', 'greattransfer' ), $url ) ),
					400
				);
			}

			update_post_meta( $id, '_greattransfer_attachment_source', $url );
		}

		if ( ! $id ) {
			throw new Exception(
				/* translators: %s: image URL */
				esc_html( sprintf( __( 'Unable to use image "%s".', 'greattransfer' ), $url ) ),
				400
			);
		}

		return $id;
	}

	public function get_attribute_taxonomy_id( $raw_name ) {
		global $wpdb, $greattransfer_post_attributes;

		$attribute_labels = wp_list_pluck( greattransfer_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
		$attribute_name   = array_search( $raw_name, $attribute_labels, true );

		if ( ! $attribute_name ) {
			$attribute_name = greattransfer_sanitize_taxonomy_name( $raw_name );
		}

		$attribute_id = greattransfer_attribute_taxonomy_id_by_name( $attribute_name );

		if ( $attribute_id ) {
			return $attribute_id;
		}

		$attribute_id = greattransfer_create_attribute(
			array(
				'name'         => $raw_name,
				'slug'         => $attribute_name,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		if ( is_wp_error( $attribute_id ) ) {
			throw new Exception( esc_html( $attribute_id->get_error_message() ), 400 );
		}

		$taxonomy_name = greattransfer_attribute_taxonomy_name( $attribute_name );
		register_taxonomy(
			$taxonomy_name,
			apply_filters( 'greattransfer_taxonomy_objects_' . $taxonomy_name, array( 'post' ) ),
			apply_filters(
				'greattransfer_taxonomy_args_' . $taxonomy_name,
				array(
					'labels'       => array(
						'name' => $raw_name,
					),
					'hierarchical' => true,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			)
		);

		$greattransfer_post_attributes = array();

		foreach ( greattransfer_get_attribute_taxonomies() as $taxonomy ) {
			$greattransfer_post_attributes[ greattransfer_attribute_taxonomy_name( $taxonomy->attribute_name ) ] = $taxonomy;
		}

		return $attribute_id;
	}

	protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9;
		$current_memory = memory_get_usage( true );
		$return         = false;
		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}
		return apply_filters( 'greattransfer_post_importer_memory_exceeded', $return );
	}

	protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
			$memory_limit = '32000M';
		}
		return wp_convert_hr_to_bytes( $memory_limit );
	}

	protected function time_exceeded() {
		$finish = $this->start_time + apply_filters( 'greattransfer_post_importer_default_time_limit', 20 );
		$return = false;
		if ( time() >= $finish ) {
			$return = true;
		}
		return apply_filters( 'greattransfer_post_importer_time_exceeded', $return );
	}

	protected function explode_values( $value, $separator = ',' ) {
		$value  = str_replace( '\\,', '::separator::', $value );
		$values = explode( $separator, $value );
		$values = array_map( array( $this, 'explode_values_formatter' ), $values );

		return $values;
	}

	protected function explode_values_formatter( $value ) {
		return trim( str_replace( '::separator::', ',', $value ) );
	}

	protected function unescape_data( $value ) {
		$active_content_triggers = array( "'=", "'+", "'-", "'@" );

		if ( in_array( mb_substr( $value, 0, 2 ), $active_content_triggers, true ) ) {
			$value = mb_substr( $value, 1 );
		}

		return $value;
	}
}
