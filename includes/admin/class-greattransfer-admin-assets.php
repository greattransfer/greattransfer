<?php

use GreatTransfer\GreatTransfer\Constants;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GreatTransfer_Admin_Assets', false ) ) :

	class GreatTransfer_Admin_Assets {

		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		}

		public function admin_styles() {
			global $wp_scripts;

			$version   = Constants::get_constant( 'GREATTRANSFER_VERSION' );
			$screen    = get_current_screen();
			$screen_id = $screen ? $screen->id : '';

			wp_register_style(
				'select2',
				GreatTransfer()->plugin_url() . '/assets/css/select2.css',
				array(),
				$version
			);
			wp_register_style(
				'greattransfer_admin_styles',
				GreatTransfer()->plugin_url() . '/assets/css/admin.css',
				array( 'select2' ),
				$version
			);

			wp_style_add_data( 'greattransfer_admin_styles', 'rtl', 'replace' );

			if ( in_array( $screen_id, greattransfer_get_screen_ids(), true ) ) {
				wp_enqueue_style( 'greattransfer_admin_styles' );
			}
		}

		public function admin_scripts() {
			global $post_type;

			$screen    = get_current_screen();
			$screen_id = $screen ? $screen->id : '';
			$suffix    = Constants::is_true( 'SCRIPT_DEBUG' ) ? '' : '.min';
			$version   = Constants::get_constant( 'GREATTRANSFER_VERSION' );

			wp_register_script(
				'greattransfer_admin',
				GreatTransfer()->plugin_url() . '/assets/js/admin/greattransfer_admin' . $suffix . '.js',
				array(),
				$version,
				array( 'in_footer' => true )
			);
			wp_register_script(
				'selectWoo',
				GreatTransfer()->plugin_url() . '/assets/js/selectWoo/selectWoo.full' . $suffix . '.js',
				array( 'jquery' ),
				'1.0.6',
				array( 'in_footer' => true )
			);
			wp_register_script(
				'greattransfer-enhanced-select',
				GreatTransfer()->plugin_url() . '/assets/js/admin/greattransfer-enhanced-select' . $suffix . '.js',
				array( 'jquery', 'selectWoo' ),
				$version,
				array( 'in_footer' => true )
			);

			wp_localize_script(
				'greattransfer-enhanced-select',
				'greattransferEnhancedSelectParams',
				array(
					'i18n_no_matches'                 => _x( 'No matches found', 'enhanced select', 'greattransfer' ),
					'i18n_ajax_error'                 => _x( 'Loading failed', 'enhanced select', 'greattransfer' ),
					'i18n_input_too_short_1'          => _x( 'Please enter 1 or more characters', 'enhanced select', 'greattransfer' ),
					'i18n_input_too_short_n'          => _x( 'Please enter %qty% or more characters', 'enhanced select', 'greattransfer' ),
					'i18n_input_too_long_1'           => _x( 'Please delete 1 character', 'enhanced select', 'greattransfer' ),
					'i18n_input_too_long_n'           => _x( 'Please delete %qty% characters', 'enhanced select', 'greattransfer' ),
					'i18n_selection_too_long_1'       => _x( 'You can only select 1 item', 'enhanced select', 'greattransfer' ),
					'i18n_selection_too_long_n'       => _x( 'You can only select %qty% items', 'enhanced select', 'greattransfer' ),
					'i18n_load_more'                  => _x( 'Loading more results&hellip;', 'enhanced select', 'greattransfer' ),
					'i18n_searching'                  => _x( 'Searching&hellip;', 'enhanced select', 'greattransfer' ),
					'ajax_url'                        => admin_url( 'admin-ajax.php' ),
					'search_products_nonce'           => wp_create_nonce( 'search-products' ),
					'search_customers_nonce'          => wp_create_nonce( 'search-customers' ),
					'search_categories_nonce'         => wp_create_nonce( 'search-categories' ),
					'search_taxonomy_terms_nonce'     => wp_create_nonce( 'search-taxonomy-terms' ),
					'search_product_attributes_nonce' => wp_create_nonce( 'search-product-attributes' ),
					'search_pages_nonce'              => wp_create_nonce( 'search-pages' ),
				)
			);

			if ( in_array( $screen_id, greattransfer_get_screen_ids(), true ) ) {
				wp_enqueue_script( 'greattransfer_admin' );
				wp_enqueue_script( 'greattransfer-enhanced-select' );

				$url = add_query_arg( 'post_type', $post_type, admin_url( 'edit.php' ) );

				$url_importer = add_query_arg( 'page', $post_type . '_importer', $url );
				$url_exporter = add_query_arg( 'page', $post_type . '_exporter', $url );

				$params = array(
					'post_type' => $post_type,
					'strings'   => array(
						'import' => __( 'Import', 'greattransfer' ),
						'export' => __( 'Export', 'greattransfer' ),
					),
					'urls'      => array(
						'import' => current_user_can( 'import' ) ? esc_url_raw( $url_importer ) : null,
						'export' => current_user_can( 'export' ) ? esc_url_raw( $url_exporter ) : null,
					),
				);

				wp_localize_script( 'greattransfer_admin', 'greattransferAdmin', $params );
			}
		}
	}
endif;

return new GreatTransfer_Admin_Assets();
