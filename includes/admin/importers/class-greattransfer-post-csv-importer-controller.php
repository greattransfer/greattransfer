<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_Importer' ) ) {
	return;
}

class GreatTransfer_Post_CSV_Importer_Controller {

	protected $import_type;

	protected $file = '';

	protected $step = '';

	protected $steps = array();

	protected $errors = array();

	protected $delimiter = ',';

	protected $map_preferences = false;

	protected $update_existing = false;

	protected $character_encoding = 'UTF-8';

	public static function get_importer( $file, $args = array() ) {
		$importer_class = apply_filters( 'greattransfer_post_csv_importer_class', 'GreatTransfer_Post_CSV_Importer' );
		$args           = apply_filters( 'greattransfer_post_csv_importer_args', $args, $importer_class );
		return new $importer_class( $file, $args );
	}

	public static function is_file_valid_csv( $file, $check_path = true ) {
		return greattransfer_is_file_valid_csv( $file, $check_path );
	}

	protected static function check_file_path( string $path ): void {
		$wp_filesystem = FilesystemUtil::get_wp_filesystem();

		$is_valid_file = $wp_filesystem->is_readable( $path );

		if ( $is_valid_file ) {
			$is_valid_file = self::file_is_in_directory( $path, $wp_filesystem->abspath() );
			if ( ! $is_valid_file ) {
				$upload_dir    = wp_get_upload_dir();
				$is_valid_file = false === $upload_dir['error'] && self::file_is_in_directory( $path, $upload_dir['basedir'] );
			}
		}

		if ( ! $is_valid_file ) {
			throw new \Exception( esc_html__( 'File path provided for import is invalid.', 'greattransfer' ) );
		}

		if ( ! self::is_file_valid_csv( $path ) ) {
			throw new \Exception( esc_html__( 'Invalid file type. The importer supports CSV and TXT file formats.', 'greattransfer' ) );
		}
	}

	private static function file_is_in_directory( string $file_path, string $directory ): bool {
		$file_path = (string) new URL( $file_path );
		$file_path = preg_replace( '/^file:\\/\\//', '', $file_path );
		return 0 === stripos( wp_normalize_path( $file_path ), trailingslashit( wp_normalize_path( $directory ) ) );
	}

	protected static function get_valid_csv_filetypes() {
		return apply_filters(
			'greattransfer_csv_post_import_valid_filetypes',
			array(
				'csv' => 'text/csv',
				'txt' => 'text/plain',
			)
		);
	}

	public function __construct( $import_type = null ) {
		global $typenow, $post_type;

		if ( ! is_null( $import_type ) ) {
			$typenow = $import_type;
		}
		$post_type         = $typenow;
		$this->import_type = $post_type;

		$default_steps = array(
			'upload'  => array(
				'name'    => __( 'Upload CSV file', 'greattransfer' ),
				'view'    => array( $this, 'upload_form' ),
				'handler' => array( $this, 'upload_form_handler' ),
			),
			'mapping' => array(
				'name'    => __( 'Column mapping', 'greattransfer' ),
				'view'    => array( $this, 'mapping_form' ),
				'handler' => '',
			),
			'import'  => array(
				'name'    => __( 'Import', 'greattransfer' ),
				'view'    => array( $this, 'import' ),
				'handler' => '',
			),
			'done'    => array(
				'name'    => __( 'Done!', 'greattransfer' ),
				'view'    => array( $this, 'done' ),
				'handler' => '',
			),
		);

		$this->steps = apply_filters( 'greattransfer_post_csv_importer_steps', $default_steps );

		$this->step               = isset( $_REQUEST['step'] ) ? sanitize_key( $_REQUEST['step'] ) : current( array_keys( $this->steps ) );
		$this->file               = isset( $_REQUEST['file'] ) ? greattransfer_clean( wp_unslash( $_REQUEST['file'] ) ) : '';
		$this->update_existing    = isset( $_REQUEST['update_existing'] ) ? (bool) $_REQUEST['update_existing'] : false;
		$this->delimiter          = ! empty( $_REQUEST['delimiter'] ) ? greattransfer_clean( wp_unslash( $_REQUEST['delimiter'] ) ) : ',';
		$this->map_preferences    = isset( $_REQUEST['map_preferences'] ) ? (bool) $_REQUEST['map_preferences'] : false;
		$this->character_encoding = isset( $_REQUEST['character_encoding'] ) ? greattransfer_clean( wp_unslash( $_REQUEST['character_encoding'] ) ) : 'UTF-8';

		include_once __DIR__ . '/mappings/mappings.php';

		if ( $this->map_preferences ) {
			add_filter( 'greattransfer_csv_post_import_mapped_columns', array( $this, 'auto_map_user_preferences' ), 9999 );
		}
	}

