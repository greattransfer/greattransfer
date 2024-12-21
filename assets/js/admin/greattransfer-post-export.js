/* global ajaxurl, greattransferPostExportParams */
document.addEventListener( 'DOMContentLoaded', function () {
	/**
	 * Class to handle the export process.
	 */
	class PostExportForm {
		/**
		 * Constructor for PostExportForm.
		 *
		 * @param {HTMLFormElement} form - The export form element.
		 */
		constructor( form ) {
			this.form = form;

			// Initial state
			const progressElement = this.form.querySelector(
				'.greattransfer-exporter-progress'
			);
			if ( progressElement ) {
				progressElement.value = 0;
			}

			// Bind methods
			this.processStep = this.processStep.bind( this );
			this.onSubmit = this.onSubmit.bind( this );
			this.exportTypeFields = this.exportTypeFields.bind( this );

			// Attach events
			this.form.addEventListener( 'submit', this.onSubmit );
			const exportTypes = this.form.querySelector(
				'.greattransfer-exporter-types'
			);
			if ( exportTypes ) {
				exportTypes.addEventListener( 'change', this.exportTypeFields );
			}
		}

		/**
		 * Handle export form submission.
		 *
		 * @param {Event} event - The form submission event.
		 */
		onSubmit( event ) {
			event.preventDefault();

			const currentDate = new Date();
			const postType = greattransferPostExportParams.post_type;
			const day = currentDate.getDate();
			const month = currentDate.getMonth() + 1;
			const year = currentDate.getFullYear();
			const date = `${ year }-${ month }-${ day }`;
			const hours = currentDate.getHours().toString().padStart( 2, '0' );
			const minutes = currentDate
				.getMinutes()
				.toString()
				.padStart( 2, '0' );
			const seconds = currentDate
				.getSeconds()
				.toString()
				.padStart( 2, '0' );
			const time = `${ hours }-${ minutes }-${ seconds }`;
			const filename = `greattransfer-${ postType }-export-${ date }-${ time }.csv`;

			this.form.classList.add( 'greattransfer-exporter__exporting' );
			this.form.querySelector(
				'.greattransfer-exporter-progress'
			).value = 0;
			this.form.querySelector(
				'.greattransfer-exporter-button'
			).disabled = true;

			const formData = new FormData( this.form );
			this.processStep( 1, formData, '', filename );
		}

		/**
		 * Process the current export step.
		 *
		 * @param {number}   step     - The current step number.
		 * @param {FormData} formData - The form data being submitted.
		 * @param {string}   columns  - The columns data for export.
		 * @param {string}   filename - The filename for the export file.
		 */
		processStep( step, formData, columns, filename ) {
			const selectedPostStatusesSelect = this.form.querySelector(
					'.greattransfer-exporter-post-statuses'
				),
				selectedPostStatuses = Array.from(
					selectedPostStatusesSelect.selectedOptions
				).flatMap( ( option ) => option.value.split( ',' ) ),
				selectedColumnsSelect = this.form.querySelector(
					'.greattransfer-exporter-columns'
				),
				selectedColumns = Array.from(
					selectedColumnsSelect.selectedOptions
				).flatMap( ( option ) => option.value.split( ',' ) ),
				exportMeta =
					null !==
					this.form.querySelector(
						'#greattransfer-exporter-meta:checked'
					)
						? 1
						: 0,
				exportTaxonomiesSelects = this.form.querySelectorAll(
					'.greattransfer-exporter-taxonomy'
				),
				exportTaxonomies = {};

			exportTaxonomiesSelects.forEach( ( select ) => {
				const taxonomy = select.getAttribute( 'data-taxonomy' );
				const selectedValues = Array.from(
					select.selectedOptions
				).flatMap( ( option ) => option.value.split( ',' ) );
				exportTaxonomies[ taxonomy ] = selectedValues;
			} );

			formData.append( 'action', 'greattransfer_do_ajax_post_export' );
			formData.append(
				'post_type',
				greattransferPostExportParams.post_type
			);
			formData.append( 'step', step );
			selectedPostStatuses.forEach( ( column ) => {
				formData.append( 'selected_post_statuses[]', column );
			} );
			Object.entries( columns ).forEach( ( [ key, value ] ) => {
				formData.append( `columns[${ key }]`, value );
			} );
			selectedColumns.forEach( ( column ) => {
				formData.append( 'selected_columns[]', column );
			} );
			formData.append( 'export_meta', exportMeta );
			Object.entries( exportTaxonomies ).forEach( ( [ key, values ] ) => {
				values.forEach( ( value ) => {
					formData.append( `export_taxonomies[${ key }][]`, value );
				} );
			} );
			formData.append( 'filename', filename );
			formData.append(
				'security',
				greattransferPostExportParams.export_nonce
			);

			fetch( ajaxurl, {
				method: 'POST',
				body: formData,
			} )
				.then( ( response ) => response.json() )
				.then( ( data ) => {
					if ( data.success ) {
						const progressElement = this.form.querySelector(
							'.greattransfer-exporter-progress'
						);
						progressElement.value = data.data.percentage;

						if ( data.data.step === 'done' ) {
							window.location = data.data.url;
							setTimeout( () => {
								this.resetFormState();
							}, 2000 );
						} else {
							this.processStep(
								parseInt( data.data.step, 10 ),
								formData,
								data.data.columns,
								filename
							);
						}
					}
				} )
				.catch( ( error ) => {
					// eslint-disable-next-line no-console
					console.error( 'Export failed:', error );
					this.resetFormState();
				} );
		}

		/**
		 * Reset form to its initial state.
		 */
		resetFormState() {
			this.form.classList.remove( 'greattransfer-exporter__exporting' );
			this.form.querySelector(
				'.greattransfer-exporter-button'
			).disabled = false;
		}

		/**
		 * Handle fields per export type.
		 *
		 * @param {Event} event - The change event on the export type field.
		 */
		exportTypeFields( event ) {
			const exportCategory = this.form.querySelector(
				'.greattransfer-exporter-category'
			);
			if ( event.target.value.includes( 'variation' ) ) {
				exportCategory.closest( 'tr' ).style.display = 'none';
				exportCategory.value = '';
				exportCategory.dispatchEvent( new Event( 'change' ) );
			} else {
				exportCategory.closest( 'tr' ).style.display = '';
			}
		}
	}

	// Initialize PostExportForm for each matching form
	document
		.querySelectorAll( '.greattransfer-exporter' )
		.forEach( ( form ) => new PostExportForm( form ) );
} );
