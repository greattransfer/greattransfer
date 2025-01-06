<?php
declare(strict_types=1);

namespace GilbertoTavares\GreatTransfer;

use GilbertoTavares\GreatTransfer\Import;
use GilbertoTavares\GreatTransfer\Export;

class ImportExport {

	public function __construct( $plugin_file ) {
		new Import( $plugin_file );
		new Export( $plugin_file );
	}
}
