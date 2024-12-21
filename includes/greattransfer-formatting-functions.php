<?php

defined( 'ABSPATH' ) || exit;

function greattransfer_clean( $var ) {
	if ( is_array( $var ) ) {
		return array_map( 'greattransfer_clean', $var );
	} else {
		return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
	}
}
