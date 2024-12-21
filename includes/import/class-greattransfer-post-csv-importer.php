<?php

use GreatTransfer\GreatTransfer\Utilities\ArrayUtil;

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
			'post_type'        => null,
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

		self::$import_type = $this->params['post_type'];

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

	protected function remove_utf8_bom( $text ) {
		if ( 'efbbbf' === substr( bin2hex( $text ), 0, 6 ) ) {
			$text = substr( $text, 3 );
		}

		return $text;
	}

	protected function set_mapped_keys() {
		$mapping = $this->params['mapping'];

		foreach ( $this->raw_keys as $key ) {
			$this->mapped_keys[] = isset( $mapping[ $key ] ) ? $mapping[ $key ] : $key;
		}
	}

	public function taxonomies() {
		$taxonomies = array();

		if ( class_exists( 'Jet_Engine' ) ) {
			$cpt_items = jet_engine()->cpt->get_items();
			$cpt_items = array_combine( wp_list_pluck( $cpt_items, 'slug' ), $cpt_items );
			if ( isset( $cpt_items[ self::$import_type ] ) ) {
				$cpt = $cpt_items[ self::$import_type ];

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

	public function parse_id_field( $value ) {
		global $wpdb;

		$id = absint( $value );

		if ( ! $id ) {
			return 0;
		}

		$original_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_original_id' AND meta_value = %s;",
				$id
			)
		);

		if ( $original_id ) {
			return absint( $original_id );
		}

		if ( ! $this->params['update_existing'] ) {
			$post_from_id = get_post( $id );

			if ( $post_from_id && self::$import_type === $post_from_id->post_type ) {
				return $id;
			}

			$postarr = array(
				'post_title'  => 'Import placeholder for ' . $id,
				'post_status' => 'importing',
				'post_type'   => self::$import_type,
				'meta_input'  => array(
					'_original_id' => $id,
				),
			);

			$id = wp_insert_post( $postarr );
		}

		return $id && ! is_wp_error( $id ) ? $id : 0;
	}

	public function parse_taxonomy_terms_field( $value, $taxonomy ) {
		if ( empty( $value ) ) {
			return array();
		}

		$taxonomy_object = get_taxonomy( $taxonomy );

		$row_terms = $this->explode_values( $value );

		$terms = array();

		foreach ( $row_terms as $row_term ) {
			$parent = null;
			$_terms = array_map( 'trim', explode( '>', $row_term ) );
			$total  = count( $_terms );

			foreach ( $_terms as $index => $_term ) {
				if ( ! current_user_can( $taxonomy_object->cap->manage_terms ) ) {
					break;
				}

				$term = wp_insert_term( $_term, $taxonomy, array( 'parent' => intval( $parent ) ) );

				if ( is_wp_error( $term ) ) {
					if ( $term->get_error_code() === 'term_exists' ) {
						$term_id = $term->get_error_data();
					} else {
						break;
					}
				} else {
					$term_id = $term['term_id'];
				}

				if ( ( 1 + $index ) === $total ) {
					$terms[] = $term_id;
				} else {
					$parent = $term_id;
				}
			}
		}

		return $terms;
	}

	public function parse_skip_field( $value ) {
		return $value;
	}

	protected function get_formatting_callback() {
		$data_formatting = array(
			'ID'         => array( $this, 'parse_id_field' ),
			'post_title' => array( $this, 'parse_skip_field' ),
		);

		$taxonomies = $this->taxonomies();

		foreach ( $taxonomies as $taxonomy => $labels ) {
			$key = $taxonomy . '_ids';

			$data_formatting[ $key ] = array( $this, 'parse_taxonomy_terms_field' );
		}

		$regex_match_data_formatting = array(
			'/meta:*/' => 'wp_kses_post',
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

		$meta_data = array();

		foreach ( $data as $key => $value ) {
			if ( $this->starts_with( $key, 'meta:' ) ) {
				$meta_data[] = array(
					'key'   => str_replace( 'meta:', '', $key ),
					'value' => $value,
				);
				unset( $data[ $key ] );
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

		$taxonomies = $this->taxonomies();

		$taxonomy_keys = array();
		foreach ( $taxonomies as $taxonomy => $labels ) {
			$key = $taxonomy . '_ids';

			$taxonomy_keys[ $key ] = $taxonomy;
		}

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

				$key = $mapped_keys[ $id ];

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

				if ( isset( $taxonomy_keys[ $key ] ) ) {
					$taxonomy = $taxonomy_keys[ $key ];

					$parsed_value = call_user_func( $parse_functions[ $id ], $value, $taxonomy );
				} else {
					$parsed_value = call_user_func( $parse_functions[ $id ], $value );
				}

				$data[ $key ] = $parsed_value;
			}

			$this->parsed_data[] = apply_filters(
				'greattransfer_post_importer_parsed_data',
				$this->expand_data( $data ),
				$this
			);
		}
	}

	protected function get_row_id( $parsed_data ) {
		$id       = isset( $parsed_data['ID'] ) ? absint( $parsed_data['ID'] ) : 0;
		$title    = isset( $parsed_data['post_title'] ) ? esc_attr( $parsed_data['post_title'] ) : '';
		$row_data = array();

		if ( $title ) {
			$row_data[] = $title;
		}
		if ( $id ) {
			/* translators: %d: post ID */
			$row_data[] = sprintf( __( 'ID %d', 'greattransfer' ), $id );
		}

		return implode( ', ', $row_data );
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

	private function gender() {
		$post_type_object = $this->get_post_type_object();

		$labels = $post_type_object->labels;

		$all_male   = _x( 'All', 'male', 'greattransfer' );
		$all_female = _x( 'All', 'female', 'greattransfer' );

		$labels = $post_type_object->labels;

		$gender = 'neutral';
		if ( $all_male !== $all_female ) {
			if ( str_starts_with( $labels->all_items, $all_male ) ) {
				$gender = 'male';
			} elseif ( str_starts_with( $labels->all_items, $all_female ) ) {
				$gender = 'female';
			}
		}

		return $gender;
	}

	public function import() {
		$this->start_time = time();
		$index            = 0;
		$update_existing  = $this->params['update_existing'];
		$data             = array(
			'imported' => array(),
			'failed'   => array(),
			'updated'  => array(),
			'skipped'  => array(),
		);

		$post_type_object = $this->get_post_type_object();

		/* translators: Post/Taxonomy labels singular_name */
		$already_exists = _x( 'A %s with this ID already exists.', 'male', 'greattransfer' );
		/* translators: Post/Taxonomy labels singular_name */
		$no_matching = _x( 'No matching %s exists to update.', 'male', 'greattransfer' );
		if ( 'female' === $this->gender() ) {
			/* translators: Post/Taxonomy labels singular_name */
			$already_exists = _x( 'A %s with this ID already exists.', 'female', 'greattransfer' );
			/* translators: Post/Taxonomy labels singular_name */
			$no_matching = _x( 'No matching %s exists to update.', 'female', 'greattransfer' );
		}

		foreach ( $this->parsed_data as $parsed_data_key => $parsed_data ) {
			do_action( 'greattransfer_post_import_before_import', $parsed_data );

			$id        = isset( $parsed_data['ID'] ) ? absint( $parsed_data['ID'] ) : 0;
			$id_exists = false;

			if ( $id ) {
				$post      = get_post( $id );
				$id_exists = $post && 'importing' !== $post->post_status;
			}

			if ( $id_exists && ! $update_existing ) {
				$singular_name = $post_type_object->labels->singular_name;
				if ( ! str_starts_with( $already_exists, '%s' ) ) {
					$singular_name = mb_strtolower( $singular_name );
				}

				$data['skipped'][] = new WP_Error(
					'greattransfer_post_importer_error',
					esc_html( sprintf( $already_exists, $singular_name ) ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && ( isset( $parsed_data['ID'] ) ) && ! $id_exists ) {
				$singular_name = $post_type_object->labels->singular_name;
				if ( ! str_starts_with( $no_matching, '%s' ) ) {
					$singular_name = mb_strtolower( $singular_name );
				}

				$data['skipped'][] = new WP_Error(
					'greattransfer_post_importer_error',
					esc_html( sprintf( $no_matching, $singular_name ) ),
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