	private function title() {
		$post_type_object = get_post_type_object( $this->import_type );

		/* translators: Post/Taxonomy labels name (plural) */
		$title = _x( 'Import %s', 'name (plural)', 'greattransfer' );

		$name = $post_type_object->labels->name;
		if ( ! str_starts_with( $name, '%s' ) ) {
			$name = mb_strtolower( $name );
		}

		return sprintf( $title, $name );
	}

	public function subtitle() {
		$post_type_object = get_post_type_object( $this->import_type );

		/* translators: Post labels name (plural) */
		$subtitle = __( 'Import %s from a CSV file', 'greattransfer' );

		$name = $post_type_object->labels->name;
		if ( ! str_starts_with( $name, '%s' ) ) {
			$name = mb_strtolower( $name );
		}

		return sprintf( $subtitle, $name );
	}

	public function description() {
		$post_type_object = get_post_type_object( $this->import_type );

		$description = __(
			/* translators: Post/Taxonomy labels singular_name */
			'This tool allows you to import (or merge) %s data to your site from a CSV or TXT file.',
			'greattransfer'
		);

		$singular_name = $post_type_object->labels->singular_name;
		if ( ! str_starts_with( $singular_name, '%s' ) ) {
			$singular_name = mb_strtolower( $singular_name );
		}
		return sprintf( $description, $singular_name );
	}

	public function update_title() {
		$post_type_object = get_post_type_object( $this->import_type );

		/* translators: Post/Taxonomy labels name (plural) */
		$title = __( 'Update existing %s', 'greattransfer' );

		$name = $post_type_object->labels->name;
		if ( ! str_starts_with( $name, '%s' ) ) {
			$name = mb_strtolower( $name );
		}
		return sprintf( $title, $name );
	}

	public function update_label() {
		$post_type_object = get_post_type_object( $this->import_type );

		/* translators: Post/Taxonomy labels name (plural) */
		$label_starts = __( 'Existing %s that match by ID will be updated.', 'greattransfer' );

		$name = $post_type_object->labels->name;
		if ( ! str_starts_with( $label_starts, '%s' ) ) {
			$name = mb_strtolower( $name );
		}

		$label_starts = sprintf( $label_starts, $name );

		/* translators: Post/Taxonomy labels name (plural) */
		$label_ends = __( '%s that do not exist will be skipped.', 'greattransfer' );

		$name = $post_type_object->labels->name;
		if ( ! str_starts_with( $label_ends, '%s' ) ) {
			$name = mb_strtolower( $name );
		}

		$label_ends = sprintf( $label_ends, $name );

		return sprintf( '%s %s', $label_starts, $label_ends );
	}

	public function get_next_step_link( $step = '' ) {
		if ( ! $step ) {
			$step = $this->step;
		}

		$keys = array_keys( $this->steps );

		if ( end( $keys ) === $step ) {
			return admin_url();
		}

		$step_index = array_search( $step, $keys, true );

		if ( false === $step_index ) {
			return '';
		}

		$params = array(
			'step'               => $keys[ $step_index + 1 ],
			'file'               => str_replace( DIRECTORY_SEPARATOR, '/', $this->file ),
			'delimiter'          => $this->delimiter,
			'update_existing'    => $this->update_existing,
			'map_preferences'    => $this->map_preferences,
			'character_encoding' => $this->character_encoding,
			'_wpnonce'           => wp_create_nonce( 'greattransfer-csv-importer' ),
		);

		return add_query_arg( $params );
	}

	protected function output_header() {
		$title = $this->title();
		include __DIR__ . '/views/html-csv-import-header.php';
	}

	protected function output_steps() {
		include __DIR__ . '/views/html-csv-import-steps.php';
	}

	protected function output_footer() {
		include __DIR__ . '/views/html-csv-import-footer.php';
	}

	protected function add_error( $message, $actions = array() ) {
		$this->errors[] = array(
			'message' => $message,
			'actions' => $actions,
		);
	}

