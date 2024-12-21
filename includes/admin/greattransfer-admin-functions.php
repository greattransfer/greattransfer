<?php

defined( 'ABSPATH' ) || exit;

function greattransfer_get_screen_ids() {
	$screen_ids = array();

	$post_types = greattransfer_post_types();

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
		$screen_ids[] = $id . '_page_' . $post_type . '_exporter';
	}

	return apply_filters( 'greattransfer_screen_ids', $screen_ids );
}

function greattransfer_post_types() {
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

	if ( class_exists( 'WooCommerce' ) ) {
		$woocommerce_post_types = array(
			'product',
			'product_variation',
		);

		if ( \Automattic\WooCommerce\Admin\Features\Features::is_enabled( 'product-editor-template-system' ) ) {
			$woocommerce_post_types[] = 'product_form';
		}

		if ( 'yes' === get_option( 'woocommerce_enable_coupons' ) ) {
			$woocommerce_post_types[] = 'shop_coupon';
		}

		$woocommerce_post_types = array_merge( $woocommerce_post_types, wc_get_order_types() );

		$woocommerce_post_types[] = \Automattic\WooCommerce\Blocks\Patterns\AIPatterns::PATTERNS_AI_DATA_POST_TYPE;

		$exclude_post_types = array_merge( $exclude_post_types, $woocommerce_post_types );
	}

	$post_types = array_values( array_diff( $post_types, $exclude_post_types ) );

	return apply_filters( 'greattransfer_post_types', $post_types );
}

function greattransfer_jet_engine_post_types( $post_types ) {
	if ( class_exists( 'Jet_Engine' ) ) {
		$post_types = array_values( array_diff( $post_types, array( 'jet-engine' ) ) );
	}
	return $post_types;
}
add_filter( 'greattransfer_post_types', 'greattransfer_jet_engine_post_types' );
