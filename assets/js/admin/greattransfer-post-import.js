/* global ajaxurl, greattransferPostImportParams */

/**
 * postImportForm handles the import process.
 */
class PostImportForm {
	constructor( form ) {
		this.form = form;
		this.xhr = null;
		this.post_type = greattransferPostImportParams.post_type;
		this.mapping = greattransferPostImportParams.mapping;
		this.position = 0;
		this.file = greattransferPostImportParams.file;
		this.update_existing = greattransferPostImportParams.update_existing;
		this.delimiter = greattransferPostImportParams.delimiter;
		this.security = greattransferPostImportParams.import_nonce;
		this.character_encoding =
			greattransferPostImportParams.character_encoding;

		// Number of import successes/failures.
		this.imported = 0;
		this.imported_variations = 0;
		this.failed = 0;
		this.updated = 0;
		this.skipped = 0;

		// Initial state.
		this.form.querySelector( '.greattransfer-importer-progress' ).value = 0;

		// Start importing.
		this.run_import();
	}

	/**
	 * Run the import in batches until finished.
	 */
	async run_import() {
		try {
			const response = await fetch( ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( {
					action: 'greattransfer_do_ajax_post_import',
					post_type: this.post_type,
					position: this.position,
					mapping: this.mapping,
					file: this.file,
					update_existing: this.update_existing,
					delimiter: this.delimiter,
					security: this.security,
					character_encoding: this.character_encoding,
				} ),
			} );

			if ( ! response.ok ) {
				throw new Error( `HTTP error! status: ${ response.status }` );
			}

			const data = await response.json();

			if ( data.success ) {
				this.position = data.data.position;
				this.imported += data.data.imported;
				this.imported_variations += data.data.imported_variations;
				this.failed += data.data.failed;
				this.updated += data.data.updated;
				this.skipped += data.data.skipped;

				const progress = this.form.querySelector(
					'.greattransfer-importer-progress'
				);
				progress.value = data.data.percentage;

				if ( data.data.position === 'done' ) {
					const fileName = greattransferPostImportParams.file
						.split( '/' )
						.pop();
					window.location = `${ data.data.url }&posts-imported=${ this.imported }&posts-imported-variations=${ this.imported_variations }&posts-failed=${ this.failed }&posts-updated=${ this.updated }&posts-skipped=${ this.skipped }&file-name=${ fileName }`;
				} else {
					this.run_import();
				}
			}
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( error );
		}
	}
}

/**
 * Function to initialize PostImportForm on a selector.
 *
 * @param {string} selector - The CSS selector to find elements.
 */
function initializePostImporter( selector ) {
	const elements = document.querySelectorAll( selector );
	elements.forEach( ( element ) => {
		new PostImportForm( element );
	} );
}

// Initialize the importer on elements with the specified class.
initializePostImporter( '.greattransfer-importer' );
