<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GreatTransfer_Post_Importer', false ) ) {
	include_once __DIR__ . '/abstract-greattransfer-post-importer.php';
}

if ( ! class_exists( 'GreatTransfer_Post_CSV_Importer_Controller', false ) ) {
	include_once GREATTRANSFER_ABSPATH . 'includes/admin/importers/class-greattransfer-post-csv-importer-controller.php';
}

class GreatTransfer_Post_CSV_Importer extends GreatTransfer_Post_Importer {

	protected $parsing_raw_data_index = 0;

	public function __construct( $file, $params = array() ) {
		$default_args = array(
			'start_pos'        => 0,
			'end_pos'          => -1,
			'lines'            => -1,
			'mapping'          => array(),
			'parse'            => false,
			'update_existing'  => false,
			'delimiter'        => ',',
			'prevent_timeouts' => true,
			'enclosure'        => '"',
			'escape'           => "\0",
		);

		$this->params = wp_parse_args( $params, $default_args );
		$this->file   = $file;

		if ( isset( $this->params['mapping']['from'], $this->params['mapping']['to'] ) ) {
			$this->params['mapping'] = array_combine( $this->params['mapping']['from'], $this->params['mapping']['to'] );
		}

		include_once dirname( __DIR__ ) . '/admin/importers/mappings/mappings.php';

		$this->read_file();
	}

	private function adjust_character_encoding( $value ) {
		$encoding = $this->params['character_encoding'];
		return 'UTF-8' === $encoding ? $value : mb_convert_encoding( $value, 'UTF-8', $encoding );
	}

	protected function read_file() {
		if ( ! GreatTransfer_Post_CSV_Importer_Controller::is_file_valid_csv( $this->file ) ) {
			wp_die( esc_html__( 'Invalid file type. The importer supports CSV and TXT file formats.', 'greattransfer' ) );
		}

		$handle = fopen( $this->file, 'r' );

		if ( false !== $handle ) {
			$this->raw_keys = array_map( 'trim', fgetcsv( $handle, 0, $this->params['delimiter'], $this->params['enclosure'], $this->params['escape'] ) );

			if ( ArrayUtil::is_truthy( $this->params, 'character_encoding' ) ) {
				$this->raw_keys = array_map( array( $this, 'adjust_character_encoding' ), $this->raw_keys );
			}

			$this->raw_keys = greattransfer_clean( wp_unslash( $this->raw_keys ) );

			if ( isset( $this->raw_keys[0] ) ) {
				$this->raw_keys[0] = $this->remove_utf8_bom( $this->raw_keys[0] );
			}

			if ( 0 !== $this->params['start_pos'] ) {
				fseek( $handle, (int) $this->params['start_pos'] );
			}

			while ( 1 ) {
				$row = fgetcsv( $handle, 0, $this->params['delimiter'], $this->params['enclosure'], $this->params['escape'] ); // @codingStandardsIgnoreLine

				if ( false !== $row ) {
					if ( ArrayUtil::is_truthy( $this->params, 'character_encoding' ) ) {
						$row = array_map( array( $this, 'adjust_character_encoding' ), $row );
					}

					$this->raw_data[]                                 = $row;
					$this->file_positions[ count( $this->raw_data ) ] = ftell( $handle );

					if ( ( $this->params['end_pos'] > 0 && ftell( $handle ) >= $this->params['end_pos'] ) || 0 === --$this->params['lines'] ) {
						break;
					}
				} else {
					break;
				}
			}

			$this->file_position = ftell( $handle );
		}

		if ( ! empty( $this->params['mapping'] ) ) {
			$this->set_mapped_keys();
		}

		if ( $this->params['parse'] ) {
			$this->set_parsed_data();
		}
	}

	protected function remove_utf8_bom( $string ) {
		if ( 'efbbbf' === substr( bin2hex( $string ), 0, 6 ) ) {
			$string = substr( $string, 3 );
		}

		return $string;
	}

	protected function set_mapped_keys() {
		$mapping = $this->params['mapping'];

		foreach ( $this->raw_keys as $key ) {
			$this->mapped_keys[] = isset( $mapping[ $key ] ) ? $mapping[ $key ] : $key;
		}
	}

