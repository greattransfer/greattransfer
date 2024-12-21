<?php

use GreatTransfer\GreatTransfer\Constants;

defined( 'ABSPATH' ) || exit;

class GreatTransfer_Admin_Importers {

	protected $importers = array();

	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 12 );
	}

	public function init() {
		if ( ! $this->import_allowed() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'add_to_menus' ) );
		add_action( 'admin_init', array( $this, 'register_importers' ) );
		add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'wp_ajax_greattransfer_do_ajax_post_import', array( $this, 'do_ajax_post_import' ) );
		add_action( 'in_admin_footer', array( $this, 'track_importer_exporter_view' ) );
		$post_types = greattransfer_post_types();

		foreach ( $post_types as $post_type ) {
			$menu_slug = "{$post_type}_importer";

			$post_type_object = $this->get_post_type_object( $post_type );

			/* translators: Post/Taxonomy labels singular_name */
			$page_title = __( '%s Import', 'greattransfer' );

			$singular_name = $post_type_object->labels->singular_name;
			if ( ! str_starts_with( $page_title, '%s' ) ) {
				$singular_name = mb_strtolower( $singular_name );
			}

			$this->importers[ $menu_slug ] = array(
				'parent_slug' => add_query_arg( 'post_type', $post_type, 'edit.php' ),
				'page_title'  => sprintf( $page_title, $singular_name ),
				'capability'  => 'import',
				'callback'    => array( $this, 'post_importer' ),
			);
		}
	}

	protected function import_allowed( $post_type = null ) {
		if ( ! current_user_can( 'import' ) ) {
			return false;
		}
		if ( is_null( $post_type ) ) {
			$post_types = greattransfer_post_types();
		} else {
			$post_types = (array) $post_type;
		}
		foreach ( $post_types as $post_type ) {
			$post_type = $this->get_post_type_object( $post_type );
			if ( ! current_user_can( $post_type->cap->edit_posts ) ) {
				return false;
			}
		}
		return true;
	}

	public function add_to_menus() {
		foreach ( $this->importers as $menu_slug => $importer ) {
			add_submenu_page(
				$importer['parent_slug'],
				$importer['page_title'],
				$importer['page_title'],
				$importer['capability'],
				$menu_slug,
				$importer['callback']
			);
		}
	}

	public function hide_from_menus() {
		global $submenu;

		foreach ( $this->importers as $id => $importer ) {
			if ( isset( $submenu[ $importer['parent_slug'] ] ) ) {
				foreach ( $submenu[ $importer['parent_slug'] ] as $key => $menu ) {
					if ( $id === $menu[2] ) {
						unset( $submenu[ $importer['parent_slug'] ][ $key ] );
					}
				}
			}
		}
	}

	public function admin_scripts() {
		$suffix  = Constants::is_true( 'SCRIPT_DEBUG' ) ? '' : '.min';
		$version = Constants::get_constant( 'GREATTRANSFER_VERSION' );
		wp_register_script(
			'greattransfer-post-import',
			GreatTransfer()->plugin_url() . '/assets/js/admin/greattransfer-post-import' . $suffix . '.js',
			array(),
			$version,
			array( 'in_footer' => true )
		);
	}

	public function post_importer() {
		if ( defined( 'WP_LOAD_IMPORTERS' ) ) {
			wp_safe_redirect(
				add_query_args(
					array(
						'post_type' => 'greattransfer',
						'page'      => 'greattransfer_importer',
						'source'    => 'wordpress-importer',
					),
					admin_url( 'edit.php' )
				)
			);
			exit;
		}

		include_once GREATTRANSFER_ABSPATH . 'includes/import/class-greattransfer-post-csv-importer.php';
		include_once GREATTRANSFER_ABSPATH . 'includes/admin/importers/class-greattransfer-post-csv-importer-controller.php';

		$importer = new GreatTransfer_Post_CSV_Importer_Controller();
		$importer->dispatch();
	}

	private function get_post_type_object( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		return (object) array(
			'name'   => $post_type_object->name,
			'cap'    => (object) array(
				'edit_posts' => $post_type_object->cap->edit_posts,
			),
			'labels' => (object) array(
				'name'          => $post_type_object->labels->name,
				'singular_name' => $post_type_object->labels->singular_name,
				'all_items'     => $post_type_object->labels->all_items,
			),
		);
	}

	public function register_importers() {
		if ( defined( 'WP_LOAD_IMPORTERS' ) ) {
			add_action( 'import_start', array( $this, 'post_importer_compatibility' ) );
			$post_types = greattransfer_post_types();
			$names      = array();
			foreach ( $post_types as $post_type ) {
				$post_type = $this->get_post_type_object( $post_type );
				$names[]   = mb_strtolower( $post_type->name );
			}
			register_importer(
				'greattransfer_post_csv',
				__( 'Posts, Pages and Customs (CSV)', 'greattransfer' ),
				sprintf(
					/* translators: %s: Post/Taxonomy labels names */
					__( 'Import <strong>%s</strong> to your site via a csv file.', 'greattransfer' ),
					greattransfer_list_with_and( $names )
				),
				array( $this, 'post_importer' )
			);
		}
	}

	public function post_importer_compatibility() {
		global $wpdb;

		if ( empty( $_POST['import_id'] ) || ! class_exists( 'WXR_Parser' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$id          = absint( $_POST['import_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$file        = get_attached_file( $id );
		$parser      = new WXR_Parser();
		$import_data = $parser->parse( $file );

		if ( isset( $import_data['posts'] ) && ! empty( $import_data['posts'] ) ) {
			foreach ( $import_data['posts'] as $post ) {
				if ( 'post' === $post['post_type'] && ! empty( $post['terms'] ) ) {
					foreach ( $post['terms'] as $term ) {
						if ( strstr( $term['domain'], 'pa_' ) ) {
							if ( ! taxonomy_exists( $term['domain'] ) ) {
								$attribute_name = greattransfer_attribute_taxonomy_slug( $term['domain'] );

								if ( ! in_array( $attribute_name, greattransfer_get_attribute_taxonomies(), true ) ) {
									greattransfer_create_attribute(
										array(
											'name'         => $attribute_name,
											'slug'         => $attribute_name,
											'type'         => 'select',
											'order_by'     => 'menu_order',
											'has_archives' => false,
										)
									);
								}

								register_taxonomy(
									$term['domain'],
									apply_filters( 'greattransfer_taxonomy_objects_' . $term['domain'], array( 'post' ) ),
									apply_filters(
										'greattransfer_taxonomy_args_' . $term['domain'],
										array(
											'hierarchical' => true,
											'show_ui'      => false,
											'query_var'    => true,
											'rewrite'      => false,
										)
									)
								);
							}
						}
					}
				}
			}
		}
	}

	public function do_ajax_post_import() {
		$post_type = isset( $_POST['post_type'] ) ? greattransfer_clean( wp_unslash( $_POST['post_type'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $this->import_allowed( $post_type ) ) {
			$post_type_object = $this->get_post_type_object( $post_type );

			/* translators: Post/Taxonomy labels name (plural) */
			$message = __( 'Insufficient privileges to import %s.', 'greattransfer' );

			$name = $post_type_object->name;
			if ( ! str_starts_with( $message, '%s' ) ) {
				$name = mb_strtolower( $name );
			}

			wp_send_json_error( array( 'message' => sprintf( $message, $name ) ) );
		}

		include_once GREATTRANSFER_ABSPATH . 'includes/admin/importers/class-greattransfer-post-csv-importer-controller.php';
		GreatTransfer_Post_CSV_Importer_Controller::dispatch_ajax();
	}
}

new GreatTransfer_Admin_Importers();
