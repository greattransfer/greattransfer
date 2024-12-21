<?php

defined( 'ABSPATH' ) || exit;

abstract class GreatTransfer_CSV_Exporter {

	protected $filename = 'greattransfer-export.csv';

	protected $limit = 50;

	protected $exported_row_count = 0;

	protected $row_data = array();

	protected $total_rows = 0;

	protected $column_names = array();

	protected $columns_to_export = array();

	protected $delimiter = ',';

	public function get_column_names() {
		return apply_filters( "greattransfer_{$this->export_type}_export_column_names", $this->column_names, $this );
	}

	public function set_column_names( $column_names ) {
		$this->column_names = array();

		foreach ( $column_names as $column_id => $column_name ) {
			$this->column_names[ greattransfer_clean( $column_id ) ] = greattransfer_clean( $column_name );
		}
	}

	public function get_columns_to_export() {
		return $this->columns_to_export;
	}

	public function get_delimiter() {
		return apply_filters( "greattransfer_{$this->export_type}_export_delimiter", $this->delimiter );
	}

	public function set_columns_to_export( $columns ) {
		$this->columns_to_export = array_map( 'greattransfer_clean', $columns );
	}

	public function is_column_exporting( $column_id ) {
		$default_columns = array_keys( $this->get_default_column_names() );

		if ( strstr( $column_id, ':' ) && ! in_array( $column_id, $default_columns, true ) ) {
			$column_id = current( explode( ':', $column_id ) );
		}

		$columns_to_export = $this->get_columns_to_export();

		if ( empty( $columns_to_export ) ) {
			return true;
		}

		if ( in_array( $column_id, $columns_to_export, true ) || 'meta' === $column_id ) {
			return true;
		}

		return false;
	}

	public function set_filename( $filename ) {
		$this->filename = sanitize_file_name( str_replace( '.csv', '', $filename ) . '.csv' );
	}

	public function get_filename() {
		return sanitize_file_name( apply_filters( "greattransfer_{$this->export_type}_export_get_filename", $this->filename ) );
	}

	protected function get_csv_data() {
		return $this->export_rows();
	}

	protected function get_data_to_export() {
		return $this->row_data;
	}

	protected function export_column_headers() {
		$columns    = $this->get_column_names();
		$export_row = array();
		$buffer     = fopen( 'php://output', 'w' );
		ob_start();

		$columns_to_export = $this->get_columns_to_export();

		foreach ( $columns as $column_id => $column_name ) {
			if ( $columns_to_export && ! in_array( $column_id, $columns_to_export, true ) ) {
				continue;
			}
			if ( ! $this->is_column_exporting( $column_id ) ) {
				continue;
			}
			$export_row[] = $this->format_data( $column_name );
		}

		$this->fputcsv( $buffer, $export_row );

		return ob_get_clean();
	}

	protected function export_rows() {
		$data   = $this->get_data_to_export();
		$buffer = fopen( 'php://output', 'w' );
		ob_start();

		array_walk( $data, array( $this, 'export_row' ), $buffer );

		return apply_filters( "greattransfer_{$this->export_type}_export_rows", ob_get_clean(), $this );
	}

	protected function export_row( $row_data, $key, $buffer ) {
		$columns    = $this->get_column_names();
		$export_row = array();

		foreach ( $columns as $column_id => $column_name ) {
			if ( ! $this->is_column_exporting( $column_id ) ) {
				continue;
			}
			if ( isset( $row_data[ $column_id ] ) ) {
				$export_row[] = $this->format_data( $row_data[ $column_id ] );
			} else {
				$export_row[] = '';
			}
		}

		$this->fputcsv( $buffer, $export_row );

		++$this->exported_row_count;
	}

	public function get_limit() {
		return apply_filters( "greattransfer_{$this->export_type}_export_batch_limit", $this->limit, $this );
	}

	public function get_total_exported() {
		return $this->exported_row_count;
	}

	public function escape_data( $data ) {
		$active_content_triggers = array( '=', '+', '-', '@', chr( 0x09 ), chr( 0x0d ) );

		if ( in_array( mb_substr( $data, 0, 1 ), $active_content_triggers, true ) ) {
			$data = "'" . $data;
		}

		return $data;
	}

	public function format_data( $data ) {
		if ( ! is_scalar( $data ) ) {
			$data = '';
		} elseif ( is_bool( $data ) ) {
			$data = $data ? 1 : 0;
		}

		$use_mb = function_exists( 'mb_convert_encoding' );

		if ( $use_mb ) {
			$is_valid_utf_8 = mb_check_encoding( $data, 'UTF-8' );
			if ( ! $is_valid_utf_8 ) {
				$data = mb_convert_encoding( $data, 'UTF-8', 'ISO-8859-1' );
			}
		}

		return $this->escape_data( $data );
	}

	public function format_term_ids( $term_ids, $taxonomy ) {
		$term_ids = wp_parse_id_list( $term_ids );

		if ( ! count( $term_ids ) ) {
			return '';
		}

		$formatted_terms = array();

		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			foreach ( $term_ids as $term_id ) {
				$formatted_term = array();
				$ancestor_ids   = array_reverse( get_ancestors( $term_id, $taxonomy ) );

				foreach ( $ancestor_ids as $ancestor_id ) {
					$term = get_term( $ancestor_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$formatted_term[] = $term->name;
					}
				}

				$term = get_term( $term_id, $taxonomy );

				if ( $term && ! is_wp_error( $term ) ) {
					$formatted_term[] = $term->name;
				}

				$formatted_terms[] = implode( ' > ', $formatted_term );
			}
		} else {
			foreach ( $term_ids as $term_id ) {
				$term = get_term( $term_id, $taxonomy );

				if ( $term && ! is_wp_error( $term ) ) {
					$formatted_terms[] = $term->name;
				}
			}
		}

		return $this->implode_values( $formatted_terms );
	}

	protected function implode_values( $values ) {
		$values_to_implode = array();

		foreach ( $values as $value ) {
			$value               = (string) is_scalar( $value ) ? html_entity_decode( $value, ENT_QUOTES ) : '';
			$values_to_implode[] = str_replace( ',', '\\,', $value );
		}

		return implode( ', ', $values_to_implode );
	}

	protected function fputcsv( $buffer, $export_row ) {
		fputcsv( $buffer, $export_row, $this->get_delimiter(), '"', "\0" );
	}
}
