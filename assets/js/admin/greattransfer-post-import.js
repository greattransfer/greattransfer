/*global ajaxurl, greattransfer_post_import_params */
( function ( $, window ) {
	/**
	 * postImportForm handles the import process.
	 */
	var postImportForm = function ( $form ) {
		this.$form = $form;
		this.xhr = false;
		this.post_type = greattransfer_post_import_params.post_type;
		this.mapping = greattransfer_post_import_params.mapping;
		this.position = 0;
		this.file = greattransfer_post_import_params.file;
		this.update_existing = greattransfer_post_import_params.update_existing;
		this.delimiter = greattransfer_post_import_params.delimiter;
		this.security = greattransfer_post_import_params.import_nonce;
		this.character_encoding = greattransfer_post_import_params.character_encoding;

		// Number of import successes/failures.
		this.imported = 0;
		this.imported_variations = 0;
		this.failed = 0;
		this.updated = 0;
		this.skipped = 0;

		// Initial state.
		this.$form.find( '.greattransfer-importer-progress' ).val( 0 );

		this.run_import = this.run_import.bind( this );

		// Start importing.
		this.run_import();
	};

	/**
	 * Run the import in batches until finished.
	 */
	postImportForm.prototype.run_import = function () {
		var $this = this;

		$.ajax( {
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'greattransfer_do_ajax_post_import',
				post_type: $this.post_type,
				position: $this.position,
				mapping: $this.mapping,
				file: $this.file,
				update_existing: $this.update_existing,
				delimiter: $this.delimiter,
				security: $this.security,
				character_encoding: $this.character_encoding,
			},
			dataType: 'json',
			success: function ( response ) {
				if ( response.success ) {
					$this.position = response.data.position;
					$this.imported += response.data.imported;
					$this.imported_variations +=
						response.data.imported_variations;
					$this.failed += response.data.failed;
					$this.updated += response.data.updated;
					$this.skipped += response.data.skipped;
					$this.$form
						.find( '.greattransfer-importer-progress' )
						.val( response.data.percentage );

					if ( 'done' === response.data.position ) {
						var file_name = greattransfer_post_import_params.file
							.split( '/' )
							.pop();
						window.location =
							response.data.url +
							'&posts-imported=' +
							parseInt( $this.imported, 10 ) +
							'&posts-imported-variations=' +
							parseInt( $this.imported_variations, 10 ) +
							'&posts-failed=' +
							parseInt( $this.failed, 10 ) +
							'&posts-updated=' +
							parseInt( $this.updated, 10 ) +
							'&posts-skipped=' +
							parseInt( $this.skipped, 10 ) +
							'&file-name=' +
							file_name;
					} else {
						$this.run_import();
					}
				}
			},
		} ).fail( function ( response ) {
			window.console.log( response );
		} );
	};

	/**
	 * Function to call postImportForm on jQuery selector.
	 */
	$.fn.greattransfer_post_importer = function () {
		new postImportForm( this );
		return this;
	};

	$( '.greattransfer-importer' ).greattransfer_post_importer();
} )( jQuery, window );
