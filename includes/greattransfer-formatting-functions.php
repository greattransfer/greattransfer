<?php

defined( 'ABSPATH' ) || exit;

function greattransfer_clean( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'greattransfer_clean', $value );
	} else {
		return is_scalar( $value ) ? sanitize_text_field( $value ) : $value;
	}
}

function greattransfer_list_with_and( $items ) {
	if ( count( $items ) <= 1 ) {
		return implode( '', $items );
	}

	$last_item = array_pop( $items );
	/* translators: %1$s: first list items, %1$s: last list item */
	return sprintf( __( '%1$s and %2$s', 'greattransfer' ), implode( ', ', $items ), $last_item );
}
