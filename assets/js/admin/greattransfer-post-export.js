/* global ajaxurl, greattransfer_post_export_params */
document.addEventListener( 'DOMContentLoaded', function () {
	/**
	 * Class to handle the export process.
	 */
	class PostExportForm {
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
		 */
		onSubmit( event ) {
			event.preventDefault();

			const currentDate = new Date();
			const postType = greattransfer_post_export_params.post_type;
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
		 */
		processStep( step, formData, columns, filename ) {
			const selected_post_statuses_select = this.form.querySelector(
					'.greattransfer-exporter-post-statuses'
				),
				selected_post_statuses = Array.from(
					selected_post_statuses_select.selectedOptions
				).flatMap( ( option ) => option.value.split( ',' ) ),
				selected_columns_select = this.form.querySelector(
					'.greattransfer-exporter-columns'
				),
				selected_columns = Array.from(
					selected_columns_select.selectedOptions
				).flatMap( ( option ) => option.value.split( ',' ) ),
				export_meta =
					null !==
					this.form.querySelector(
						'#greattransfer-exporter-meta:checked'
					)
						? 1
						: 0,
				export_taxonomies_selects = this.form.querySelectorAll(
					'.greattransfer-exporter-taxonomy'
				),
				export_taxonomies = {};

			export_taxonomies_selects.forEach( ( select ) => {
				const taxonomy = select.getAttribute( 'data-taxonomy' );
				const selectedValues = Array.from(
					select.selectedOptions
				).flatMap( ( option ) => option.value.split( ',' ) );
				export_taxonomies[ taxonomy ] = selectedValues;
			} );

			formData.append( 'action', 'greattransfer_do_ajax_post_export' );
			formData.append(
				'post_type',
				greattransfer_post_export_params.post_type
			);
			formData.append( 'step', step );
			selected_post_statuses.forEach( ( column ) => {
				formData.append( 'selected_post_statuses[]', column );
			} );
			Object.entries( columns ).forEach(
				( [ key, value ] ) => {
					formData.append(
						`columns[${ key }]`,
						value
					);
				}
			);
			selected_columns.forEach( ( column ) => {
				formData.append( 'selected_columns[]', column );
			} );
			formData.append( 'export_meta', export_meta );
			Object.entries( export_taxonomies ).forEach(
				( [ key, values ] ) => {
					values.forEach( ( value ) => {
						formData.append(
							`export_taxonomies[${ key }][]`,
							value
						);
					} );
				}
			);
			formData.append( 'filename', filename );
			formData.append(
				'security',
				greattransfer_post_export_params.export_nonce
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