	public function parse_relative_field( $value ) {
		global $wpdb;

		if ( empty( $value ) ) {
			return '';
		}

		if ( preg_match( '/^id:(\d+)$/', $value, $matches ) ) {
			$id = intval( $matches[1] );

			$original_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_original_id' AND meta_value = %s;", $id ) ); // WPCS: db call ok, cache ok.

			if ( $original_id ) {
				return absint( $original_id );
			}

			$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( 'post', 'post_variation' ) AND ID = %d;", $id ) ); // WPCS: db call ok, cache ok.

			if ( $existing_id ) {
				return absint( $existing_id );
			}

			if ( ! $this->params['update_existing'] ) {
				$post = greattransfer_get_post_object( 'simple' );
				$post->set_name( 'Import placeholder for ' . $id );
				$post->set_status( 'importing' );
				$post->add_meta_data( '_original_id', $id, true );
				$id = $post->save();
			}

			return $id;
		}

		$id = greattransfer_get_post_id_by_sku( $value );

		if ( $id ) {
			return $id;
		}

		try {
			$post = greattransfer_get_post_object( 'simple' );
			$post->set_name( 'Import placeholder for ' . $value );
			$post->set_status( 'importing' );
			$post->set_sku( $value );
			$id = $post->save();

			if ( $id && ! is_wp_error( $id ) ) {
				return $id;
			}
		} catch ( Exception $e ) {
			return '';
		}

		return '';
	}

	public function parse_id_field( $value ) {
		global $wpdb;

		$id = absint( $value );

		if ( ! $id ) {
			return 0;
		}

		$original_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_original_id' AND meta_value = %s;", $id ) ); // WPCS: db call ok, cache ok.

		if ( $original_id ) {
			return absint( $original_id );
		}

		if ( ! $this->params['update_existing'] ) {
			$mapped_keys      = $this->get_mapped_keys();
			$sku_column_index = absint( array_search( 'sku', $mapped_keys, true ) );
			$row_sku          = isset( $this->raw_data[ $this->parsing_raw_data_index ][ $sku_column_index ] ) ? $this->raw_data[ $this->parsing_raw_data_index ][ $sku_column_index ] : '';
			$id_from_sku      = $row_sku ? greattransfer_get_post_id_by_sku( $row_sku ) : '';

			if ( $id_from_sku ) {
				return $id_from_sku;
			}

			$post = greattransfer_get_post_object( 'simple' );
			$post->set_name( 'Import placeholder for ' . $id );
			$post->set_status( 'importing' );
			$post->add_meta_data( '_original_id', $id, true );

			if ( $row_sku ) {
				$post->set_sku( $row_sku );
			}
			$id = $post->save();
		}

		return $id && ! is_wp_error( $id ) ? $id : 0;
	}

