<?php
declare(strict_types=1);

namespace GilbertoTavares\GreatTransfer;

use GilbertoTavares\GreatTransfer\Constants;

class Import {

	protected $importers = array();

	protected $prefix;

	protected $plugin_file;

	protected $version;

	protected $i18n;

	public function __construct( $plugin_file, $args = array() ) {
		$this->plugin_file = $plugin_file;

		$i18n = isset( $args['i18n'] ) ? $args['i18n'] : array();

		$defaults = array(
			'prefix'  => 'greattransfer',
			'version' => '1.0.0',
		);

		$args = wp_parse_args( $args, $defaults );

		$this->prefix  = $args['prefix'];
		$this->version = $args['version'];

		$defaults = array(
			'import'   => 'Import',
			's_import' => '%s Import',
		);

		$this->i18n = (object) wp_parse_args( $i18n, $defaults );

		add_action( 'init', array( $this, 'init' ), 12 );
	}

	public function init() {
		if ( ! $this->import_allowed() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_menu', array( $this, 'add_to_menus' ) );
		$post_types = $this->post_types();

		foreach ( $post_types as $post_type ) {
			$menu_slug = "{$post_type}_importer";

			$post_type_object = $this->get_post_type_object( $post_type );

			$page_title = $this->i18n->s_import;

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

    private function get_vendor_dir(): string {
        return 'vendor';
    }

    private function get_package_name(): string
    {
        $composer_json_path = realpath( __DIR__ . '/../composer.json' );

		$composer_data = json_decode( file_get_contents( $composer_json_path ), true );

		return $composer_data['name'];
    }

    private function get_relative_dir(): string {
        return untrailingslashit( $this->get_vendor_dir() ) . '/' . $this->get_package_name();
    }

	private function library_path() {
		return untrailingslashit( plugin_dir_path( $this->plugin_file ) . $this->get_relative_dir() );
	}

	private function library_url(): string {
		return untrailingslashit( plugins_url( '/', $this->plugin_file ) . $this->get_relative_dir() );
	}

	private function get_screen_ids() {
		$screen_ids = array();

		$post_types = $this->post_types();

		foreach ( $post_types as $post_type ) {
			$id = $post_type;

			switch ( $id ) {
				case 'page':
					$id .= 's';
					break;
				case 'post':
					$id = 'admin';
					break;
			}

			$screen_ids[] = 'edit-' . $post_type;
			$screen_ids[] = $id . '_page_' . $post_type . '_importer';
		}

		return apply_filters( $this->prefix . '_screen_ids', $screen_ids );
	}

	public function admin_scripts() {
		global $post_type;

		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		$suffix    = Constants::is_true( 'SCRIPT_DEBUG' ) ? '' : '.min';
		$version   = $this->version;

		wp_register_script(
			$this->prefix . '_admin_importer',
			$this->library_url() . '/assets/js/greattransfer-import' . $suffix . '.js',
			array(),
			$version,
			array( 'in_footer' => true )
		);

		if ( in_array( $screen_id, $this->get_screen_ids(), true ) ) {
			wp_enqueue_script( $this->prefix . '_admin_importer' );

			$url = add_query_arg( 'post_type', $post_type, admin_url( 'edit.php' ) );

			$url = add_query_arg( 'page', $post_type . '_importer', $url );

			$params = array(
				'post_type' => $post_type,
				'string'    => $this->i18n->import,
				'url'       => current_user_can( 'import' ) ? esc_url_raw( $url ) : null,
			);
			wp_localize_script( $this->prefix . '_admin', 'greattransferAdmin', $params );
		}
	}

	public function post_types() {
		$post_types = array_values( get_post_types() );

		$exclude_post_types = array(
			'post',
			'page',
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
		);

		$post_types = array_values( array_diff( $post_types, $exclude_post_types ) );

		return apply_filters( 'greattransfer_post_types', $post_types );
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

	protected function import_allowed( $post_type = null ) {
		if ( ! current_user_can( 'import' ) ) {
			return false;
		}
		if ( is_null( $post_type ) ) {
			$post_types = $this->post_types();
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
}
