<?php

namespace GreatTransfer\GreatTransfer\Utilities;

class ArrayUtil {

	public const SELECT_BY_AUTO = 0;

	public const SELECT_BY_OBJECT_METHOD = 1;

	public const SELECT_BY_OBJECT_PROPERTY = 2;

	public const SELECT_BY_ARRAY_KEY = 3;

	public static function get_nested_value( array $items, string $key, $default_value = null ) {
		$key_stack = explode( '::', $key );
		$subkey    = array_shift( $key_stack );

		if ( isset( $items[ $subkey ] ) ) {
			$value = $items[ $subkey ];

			if ( count( $key_stack ) ) {
				foreach ( $key_stack as $subkey ) {
					if ( is_array( $value ) && isset( $value[ $subkey ] ) ) {
						$value = $value[ $subkey ];
					} else {
						$value = $default_value;
						break;
					}
				}
			}
		} else {
			$value = $default_value;
		}

		return $value;
	}

	public static function is_truthy( array $items, string $key ) {
		return isset( $items[ $key ] ) && $items[ $key ];
	}

	public static function get_value_or_default( array $items, string $key, $default_value = null ) {
		return array_key_exists( $key, $items ) ? $items[ $key ] : $default_value;
	}

	public static function to_ranges_string( array $items, string $item_separator = ', ', string $range_separator = '-', bool $sort = true ): string {
		if ( $sort ) {
			sort( $items );
		}

		$point = null;
		$range = false;
		$str   = '';

		foreach ( $items as $i ) {
			if ( null === $point ) {
				$str .= $i;
			} elseif ( ( $point + 1 ) === $i ) {
				$range = true;
			} else {
				if ( $range ) {
					$str  .= $range_separator . $point;
					$range = false;
				}
				$str .= $item_separator . $i;
			}
			$point = $i;
		}

		if ( $range ) {
			$str .= $range_separator . $point;
		}

		return $str;
	}

	private static function get_selector_callback( string $selector_name, int $selector_type = self::SELECT_BY_AUTO ): \Closure {
		if ( self::SELECT_BY_OBJECT_METHOD === $selector_type ) {
			$callback = function ( $item ) use ( $selector_name ) {
				return $item->$selector_name();
			};
		} elseif ( self::SELECT_BY_OBJECT_PROPERTY === $selector_type ) {
			$callback = function ( $item ) use ( $selector_name ) {
				return $item->$selector_name;
			};
		} elseif ( self::SELECT_BY_ARRAY_KEY === $selector_type ) {
			$callback = function ( $item ) use ( $selector_name ) {
				return $item[ $selector_name ];
			};
		} else {
			$callback = function ( $item ) use ( $selector_name ) {
				if ( is_array( $item ) ) {
					return $item[ $selector_name ];
				} elseif ( method_exists( $item, $selector_name ) ) {
					return $item->$selector_name();
				} else {
					return $item->$selector_name;
				}
			};
		}
		return $callback;
	}

	public static function select( array $items, string $selector_name, int $selector_type = self::SELECT_BY_AUTO ): array {
		$callback = self::get_selector_callback( $selector_name, $selector_type );
		return array_map( $callback, $items );
	}

	public static function select_as_assoc( array $items, string $selector_name, int $selector_type = self::SELECT_BY_AUTO ): array {
		$selector_callback = self::get_selector_callback( $selector_name, $selector_type );
		$result            = array();
		foreach ( $items as $item ) {
			$key = $selector_callback( $item );
			self::ensure_key_is_array( $result, $key );
			$result[ $key ][] = $item;
		}
		return $result;
	}

	public static function deep_compare_array_diff( array $array1, array $array2, bool $strict = true ) {
		return self::deep_compute_or_compare_array_diff( $array1, $array2, true, $strict );
	}

	public static function deep_assoc_array_diff( array $array1, array $array2, bool $strict = true ): array {
		return self::deep_compute_or_compare_array_diff( $array1, $array2, false, $strict );
	}

	private static function deep_compute_or_compare_array_diff( array $array1, array $array2, bool $compare, bool $strict = true ) {
		$diff = array();
		foreach ( $array1 as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( ! array_key_exists( $key, $array2 ) || ! is_array( $array2[ $key ] ) ) {
					if ( $compare ) {
						return true;
					}
					$diff[ $key ] = $value;
					continue;
				}
				$new_diff = self::deep_assoc_array_diff( $value, $array2[ $key ], $strict );
				if ( ! empty( $new_diff ) ) {
					if ( $compare ) {
						return true;
					}
					$diff[ $key ] = $new_diff;
				}
			} elseif ( $strict ) {
				if ( ! array_key_exists( $key, $array2 ) || $value !== $array2[ $key ] ) {
					if ( $compare ) {
						return true;
					}
					$diff[ $key ] = $value;
				}
			} elseif ( ! array_key_exists( $key, $array2 ) || $value !== $array2[ $key ] ) {
				if ( $compare ) {
					return true;
				}
				$diff[ $key ] = $value;
			}
		}

		return $compare ? false : $diff;
	}

	public static function push_once( array &$items, $value ): bool {
		if ( in_array( $value, $items, true ) ) {
			return false;
		}

		$items[] = $value;
		return true;
	}

	public static function ensure_key_is_array( array &$items, string $key, bool $throw_if_existing_is_not_array = false ): bool {
		if ( ! isset( $items[ $key ] ) ) {
			$items[ $key ] = array();
			return true;
		}

		if ( $throw_if_existing_is_not_array && ! is_array( $items[ $key ] ) ) {
			$type = is_object( $items[ $key ] ) ? get_class( $items[ $key ] ) : gettype( $items[ $key ] );
			throw new \Exception( esc_html( "Array key exists but it's not an array, it's a {$type}" ) );
		}

		return false;
	}

	public static function group_by_column( array $items, string $column, bool $single_values = false ): array {
		if ( $single_values ) {
			return array_combine( array_column( $items, $column ), array_values( $items ) );
		}

		$distinct_column_values = array_unique( array_column( $items, $column ), SORT_REGULAR );
		$result                 = array_fill_keys( $distinct_column_values, array() );

		foreach ( $items as $value ) {
			$result[ $value[ $column ] ][] = $value;
		}

		return $result;
	}
}
