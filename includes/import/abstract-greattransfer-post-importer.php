<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GreatTransfer_Importer_Interface', false ) ) {
	include_once GREATTRANSFER_ABSPATH . 'includes/interfaces/class-greattransfer-importer-interface.php';
}

abstract class GreatTransfer_Post_Importer implements GreatTransfer_Importer_Interface {

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
		return apply_filters( 'greattransfer_product_importer_parsed_data', $this->parsed_data, $this );
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

	protected function get_product_object( $data ) {
		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( isset( $data['type'] ) ) {
			if ( ! array_key_exists( $data['type'], GreatTransfer_Admin_Exporters::get_product_types() ) ) {
				return new WP_Error( 'greattransfer_product_importer_invalid_type', __( 'Invalid product type.', 'greattransfer' ), array( 'status' => 401 ) );
			}

			try {
				if ( 'variation' === $data['type'] ) {
					$id = wp_update_post(
						array(
							'ID'        => $id,
							'post_type' => 'product_variation',
						)
					);
				}

				$product = gettransfer_get_product_object( $data['type'], $id );
			} catch ( WC_Data_Exception $e ) {
				return new WP_Error( 'greattransfer_product_csv_importer_' . $e->getErrorCode(), $e->getMessage(), array( 'status' => 401 ) );
			}
		} elseif ( ! empty( $data['id'] ) ) {
			$product = gettransfer_get_product( $id );

			if ( ! $product ) {
				return new WP_Error(
					'greattransfer_product_csv_importer_invalid_id',
					/* translators: %d: product ID */
					sprintf( __( 'Invalid product ID %d.', 'greattransfer' ), $id ),
					array(
						'id'     => $id,
						'status' => 401,
					)
				);
			}
		} else {
			$product = gettransfer_get_product_object( 'simple', $id );
		}

		return apply_filters( 'greattransfer_product_import_get_product_object', $product, $data );
	}

