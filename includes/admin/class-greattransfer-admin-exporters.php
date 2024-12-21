<?php

use GreatTransfer\GreatTransfer\Constants;

defined( 'ABSPATH' ) || exit;

class GreatTransfer_Admin_Exporters {

	protected $exporters = array();

	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 12 );
	}

	public function init() {
		if ( ! $this->export_allowed() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'add_to_menus' ) );
		add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'wp_ajax_greattransfer_do_ajax_post_export', array( $this, 'do_ajax_post_export' ) );

		$post_types = greattransfer_post_types();

		foreach ( $post_types as $post_type ) {
			$menu_slug = "{$post_type}_exporter";

			$post_type_object = get_post_type_object( $post_type );

			/* translators: Post/Taxonomy labels singular_name */
			$page_title = __( '%s Export', 'greattransfer' );

			$singular_name = $post_type_object->labels->singular_name;
			if ( ! str_starts_with( $page_title, '%s' ) ) {
				$singular_name = mb_strtolower( $singular_name );
			}

			$this->exporters[ $menu_slug ] = array(
				'parent_slug' => add_query_arg( 'post_type', $post_type, 'edit.php' ),
				'page_title'  => sprintf( $page_title, $singular_name ),
				'capability'  => 'export',
				'callback'    => array( $this, 'post_exporter' ),
			);
		}
	}

	protected function export_allowed( $post_type = null ) {
		if ( ! current_user_can( 'export' ) ) {
			return false;
		}
		if ( is_null( $post_type ) ) {
			$post_types = greattransfer_post_types();
		} else {
			$post_types = (array) $post_type;
		}
		foreach ( $post_types as $post_type ) {
			$post_type = get_post_type_object( $post_type );
			if ( ! current_user_can( $post_type->cap->edit_posts ) ) {
				return false;
			}
		}
		return true;
	}

	public function add_to_menus() {
		foreach ( $this->exporters as $menu_slug => $exporter ) {
			add_submenu_page(
				$exporter['parent_slug'],
				$exporter['page_title'],
				$exporter['page_title'],
				$exporter['capability'],
				$menu_slug,
				$exporter['callback']
			);
		}
	}

	public function hide_from_menus() {
		global $submenu;

		foreach ( $this->exporters as $id => $exporter ) {
			if ( isset( $submenu[ $exporter['parent_slug'] ] ) ) {
				foreach ( $submenu[ $exporter['parent_slug'] ] as $key => $menu ) {
					if ( $id === $menu[2] ) {
						unset( $submenu[ $exporter['parent_slug'] ][ $key ] );
					}
				}
			}
		}
	}

	public function admin_scripts() {
		global $typenow;
		$suffix  = Constants::is_true( 'SCRIPT_DEBUG' ) ? '' : '.min';
		$version = Constants::get_constant( 'GREATTRANSFER_VERSION' );
		wp_register_script(
			'greattransfer-post-export',
			GreatTransfer()->plugin_url() . '/assets/js/admin/greattransfer-post-export' . $suffix . '.js',
			array(),
			$version,
			array( 'in_footer' => true )
		);
		$current_post_type = $typenow;
		wp_localize_script(
			'greattransfer-post-export',
			'greattransferPostExportParams',
			array(
				'post_type'    => $current_post_type,
				'export_nonce' => wp_create_nonce( 'greattransfer-' . $current_post_type . '-export' ),
			)
		);
	}

	public function post_exporter() {
		include_once GREATTRANSFER_ABSPATH . 'includes/export/class-greattransfer-post-csv-exporter.php';
		include_once __DIR__ . '/views/html-admin-page-post-export.php';
	}

	public function do_ajax_post_export() {
		$current_post_type = null;
		if ( isset( $_POST['post_type'] ) ) {
			$current_post_type = greattransfer_clean( wp_unslash( $_POST['post_type'] ) );
		}
		check_ajax_referer( 'greattransfer-' . $current_post_type . '-export', 'security' );

		if ( ! $this->export_allowed( $current_post_type ) ) {
			$post_type_object = get_post_type_object( $current_post_type );

			/* translators: Post/Taxonomy labels name (plural) */
			$message = __( 'Insufficient privileges to export %s.', 'greattransfer' );

			$name = $post_type_object->labels->name;
			if ( ! str_starts_with( $message, '%s' ) ) {
				$name = mb_strtolower( $name );
			}

			wp_send_json_error( array( 'message' => sprintf( $message, $name ) ) );
		}

		include_once GREATTRANSFER_ABSPATH . 'includes/export/class-greattransfer-post-csv-exporter.php';

		$step     = isset( $_POST['step'] ) ? absint( greattransfer_clean( wp_unslash( $_POST['step'] ) ) ) : 1;
		$exporter = new GreatTransfer_Post_CSV_Exporter( $post_type );

		$selected_post_statuses = null;
		if ( isset( $_POST['selected_post_statuses'] ) ) {
			$selected_post_statuses = greattransfer_clean( wp_unslash( $_POST['selected_post_statuses'] ) );
		}
		if ( ! empty( $selected_post_statuses ) ) {
			$exporter->set_post_statuses_to_export( $selected_post_statuses );
		}

		$columns = isset( $_POST['columns'] ) ? greattransfer_clean( wp_unslash( $_POST['columns'] ) ) : null;
		if ( ! empty( $columns ) ) {
			$exporter->set_column_names( $columns );
		}

		$selected_columns = null;
		if ( isset( $_POST['selected_columns'] ) ) {
			$selected_columns = greattransfer_clean( wp_unslash( $_POST['selected_columns'] ) );
		}
		if ( ! empty( $selected_columns ) ) {
			$exporter->set_columns_to_export( $selected_columns );
		}

		$export_meta = null;
		if ( isset( $_POST['export_meta'] ) ) {
			$export_meta = greattransfer_clean( wp_unslash( $_POST['export_meta'] ) );
		}
		if ( ! empty( $export_meta ) ) {
			$exporter->enable_meta_export( $export_meta );
		}

		$export_taxonomies = null;
		if ( isset( $_POST['export_taxonomies'] ) ) {
			$export_taxonomies = greattransfer_clean( wp_unslash( $_POST['export_taxonomies'] ) );
		}
		if ( ! empty( $export_taxonomies ) ) {
			$exporter->set_post_taxonomies_to_export( $export_taxonomies );
		}

		$filename = isset( $_POST['filename'] ) ? greattransfer_clean( wp_unslash( $_POST['filename'] ) ) : null;
		if ( ! empty( $filename ) ) {
			$exporter->set_filename( $filename );
		}

		$exporter->set_page( $step );
		$exporter->generate_file();

		$query_args = apply_filters(
			'greattransfer_export_get_ajax_query_args',
			array(
				'nonce'    => wp_create_nonce( 'product-csv' ),
				'action'   => 'download_product_csv',
				'filename' => $exporter->get_filename(),
			)
		);

		if ( 100 === $exporter->get_percent_complete() ) {
			wp_send_json_success(
				array(
					'step'       => 'done',
					'percentage' => 100,
					'url'        => add_query_arg(
						$query_args,
						add_query_arg(
							array(
								'post_type' => $post_type,
								'page'      => $post_type . '_exporter',
							),
							admin_url( 'edit.php' )
						)
					),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'step'       => ++$step,
					'percentage' => $exporter->get_percent_complete(),
					'columns'    => $exporter->get_column_names(),
				)
			);
		}
	}
}

new GreatTransfer_Admin_Exporters();
