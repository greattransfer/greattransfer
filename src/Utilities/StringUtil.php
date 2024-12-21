<?php

namespace GreatTransfer\GreatTransfer\Utilities;

final class StringUtil {

	public static function starts_with( string $text, string $starts_with, bool $case_sensitive = true ): bool {
		$len = strlen( $starts_with );
		if ( $len > strlen( $text ) ) {
			return false;
		}

		$text = substr( $text, 0, $len );

		if ( $case_sensitive ) {
			return strcmp( $text, $starts_with ) === 0;
		}

		return strcasecmp( $text, $starts_with ) === 0;
	}

	public static function ends_with( string $text, string $ends_with, bool $case_sensitive = true ): bool {
		$len = strlen( $ends_with );
		if ( $len > strlen( $text ) ) {
			return false;
		}

		$text = substr( $text, -$len );

		if ( $case_sensitive ) {
			return strcmp( $text, $ends_with ) === 0;
		}

		return strcasecmp( $text, $ends_with ) === 0;
	}

	public static function contains( string $text, string $contained, bool $case_sensitive = true ): bool {
		if ( $case_sensitive ) {
			return false !== strpos( $text, $contained );
		} else {
			return false !== stripos( $text, $contained );
		}
	}

	public static function plugin_name_from_plugin_file( string $plugin_file_path ): string {
		return basename( dirname( $plugin_file_path ) ) . DIRECTORY_SEPARATOR . basename( $plugin_file_path );
	}

	public static function is_null_or_empty( ?string $value ) {
		return is_null( $value ) || '' === $value;
	}

	public static function is_null_or_whitespace( ?string $value ) {
		return is_null( $value ) || '' === $value || ctype_space( $value );
	}

	public static function to_sql_list( array $values ) {
		if ( empty( $values ) ) {
			throw new \InvalidArgumentException( esc_html( self::class_name_without_namespace( __CLASS__ ) ) . '::' . __FUNCTION__ . ': the values array is empty' );
		}

		return '(' . implode( ',', $values ) . ')';
	}

	public static function class_name_without_namespace( string $class_name ) {
		$result = substr( strrchr( $class_name, '\\' ), 1 );
		return $result ? $result : $class_name;
	}

	public static function normalize_local_path_slashes( ?string $path ) {
		return is_null( $path ) ? null : str_replace( array( '\\', '/' ), DIRECTORY_SEPARATOR, $path );
	}
}