	protected function process_item( $data ) {
		try {
			do_action( 'greattransfer_product_import_before_process_item', $data );
			$data = apply_filters( 'greattransfer_product_import_process_item_data', $data );

			if ( empty( $data['id'] ) && ! empty( $data['sku'] ) ) {
				$product_id = gettransfer_get_product_id_by_sku( $data['sku'] );

				if ( $product_id ) {
					$data['id'] = $product_id;
				}
			}

			$object   = $this->get_product_object( $data );
			$updating = false;

			if ( is_wp_error( $object ) ) {
				return $object;
			}

			if ( $object->get_id() && 'importing' !== $object->get_status() ) {
				$updating = true;
			}

			if ( 'external' === $object->get_type() ) {
				unset( $data['manage_stock'], $data['stock_status'], $data['backorders'], $data['low_stock_amount'] );
			}
			$is_variation = false;
			if ( 'variation' === $object->get_type() ) {
				if ( isset( $data['status'] ) && -1 === $data['status'] ) {
					$data['status'] = 0;
				}
				$is_variation = true;
			}

			if ( 'importing' === $object->get_status() ) {
				$object->set_status( 'publish' );
				$object->set_slug( '' );
			}

			$result = $object->set_props( array_diff_key( $data, array_flip( array( 'meta_data', 'raw_image_id', 'raw_gallery_image_ids', 'raw_attributes' ) ) ) );

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			if ( 'variation' === $object->get_type() ) {
				$this->set_variation_data( $object, $data );
			} else {
				$this->set_product_data( $object, $data );
			}

			$this->set_image_data( $object, $data );
			$this->set_meta_data( $object, $data );

			$object = apply_filters( 'greattransfer_product_import_pre_insert_product_object', $object, $data );
			$object->save();

			do_action( 'greattransfer_product_import_inserted_product_object', $object, $data );

			return array(
				'id'           => $object->get_id(),
				'updated'      => $updating,
				'is_variation' => $is_variation,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'greattransfer_product_importer_error', $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

	protected function set_image_data( &$product, $data ) {
		if ( isset( $data['raw_image_id'] ) ) {
			$attachment_id = $this->get_attachment_id_from_url( $data['raw_image_id'], $product->get_id() );
			$product->set_image_id( $attachment_id );
			gettransfer_product_attach_featured_image( $attachment_id, $product );
		}

		if ( isset( $data['raw_gallery_image_ids'] ) ) {
			$gallery_image_ids = array();

			foreach ( $data['raw_gallery_image_ids'] as $image_id ) {
				$gallery_image_ids[] = $this->get_attachment_id_from_url( $image_id, $product->get_id() );
			}
			$product->set_gallery_image_ids( $gallery_image_ids );
		}
	}

	protected function set_meta_data( &$product, $data ) {
		if ( isset( $data['meta_data'] ) ) {
			foreach ( $data['meta_data'] as $meta ) {
				$product->update_meta_data( $meta['key'], $meta['value'] );
			}
		}
	}

	protected function set_product_data( &$product, $data ) {
		if ( isset( $data['raw_attributes'] ) ) {
			$attributes          = array();
			$default_attributes  = array();
			$existing_attributes = $product->get_attributes();

			foreach ( $data['raw_attributes'] as $position => $attribute ) {
				$attribute_id = 0;

				if ( ! empty( $attribute['taxonomy'] ) ) {
					$attribute_id = $this->get_attribute_taxonomy_id( $attribute['name'] );
				}

				if ( isset( $attribute['visible'] ) ) {
					$is_visible = $attribute['visible'];
				} else {
					$is_visible = 1;
				}

				$attribute_name = $attribute_id ? gettransfer_attribute_taxonomy_name_by_id( $attribute_id ) : $attribute['name'];

				$is_variation = 0;

				if ( $existing_attributes ) {
					foreach ( $existing_attributes as $existing_attribute ) {
						if ( $existing_attribute->get_name() === $attribute_name ) {
							$is_variation = $existing_attribute->get_variation();
							break;
						}
					}
				}

				if ( $attribute_id ) {
					if ( isset( $attribute['value'] ) ) {
						$options = array_map( 'gettransfer_sanitize_term_text_based', $attribute['value'] );
						$options = array_filter( $options, 'strlen' );
					} else {
						$options = array();
					}

					if ( ! empty( $attribute['default'] ) && in_array( $attribute['default'], $options, true ) ) {
						$default_term = get_term_by( 'name', $attribute['default'], $attribute_name );

						if ( $default_term && ! is_wp_error( $default_term ) ) {
							$default = $default_term->slug;
						} else {
							$default = sanitize_title( $attribute['default'] );
						}

						$default_attributes[ $attribute_name ] = $default;
						$is_variation                          = 1;
					}

					if ( ! empty( $options ) ) {
						$attribute_object = new GreatTransfer_Post_Attribute();
						$attribute_object->set_id( $attribute_id );
						$attribute_object->set_name( $attribute_name );
						$attribute_object->set_options( $options );
						$attribute_object->set_position( $position );
						$attribute_object->set_visible( $is_visible );
						$attribute_object->set_variation( $is_variation );
						$attributes[] = $attribute_object;
					}
				} elseif ( isset( $attribute['value'] ) ) {
					if ( ! empty( $attribute['default'] ) && in_array( $attribute['default'], $attribute['value'], true ) ) {
						$default_attributes[ sanitize_title( $attribute['name'] ) ] = $attribute['default'];
						$is_variation = 1;
					}

					$attribute_object = new GreatTransfer_Post_Attribute();
					$attribute_object->set_name( $attribute['name'] );
					$attribute_object->set_options( $attribute['value'] );
					$attribute_object->set_position( $position );
					$attribute_object->set_visible( $is_visible );
					$attribute_object->set_variation( $is_variation );
					$attributes[] = $attribute_object;
				}
			}

			$product->set_attributes( $attributes );

			if ( $product->is_type( 'variable' ) ) {
				$product->set_default_attributes( $default_attributes );
			}
		}
	}

	protected function set_variation_data( &$variation, $data ) {
		$parent = false;

		if ( isset( $data['parent_id'] ) ) {
			$parent = gettransfer_get_product( $data['parent_id'] );

			if ( $parent ) {
				$variation->set_parent_id( $parent->get_id() );
			}
		}

		if ( ! $parent ) {
			return new WP_Error( 'greattransfer_product_importer_missing_variation_parent_id', __( 'Variation cannot be imported: Missing parent ID or parent does not exist yet.', 'greattransfer' ), array( 'status' => 401 ) );
		}

		if ( $parent->is_type( 'variation' ) ) {
			return new WP_Error( 'greattransfer_product_importer_parent_set_as_variation', __( 'Variation cannot be imported: Parent product cannot be a product variation', 'greattransfer' ), array( 'status' => 401 ) );
		}

		if ( isset( $data['raw_attributes'] ) ) {
			$attributes        = array();
			$parent_attributes = $this->get_variation_parent_attributes( $data['raw_attributes'], $parent );

			foreach ( $data['raw_attributes'] as $attribute ) {
				$attribute_id = 0;

				if ( ! empty( $attribute['taxonomy'] ) ) {
					$attribute_id = $this->get_attribute_taxonomy_id( $attribute['name'] );
				}

				if ( $attribute_id ) {
					$attribute_name = gettransfer_attribute_taxonomy_name_by_id( $attribute_id );
				} else {
					$attribute_name = sanitize_title( $attribute['name'] );
				}

				if ( ! isset( $parent_attributes[ $attribute_name ] ) || ! $parent_attributes[ $attribute_name ]->get_variation() ) {
					continue;
				}

				$attribute_key   = sanitize_title( $parent_attributes[ $attribute_name ]->get_name() );
				$attribute_value = isset( $attribute['value'] ) ? current( $attribute['value'] ) : '';

				if ( $parent_attributes[ $attribute_name ]->is_taxonomy() ) {
					$term = get_term_by( 'name', $attribute_value, $attribute_name );

					if ( $term && ! is_wp_error( $term ) ) {
						$attribute_value = $term->slug;
					} else {
						$attribute_value = sanitize_title( $attribute_value );
					}
				}

				$attributes[ $attribute_key ] = $attribute_value;
			}

			$variation->set_attributes( $attributes );
		}
	}

	protected function get_variation_parent_attributes( $attributes, $parent ) {
		$parent_attributes = $parent->get_attributes();
		$require_save      = false;

		foreach ( $attributes as $attribute ) {
			$attribute_id = 0;

			if ( ! empty( $attribute['taxonomy'] ) ) {
				$attribute_id = $this->get_attribute_taxonomy_id( $attribute['name'] );
			}

			if ( $attribute_id ) {
				$attribute_name = gettransfer_attribute_taxonomy_name_by_id( $attribute_id );
			} else {
				$attribute_name = sanitize_title( $attribute['name'] );
			}

			if ( isset( $parent_attributes[ $attribute_name ] ) && ! $parent_attributes[ $attribute_name ]->get_variation() ) {
				$parent_attributes[ $attribute_name ] = clone $parent_attributes[ $attribute_name ];
				$parent_attributes[ $attribute_name ]->set_variation( 1 );

				$require_save = true;
			}
		}

		if ( $require_save ) {
			$parent->set_attributes( array_values( $parent_attributes ) );
			$parent->save();
		}

		return $parent_attributes;
	}

	public function get_attachment_id_from_url( $url, $product_id ) {
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
				'meta_query'  => array(
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
						'key'     => '_gettransfer_attachment_source',
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
				'meta_query'  => array(
					array(
						'value' => $url,
						'key'   => '_gettransfer_attachment_source',
					),
				),
			);
		}

		$ids = get_posts( $args );

		if ( $ids ) {
			$id = current( $ids );
		}

		if ( ! $id && stristr( $url, '://' ) ) {
			$upload = gettransfer_rest_upload_image_from_url( $url );

			if ( is_wp_error( $upload ) ) {
				throw new Exception( $upload->get_error_message(), 400 );
			}

			$id = gettransfer_rest_set_uploaded_image_as_attachment( $upload, $product_id );

			if ( ! wp_attachment_is_image( $id ) ) {
				/* translators: %s: image URL */
				throw new Exception( sprintf( __( 'Not able to attach "%s".', 'greattransfer' ), $url ), 400 );
			}

			update_post_meta( $id, '_gettransfer_attachment_source', $url );
		}

		if ( ! $id ) {
			/* translators: %s: image URL */
			throw new Exception( sprintf( __( 'Unable to use image "%s".', 'greattransfer' ), $url ), 400 );
		}

		return $id;
	}


	public function get_attribute_taxonomy_id( $raw_name ) {
		global $wpdb, $gettransfer_product_attributes;

		$attribute_labels = wp_list_pluck( gettransfer_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
		$attribute_name   = array_search( $raw_name, $attribute_labels, true );

		if ( ! $attribute_name ) {
			$attribute_name = gettransfer_sanitize_taxonomy_name( $raw_name );
		}

		$attribute_id = gettransfer_attribute_taxonomy_id_by_name( $attribute_name );

		if ( $attribute_id ) {
			return $attribute_id;
		}

		$attribute_id = gettransfer_create_attribute(
			array(
				'name'         => $raw_name,
				'slug'         => $attribute_name,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		if ( is_wp_error( $attribute_id ) ) {
			throw new Exception( $attribute_id->get_error_message(), 400 );
		}

		$taxonomy_name = gettransfer_attribute_taxonomy_name( $attribute_name );
		register_taxonomy(
			$taxonomy_name,
			apply_filters( 'greattransfer_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
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

		$gettransfer_product_attributes = array();

		foreach ( gettransfer_get_attribute_taxonomies() as $taxonomy ) {
			$gettransfer_product_attributes[ gettransfer_attribute_taxonomy_name( $taxonomy->attribute_name ) ] = $taxonomy;
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
		return apply_filters( 'greattransfer_product_importer_memory_exceeded', $return );
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
		$finish = $this->start_time + apply_filters( 'greattransfer_product_importer_default_time_limit', 20 );
		$return = false;
		if ( time() >= $finish ) {
			$return = true;
		}
		return apply_filters( 'greattransfer_product_importer_time_exceeded', $return );
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
