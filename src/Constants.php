<?php

namespace GreatTransfer\GreatTransfer;

class Constants {

	public static $set_constants = array();


	public static function is_true( $name ) {
		return self::is_defined( $name ) && self::get_constant( $name );
	}

	public static function is_defined( $name ) {
		return array_key_exists( $name, self::$set_constants )
			? true
			: defined( $name );
	}

	public static function get_constant( $name ) {
		if ( array_key_exists( $name, self::$set_constants ) ) {
			return self::$set_constants[ $name ];
		}

		if ( defined( $name ) ) {
			return constant( $name );
		}

		return apply_filters( 'greattransfer_constant_default_value', null, $name );
	}

	public static function set_constant( $name, $value ) {
		self::$set_constants[ $name ] = $value;
	}

	public static function clear_single_constant( $name ) {
		if ( ! array_key_exists( $name, self::$set_constants ) ) {
			return false;
		}

		unset( self::$set_constants[ $name ] );

		return true;
	}

	public static function clear_constants() {
		self::$set_constants = array();
	}
}