	public function parse_relative_comma_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		return array_filter( array_map( array( $this, 'parse_relative_field' ), $this->explode_values( $value ) ) );
	}

	public function parse_comma_field( $value ) {
		if ( empty( $value ) && '0' !== $value ) {
			return array();
		}

		$value = $this->unescape_data( $value );
		return array_map( 'greattransfer_clean', $this->explode_values( $value ) );
	}

	public function parse_bool_field( $value ) {
		if ( '0' === $value ) {
			return false;
		}

		if ( '1' === $value ) {
			return true;
		}

		return greattransfer_clean( $value );
	}

	public function parse_float_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}

		// Remove the ' prepended to fields that start with - if needed.
		$value = $this->unescape_data( $value );

		return floatval( $value );
	}

	public function parse_stock_quantity_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}

		$value = $this->unescape_data( $value );

		return greattransfer_stock_amount( $value );
	}

	public function parse_tax_status_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}

		$value = $this->unescape_data( $value );

		if ( 'true' === strtolower( $value ) || 'false' === strtolower( $value ) ) {
			$value = greattransfer_string_to_bool( $value ) ? 'taxable' : 'none';
		}

		return greattransfer_clean( $value );
	}

	public function parse_categories_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$row_terms  = $this->explode_values( $value );
		$categories = array();

		foreach ( $row_terms as $row_term ) {
			$parent = null;
			$_terms = array_map( 'trim', explode( '>', $row_term ) );
			$total  = count( $_terms );

			foreach ( $_terms as $index => $_term ) {
				// Don't allow users without capabilities to create new categories.
				if ( ! current_user_can( 'manage_post_terms' ) ) {
					break;
				}

				$term = wp_insert_term( $_term, 'post_cat', array( 'parent' => intval( $parent ) ) );

				if ( is_wp_error( $term ) ) {
					if ( $term->get_error_code() === 'term_exists' ) {
						// When term exists, error data should contain existing term id.
						$term_id = $term->get_error_data();
					} else {
						break; // We cannot continue on any other error.
					}
				} else {
					// New term.
					$term_id = $term['term_id'];
				}

				// Only requires assign the last category.
				if ( ( 1 + $index ) === $total ) {
					$categories[] = $term_id;
				} else {
					// Store parent to be able to insert or query categories based in parent ID.
					$parent = $term_id;
				}
			}
		}

		return $categories;
	}

	public function parse_tags_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$value = $this->unescape_data( $value );
		$names = $this->explode_values( $value );
		$tags  = array();

		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, 'post_tag' );

			if ( ! $term || is_wp_error( $term ) ) {
				$term = (object) wp_insert_term( $name, 'post_tag' );
			}

			if ( ! is_wp_error( $term ) ) {
				$tags[] = $term->term_id;
			}
		}

		return $tags;
	}

	public function parse_tags_spaces_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$value = $this->unescape_data( $value );
		$names = $this->explode_values( $value, ' ' );
		$tags  = array();

		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, 'post_tag' );

			if ( ! $term || is_wp_error( $term ) ) {
				$term = (object) wp_insert_term( $name, 'post_tag' );
			}

			if ( ! is_wp_error( $term ) ) {
				$tags[] = $term->term_id;
			}
		}

		return $tags;
	}

	public function parse_shipping_class_field( $value ) {
		if ( empty( $value ) ) {
			return 0;
		}

		$term = get_term_by( 'name', $value, 'post_shipping_class' );

		if ( ! $term || is_wp_error( $term ) ) {
			$term = (object) wp_insert_term( $value, 'post_shipping_class' );
		}

		if ( is_wp_error( $term ) ) {
			return 0;
		}

		return $term->term_id;
	}

	public function parse_images_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$images    = array();
		$separator = apply_filters( 'greattransfer_post_import_image_separator', ',' );

		foreach ( $this->explode_values( $value, $separator ) as $image ) {
			if ( stristr( $image, '://' ) ) {
				$images[] = esc_url_raw( $image );
			} else {
				$images[] = sanitize_file_name( $image );
			}
		}

		return $images;
	}

	public function parse_date_field( $value ) {
		if ( empty( $value ) ) {
			return null;
		}

		if ( preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])([ 01-9:]*)$/', $value ) ) {
			// Don't include the time if the field had time in it.
			return current( explode( ' ', $value ) );
		}

		return null;
	}

	public function parse_datetime_field( $value ) {
		try {
			// If value is a Unix timestamp, convert it to a datetime string.
			if ( is_numeric( $value ) ) {
				$datetime = new DateTime( "@{$value}" );
				// Return datetime string in ISO8601 format (eg. 2018-01-01T00:00:00Z) to preserve UTC timezone since Unix timestamps are always UTC.
				return $datetime->format( 'Y-m-d\TH:i:s\Z' );
			}
			// Check whether the value is a valid date string.
			if ( false !== strtotime( $value ) ) {
				// If the value is a valid date string, return as is.
				return $value;
			}
		} catch ( Exception $e ) {
			// DateTime constructor throws an exception if the value is not a valid Unix timestamp.
			return null;
		}
		// If value is not valid Unix timestamp or date string, return null.
		return null;
	}

	public function parse_backorders_field( $value ) {
		if ( empty( $value ) ) {
			return 'no';
		}

		$value = $this->parse_bool_field( $value );

		if ( 'notify' === $value ) {
			return 'notify';
		} elseif ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}

		return 'no';
	}

	public function parse_skip_field( $value ) {
		return $value;
	}

	public function parse_download_file_field( $value ) {
		if ( 0 === strpos( $value, 'http' ) ) {
			return esc_url_raw( $value );
		}
		return greattransfer_clean( $value );
	}

	public function parse_int_field( $value ) {
		// Remove the ' prepended to fields that start with - if needed.
		$value = $this->unescape_data( $value );

		return intval( $value );
	}

	public function parse_description_field( $description ) {
		$parts = explode( "\\\\n", $description );
		foreach ( $parts as $key => $part ) {
			$parts[ $key ] = str_replace( '\n', "\n", $part );
		}

		return implode( '\\\n', $parts );
	}

	public function parse_published_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}

		$value = $this->unescape_data( $value );

		if ( 'true' === strtolower( $value ) || 'false' === strtolower( $value ) ) {
			return greattransfer_string_to_bool( $value ) ? 1 : -1;
		}

		return floatval( $value );
	}

	protected function get_formating_callback() {
		return $this->get_formatting_callback();
	}

	protected function get_formatting_callback() {
		$data_formatting = array(
			'id'                => array( $this, 'parse_id_field' ),
			'type'              => array( $this, 'parse_comma_field' ),
			'published'         => array( $this, 'parse_published_field' ),
			'featured'          => array( $this, 'parse_bool_field' ),
			'date_on_sale_from' => array( $this, 'parse_datetime_field' ),
			'date_on_sale_to'   => array( $this, 'parse_datetime_field' ),
			'name'              => array( $this, 'parse_skip_field' ),
			'short_description' => array( $this, 'parse_description_field' ),
			'description'       => array( $this, 'parse_description_field' ),
			'manage_stock'      => array( $this, 'parse_bool_field' ),
			'low_stock_amount'  => array( $this, 'parse_stock_quantity_field' ),
			'backorders'        => array( $this, 'parse_backorders_field' ),
			'stock_status'      => array( $this, 'parse_bool_field' ),
			'sold_individually' => array( $this, 'parse_bool_field' ),
			'width'             => array( $this, 'parse_float_field' ),
			'length'            => array( $this, 'parse_float_field' ),
			'height'            => array( $this, 'parse_float_field' ),
			'weight'            => array( $this, 'parse_float_field' ),
			'reviews_allowed'   => array( $this, 'parse_bool_field' ),
			'purchase_note'     => 'wp_filter_post_kses',
			'price'             => 'greattransfer_format_decimal',
			'regular_price'     => 'greattransfer_format_decimal',
			'stock_quantity'    => array( $this, 'parse_stock_quantity_field' ),
			'category_ids'      => array( $this, 'parse_categories_field' ),
			'tag_ids'           => array( $this, 'parse_tags_field' ),
			'tag_ids_spaces'    => array( $this, 'parse_tags_spaces_field' ),
			'shipping_class_id' => array( $this, 'parse_shipping_class_field' ),
			'images'            => array( $this, 'parse_images_field' ),
			'parent_id'         => array( $this, 'parse_relative_field' ),
			'grouped_posts'     => array( $this, 'parse_relative_comma_field' ),
			'upsell_ids'        => array( $this, 'parse_relative_comma_field' ),
			'cross_sell_ids'    => array( $this, 'parse_relative_comma_field' ),
			'download_limit'    => array( $this, 'parse_int_field' ),
			'download_expiry'   => array( $this, 'parse_int_field' ),
			'post_url'          => 'esc_url_raw',
			'menu_order'        => 'intval',
			'tax_status'        => array( $this, 'parse_tax_status_field' ),
		);

		$regex_match_data_formatting = array(
			'/attributes:value*/'    => array( $this, 'parse_comma_field' ),
			'/attributes:visible*/'  => array( $this, 'parse_bool_field' ),
			'/attributes:taxonomy*/' => array( $this, 'parse_bool_field' ),
			'/downloads:url*/'       => array( $this, 'parse_download_file_field' ),
			'/meta:*/'               => 'wp_kses_post',
		);

		$callbacks = array();

		foreach ( $this->get_mapped_keys() as $index => $heading ) {
			$callback = 'greattransfer_clean';

			if ( isset( $data_formatting[ $heading ] ) ) {
				$callback = $data_formatting[ $heading ];
			} else {
				foreach ( $regex_match_data_formatting as $regex => $callback ) {
					if ( preg_match( $regex, $heading ) ) {
						$callback = $callback;
						break;
					}
				}
			}

			$callbacks[] = $callback;
		}

		return apply_filters( 'greattransfer_post_importer_formatting_callbacks', $callbacks, $this );
	}

	protected function starts_with( $haystack, $needle ) {
		return substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}

	protected function expand_data( $data ) {
		$data = apply_filters( 'greattransfer_post_importer_pre_expand_data', $data );

		if ( isset( $data['images'] ) ) {
			$images               = $data['images'];
			$data['raw_image_id'] = array_shift( $images );

			if ( ! empty( $images ) ) {
				$data['raw_gallery_image_ids'] = $images;
			}
			unset( $data['images'] );
		}

		if ( isset( $data['type'] ) ) {
			$data['type']         = array_map( 'strtolower', $data['type'] );
			$data['virtual']      = in_array( 'virtual', $data['type'], true );
			$data['downloadable'] = in_array( 'downloadable', $data['type'], true );

			$data['type'] = current( array_diff( $data['type'], array( 'virtual', 'downloadable' ) ) );

			if ( ! $data['type'] ) {
				$data['type'] = 'simple';
			}
		}

		if ( isset( $data['published'] ) ) {
			$published = $data['published'];
			if ( is_float( $published ) ) {
				$published = (int) $published;
			}

			$statuses       = array(
				-1 => 'draft',
				0  => 'private',
				1  => 'publish',
			);
			$data['status'] = $statuses[ $published ] ?? 'draft';

			if ( 'variation' === ( $data['type'] ?? null ) && -1 === $published ) {
				$data['status'] = 'publish';
			}

			unset( $data['published'] );
		}

		if ( isset( $data['stock_quantity'] ) ) {
			if ( '' === $data['stock_quantity'] ) {
				$data['manage_stock'] = false;
				$data['stock_status'] = isset( $data['stock_status'] ) ? $data['stock_status'] : true;
			} else {
				$data['manage_stock'] = true;
			}
		}

		if ( isset( $data['stock_status'] ) ) {
			if ( 'backorder' === $data['stock_status'] ) {
				$data['stock_status'] = 'onbackorder';
			} else {
				$data['stock_status'] = $data['stock_status'] ? 'instock' : 'outofstock';
			}
		}

		if ( isset( $data['grouped_posts'] ) ) {
			$data['children'] = $data['grouped_posts'];
			unset( $data['grouped_posts'] );
		}

		if ( isset( $data['tag_ids_spaces'] ) ) {
			$data['tag_ids'] = $data['tag_ids_spaces'];
			unset( $data['tag_ids_spaces'] );
		}

		$attributes = array();
		$downloads  = array();
		$meta_data  = array();

		foreach ( $data as $key => $value ) {
			if ( $this->starts_with( $key, 'attributes:name' ) ) {
				if ( ! empty( $value ) ) {
					$attributes[ str_replace( 'attributes:name', '', $key ) ]['name'] = $value;
				}
				unset( $data[ $key ] );
			} elseif ( $this->starts_with( $key, 'attributes:value' ) ) {
				$attributes[ str_replace( 'attributes:value', '', $key ) ]['value'] = $value;
				unset( $data[ $key ] );
			} elseif ( $this->starts_with( $key, 'attributes:taxonomy' ) ) {
				$attributes[ str_replace( 'attributes:taxonomy', '', $key ) ]['taxonomy'] = greattransfer_string_to_bool( $value );
				unset( $data[ $key ] );
			} elseif ( $this->starts_with( $key, 'attributes:visible' ) ) {
				$attributes[ str_replace( 'attributes:visible', '', $key ) ]['visible'] = greattransfer_string_to_bool( $value );
				unset( $data[ $key ] );
			} elseif ( $this->starts_with( $key, 'attributes:default' ) ) {
				if ( ! empty( $value ) ) {
					$attributes[ str_replace( 'attributes:default', '', $key ) ]['default'] = $value;
				}
				unset( $data[ $key ] );
			} elseif ( $this->starts_with( $key, 'downloads:id' ) ) {
				if ( ! empty( $value ) ) {
					$downloads[ str_replace( 'downloads:id', '', $key ) ]['id'] = $value;
				}
				unset( $data[ $key ] );
			} elseif ( $this->starts_with( $key, 'downloads:name' ) ) {
				if ( ! empty( $value ) ) {
					$downloads[ str_replace( 'downloads:name', '', $key ) ]['name'] = $value;
				}
				unset( $data[ $key ] );
			} elseif ( $this->starts_with( $key, 'downloads:url' ) ) {
				if ( ! empty( $value ) ) {
					$downloads[ str_replace( 'downloads:url', '', $key ) ]['url'] = $value;
				}
				unset( $data[ $key ] );
			} elseif ( $this->starts_with( $key, 'meta:' ) ) {
				$meta_data[] = array(
					'key'   => str_replace( 'meta:', '', $key ),
					'value' => $value,
				);
				unset( $data[ $key ] );
			}
		}

		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $attribute ) {
				if ( empty( $attribute['name'] ) ) {
					continue;
				}

				$data['raw_attributes'][] = $attribute;
			}
		}

		if ( ! empty( $downloads ) ) {
			$data['downloads'] = array();

			foreach ( $downloads as $key => $file ) {
				if ( empty( $file['url'] ) ) {
					continue;
				}

				$data['downloads'][] = array(
					'download_id' => isset( $file['id'] ) ? $file['id'] : null,
					'name'        => $file['name'] ? $file['name'] : greattransfer_get_filename_from_url( $file['url'] ),
					'file'        => $file['url'],
				);
			}
		}

		if ( ! empty( $meta_data ) ) {
			$data['meta_data'] = $meta_data;
		}

		return $data;
	}

	protected function set_parsed_data() {
		$parse_functions = $this->get_formatting_callback();
		$mapped_keys     = $this->get_mapped_keys();
		$use_mb          = function_exists( 'mb_convert_encoding' );

		foreach ( $this->raw_data as $row_index => $row ) {
			if ( ! count( array_filter( $row ) ) ) {
				continue;
			}

			$this->parsing_raw_data_index = $row_index;

			$data = array();

			do_action( 'greattransfer_post_importer_before_set_parsed_data', $row, $mapped_keys );

			foreach ( $row as $id => $value ) {
				if ( empty( $mapped_keys[ $id ] ) ) {
					continue;
				}

				if ( $use_mb ) {
					$encoding = mb_detect_encoding( $value, mb_detect_order(), true );
					if ( $encoding ) {
						$value = mb_convert_encoding( $value, 'UTF-8', $encoding );
					} else {
						$value = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
					}
				} else {
					$value = wp_check_invalid_utf8( $value, true );
				}

				$data[ $mapped_keys[ $id ] ] = call_user_func( $parse_functions[ $id ], $value );
			}

			$this->parsed_data[] = apply_filters( 'greattransfer_post_importer_parsed_data', $this->expand_data( $data ), $this );
		}
	}

	protected function get_row_id( $parsed_data ) {
		$id       = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
		$name     = isset( $parsed_data['name'] ) ? esc_attr( $parsed_data['name'] ) : '';
		$row_data = array();

		if ( $name ) {
			$row_data[] = $name;
		}
		if ( $id ) {
			/* translators: %d: post ID */
			$row_data[] = sprintf( __( 'ID %d', 'greattransfer' ), $id );
		}

		return implode( ', ', $row_data );
	}

	public function import() {
		$this->start_time = time();
		$index            = 0;
		$update_existing  = $this->params['update_existing'];
		$data             = array(
			'imported'            => array(),
			'imported_variations' => array(),
			'failed'              => array(),
			'updated'             => array(),
			'skipped'             => array(),
		);

		foreach ( $this->parsed_data as $parsed_data_key => $parsed_data ) {
			do_action( 'greattransfer_post_import_before_import', $parsed_data );

			$id        = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
			$id_exists = false;

			if ( $id ) {
				$post      = greattransfer_get_post( $id );
				$id_exists = $post && 'importing' !== $post->get_status();
			}

			if ( $id_exists && ! $update_existing ) {
				$data['skipped'][] = new WP_Error(
					'greattransfer_post_importer_error',
					esc_html__( 'A post with this ID already exists.', 'greattransfer' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && ( isset( $parsed_data['id'] ) || isset( $parsed_data['sku'] ) ) && ! $id_exists && ! $sku_exists ) {
				$data['skipped'][] = new WP_Error(
					'greattransfer_post_importer_error',
					esc_html__( 'No matching post exists to update.', 'greattransfer' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			$result = $this->process_item( $parsed_data );

			if ( is_wp_error( $result ) ) {
				$result->add_data( array( 'row' => $this->get_row_id( $parsed_data ) ) );
				$data['failed'][] = $result;
			} elseif ( $result['updated'] ) {
				$data['updated'][] = $result['id'];
			} elseif ( $result['is_variation'] ) {
					$data['imported_variations'][] = $result['id'];
			} else {
				$data['imported'][] = $result['id'];
			}

			++$index;

			if ( $this->params['prevent_timeouts'] && ( $this->time_exceeded() || $this->memory_exceeded() ) ) {
				$this->file_position = $this->file_positions[ $index ];
				break;
			}
		}

		return $data;
	}
}
