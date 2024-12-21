<?php

interface GreatTransfer_Importer_Interface {

	public function import();

	public function get_raw_keys();

	public function get_mapped_keys();

	public function get_raw_data();

	public function get_parsed_data();

	public function get_file_position();

	public function get_percent_complete();
}