	protected function output_errors() {
		if ( ! $this->errors ) {
			return;
		}

		foreach ( $this->errors as $error ) {
			echo '<div class="error inline">';
			echo '<p>' . esc_html( $error['message'] ) . '</p>';

			if ( ! empty( $error['actions'] ) ) {
				echo '<p>';
				foreach ( $error['actions'] as $action ) {
					echo '<a class="button button-primary" href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a> ';
				}
				echo '</p>';
			}
			echo '</div>';
		}
	}

	public function dispatch() {
		$output = '';

		try {
			if ( ! empty( $_POST['save_step'] ) && ! empty( $this->steps[ $this->step ]['handler'] ) ) {
				if ( is_callable( $this->steps[ $this->step ]['handler'] ) ) {
					call_user_func( $this->steps[ $this->step ]['handler'], $this );
				}
			}

			ob_start();

			if ( is_callable( $this->steps[ $this->step ]['view'] ) ) {
				call_user_func( $this->steps[ $this->step ]['view'], $this );
			}

			$output = ob_get_clean();
		} catch ( \Exception $e ) {
			$this->add_error( $e->getMessage() );
		}

		$this->output_header();
		$this->output_steps();
		$this->output_errors();
		echo $output;
		$this->output_footer();
	}

	public static function dispatch_ajax() {
		global $wpdb;

		check_ajax_referer( 'greattransfer-post-import', 'security' );

		try {
			$file = greattransfer_clean( wp_unslash( $_POST['file'] ?? '' ) );
			self::check_file_path( $file );

			$params = array(
				'delimiter'          => ! empty( $_POST['delimiter'] ) ? greattransfer_clean( wp_unslash( $_POST['delimiter'] ) ) : ',',
				'start_pos'          => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0,
				'mapping'            => isset( $_POST['mapping'] ) ? (array) greattransfer_clean( wp_unslash( $_POST['mapping'] ) ) : array(),
				'update_existing'    => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false,
				'character_encoding' => isset( $_POST['character_encoding'] ) ? greattransfer_clean( wp_unslash( $_POST['character_encoding'] ) ) : '',

				'lines'              => apply_filters( 'greattransfer_post_import_batch_size', 30 ),
				'parse'              => true,
			);

			if ( 0 !== $params['start_pos'] ) {
				$error_log = array_filter( (array) get_user_option( 'post_import_error_log' ) );
			} else {
				$error_log = array();
			}

			include_once GREATTRANSFER_ABSPATH . 'includes/import/class-greattransfer-post-csv-importer.php';

			$importer         = self::get_importer( $file, $params );
			$results          = $importer->import();
			$percent_complete = $importer->get_percent_complete();
			$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

			update_user_option( get_current_user_id(), 'post_import_error_log', $error_log );

			if ( 100 === $percent_complete ) {
				$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_original_id' ) );
				$wpdb->delete(
					$wpdb->posts,
					array(
						'post_type'   => 'post',
						'post_status' => 'importing',
					)
				);
				$wpdb->delete(
					$wpdb->posts,
					array(
						'post_type'   => 'post_variation',
						'post_status' => 'importing',
					)
				);

				$wpdb->query(
					"
					DELETE {$wpdb->posts}.* FROM {$wpdb->posts}
					LEFT JOIN {$wpdb->posts} wp ON wp.ID = {$wpdb->posts}.post_parent
					WHERE wp.ID IS NULL AND {$wpdb->posts}.post_type = 'post_variation'
				"
				);
				$wpdb->query(
					"
					DELETE {$wpdb->postmeta}.* FROM {$wpdb->postmeta}
					LEFT JOIN {$wpdb->posts} wp ON wp.ID = {$wpdb->postmeta}.post_id
					WHERE wp.ID IS NULL
				"
				);
				$wpdb->query(
					"
					DELETE tr.* FROM {$wpdb->term_relationships} tr
					LEFT JOIN {$wpdb->posts} wp ON wp.ID = tr.object_id
					LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE wp.ID IS NULL
					AND tt.taxonomy IN ( '" . implode( "','", array_map( 'esc_sql', get_object_taxonomies( 'post' ) ) ) . "' )
				"
				);

				wp_send_json_success(
					array(
						'position'            => 'done',
						'percentage'          => 100,
						'url'                 => add_query_arg( array( '_wpnonce' => wp_create_nonce( 'greattransfer-csv-importer' ) ), admin_url( 'edit.php?post_type=post&page=post_importer&step=done' ) ),
						'imported'            => is_countable( $results['imported'] ) ? count( $results['imported'] ) : 0,
						'imported_variations' => is_countable( $results['imported_variations'] ) ? count( $results['imported_variations'] ) : 0,
						'failed'              => is_countable( $results['failed'] ) ? count( $results['failed'] ) : 0,
						'updated'             => is_countable( $results['updated'] ) ? count( $results['updated'] ) : 0,
						'skipped'             => is_countable( $results['skipped'] ) ? count( $results['skipped'] ) : 0,
					)
				);
			} else {
				wp_send_json_success(
					array(
						'position'            => $importer->get_file_position(),
						'percentage'          => $percent_complete,
						'imported'            => is_countable( $results['imported'] ) ? count( $results['imported'] ) : 0,
						'imported_variations' => is_countable( $results['imported_variations'] ) ? count( $results['imported_variations'] ) : 0,
						'failed'              => is_countable( $results['failed'] ) ? count( $results['failed'] ) : 0,
						'updated'             => is_countable( $results['updated'] ) ? count( $results['updated'] ) : 0,
						'skipped'             => is_countable( $results['skipped'] ) ? count( $results['skipped'] ) : 0,
					)
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	protected function upload_form() {
		$subtitle     = $this->subtitle();
		$description  = $this->description();
		$update_title = $this->update_title();
		$update_label = $this->update_label();
		$bytes        = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size         = size_format( $bytes );
		$upload_dir   = wp_upload_dir();

		include __DIR__ . '/views/html-post-csv-import-form.php';
	}

	public function upload_form_handler() {
		check_admin_referer( 'greattransfer-csv-importer' );

		$file = $this->handle_upload();

		if ( is_wp_error( $file ) ) {
			$this->add_error( $file->get_error_message() );
			return;
		} else {
			$this->file = $file;
		}

		wp_redirect( esc_url_raw( $this->get_next_step_link() ) );
		exit;
	}

	public function handle_upload() {
		$file_url = isset( $_POST['file_url'] ) ? greattransfer_clean( wp_unslash( $_POST['file_url'] ) ) : '';

		try {
			if ( ! empty( $file_url ) ) {
				$path = ABSPATH . $file_url;
				self::check_file_path( $path );
			} else {
				$csv_import_util = greattransfer_get_container()->get( Automattic\WooCommerce\Internal\Admin\ImportExport\CSVUploadHelper::class );
				$upload          = $csv_import_util->handle_csv_upload( 'post', 'import', self::get_valid_csv_filetypes() );
				$path            = $upload['file'];
			}

			return $path;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'greattransfer_post_csv_importer_upload_invalid_file', $e->getMessage() );
		}
	}

	protected function mapping_form() {
		check_admin_referer( 'greattransfer-csv-importer' );
		self::check_file_path( $this->file );

		$args = array(
			'lines'              => 1,
			'delimiter'          => $this->delimiter,
			'character_encoding' => $this->character_encoding,
		);

		$importer     = self::get_importer( $this->file, $args );
		$headers      = $importer->get_raw_keys();
		$mapped_items = $this->auto_map_columns( $headers );
		$sample       = current( $importer->get_raw_data() );

		if ( empty( $sample ) ) {
			$this->add_error(
				__( 'The file is empty or using a different encoding than UTF-8, please try again with a new file.', 'greattransfer' ),
				array(
					array(
						'url'   => admin_url( 'edit.php?post_type=post&page=post_importer' ),
						'label' => __( 'Upload a new file', 'greattransfer' ),
					),
				)
			);

			$this->output_errors();
			return;
		}

		include_once __DIR__ . '/views/html-csv-import-mapping.php';
	}

	public function import() {
		check_admin_referer( 'greattransfer-csv-importer' );
		self::check_file_path( $this->file );

		if ( ! empty( $_POST['map_from'] ) && ! empty( $_POST['map_to'] ) ) {
			$mapping_from = greattransfer_clean( wp_unslash( $_POST['map_from'] ) );
			$mapping_to   = greattransfer_clean( wp_unslash( $_POST['map_to'] ) );

			update_user_option( get_current_user_id(), 'greattransfer_post_import_mapping', $mapping_to );
		} else {
			wp_redirect( esc_url_raw( $this->get_next_step_link( 'upload' ) ) );
			exit;
		}

		wp_localize_script(
			'greattransfer-post-import',
			'greattransfer_post_import_params',
			array(
				'import_nonce'       => wp_create_nonce( 'greattransfer-post-import' ),
				'mapping'            => array(
					'from' => $mapping_from,
					'to'   => $mapping_to,
				),
				'file'               => $this->file,
				'update_existing'    => $this->update_existing,
				'delimiter'          => $this->delimiter,
				'character_encoding' => $this->character_encoding,
			)
		);
		wp_enqueue_script( 'greattransfer-post-import' );

		include_once __DIR__ . '/views/html-csv-import-progress.php';
	}

	protected function done() {
		check_admin_referer( 'greattransfer-csv-importer' );
		$imported            = isset( $_GET['posts-imported'] ) ? absint( $_GET['posts-imported'] ) : 0;
		$imported_variations = isset( $_GET['posts-imported-variations'] ) ? absint( $_GET['posts-imported-variations'] ) : 0;
		$updated             = isset( $_GET['posts-updated'] ) ? absint( $_GET['posts-updated'] ) : 0;
		$failed              = isset( $_GET['posts-failed'] ) ? absint( $_GET['posts-failed'] ) : 0;
		$skipped             = isset( $_GET['posts-skipped'] ) ? absint( $_GET['posts-skipped'] ) : 0;
		$file_name           = isset( $_GET['file-name'] ) ? sanitize_text_field( wp_unslash( $_GET['file-name'] ) ) : '';
		$errors              = array_filter( (array) get_user_option( 'post_import_error_log' ) );

		include_once __DIR__ . '/views/html-csv-import-done.php';
	}

	protected function normalize_columns_names( $columns ) {
		$normalized = array();

		foreach ( $columns as $key => $value ) {
			$normalized[ strtolower( $key ) ] = $value;
		}

		return $normalized;
	}

	protected function auto_map_columns( $raw_headers, $num_indexes = true ) {
		$weight_unit_label    = I18nUtil::get_weight_unit_label( get_option( 'greattransfer_weight_unit', 'kg' ) );
		$dimension_unit_label = I18nUtil::get_dimensions_unit_label( get_option( 'greattransfer_dimension_unit', 'cm' ) );

		$default_columns = $this->normalize_columns_names(
			apply_filters(
				'greattransfer_csv_post_import_mapping_default_columns',
				array(
					__( 'ID', 'greattransfer' )   => 'id',
					__( 'Name', 'greattransfer' ) => 'name',
				),
				$raw_headers
			)
		);

		$special_columns = $this->get_special_columns(
			$this->normalize_columns_names(
				apply_filters(
					'greattransfer_csv_post_import_mapping_special_columns',
					array(
						/* translators: %s: Meta name/number */
						__( 'Meta: %s', 'greattransfer' ) => 'meta:',
					),
					$raw_headers
				)
			)
		);

		$headers = array();
		foreach ( $raw_headers as $key => $field ) {
			$normalized_field  = strtolower( $field );
			$index             = $num_indexes ? $key : $field;
			$headers[ $index ] = $normalized_field;

			if ( isset( $default_columns[ $normalized_field ] ) ) {
				$headers[ $index ] = $default_columns[ $normalized_field ];
			} else {
				foreach ( $special_columns as $regex => $special_key ) {
					if ( preg_match( $regex, $field, $matches ) ) {
						$headers[ $index ] = $special_key . $matches[1];
						break;
					}
				}
			}
		}

		return apply_filters( 'greattransfer_csv_post_import_mapped_columns', $headers, $raw_headers );
	}

	public function auto_map_user_preferences( $headers ) {
		$mapping_preferences = get_user_option( 'greattransfer_post_import_mapping' );

		if ( ! empty( $mapping_preferences ) && is_array( $mapping_preferences ) ) {
			return $mapping_preferences;
		}

		return $headers;
	}

	protected function sanitize_special_column_name_regex( $value ) {
		return '/' . str_replace( array( '%d', '%s' ), '(.*)', trim( quotemeta( $value ) ) ) . '/i';
	}

	protected function get_special_columns( $columns ) {
		$formatted = array();

		foreach ( $columns as $key => $value ) {
			$regex = $this->sanitize_special_column_name_regex( $key );

			$formatted[ $regex ] = $value;
		}

		return $formatted;
	}

	protected function get_mapping_options( $item = '' ) {
		$index = $item;

		if ( preg_match( '/\d+/', $item, $matches ) ) {
			$index = $matches[0];
		}

		$meta = str_replace( 'meta:', '', $item );

		$options = array(
			'id'            => __( 'ID', 'greattransfer' ),
			'name'          => __( 'Name', 'greattransfer' ),
			'meta:' . $meta => __( 'Import as meta data', 'greattransfer' ),
		);

		return apply_filters( 'greattransfer_csv_post_import_mapping_options', $options, $item );
	}
}
