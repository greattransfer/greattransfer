/* global jQuery, greattransferEnhancedSelectParams */
jQuery( function ( $ ) {
	function getEnhancedSelectFormatString() {
		return {
			language: {
				errorLoading() {
					// Workaround for https://github.com/select2/select2/issues/4355 instead of i18n_ajax_error.
					return greattransferEnhancedSelectParams.i18n_searching;
				},
				inputTooLong( args ) {
					const overChars = args.input.length - args.maximum;

					if ( 1 === overChars ) {
						return greattransferEnhancedSelectParams.i18n_input_too_long_1;
					}

					return greattransferEnhancedSelectParams.i18n_input_too_long_n.replace(
						'%qty%',
						overChars
					);
				},
				inputTooShort( args ) {
					const remainingChars = args.minimum - args.input.length;

					if ( 1 === remainingChars ) {
						return greattransferEnhancedSelectParams.i18n_input_too_short_1;
					}

					return greattransferEnhancedSelectParams.i18n_input_too_short_n.replace(
						'%qty%',
						remainingChars
					);
				},
				loadingMore() {
					return greattransferEnhancedSelectParams.i18n_load_more;
				},
				maximumSelected( args ) {
					if ( args.maximum === 1 ) {
						return greattransferEnhancedSelectParams.i18n_selection_too_long_1;
					}

					return greattransferEnhancedSelectParams.i18n_selection_too_long_n.replace(
						'%qty%',
						args.maximum
					);
				},
				noResults() {
					return greattransferEnhancedSelectParams.i18n_no_matches;
				},
				searching() {
					return greattransferEnhancedSelectParams.i18n_searching;
				},
			},
		};
	}

	try {
		$( document.body )
			.on( 'greattransfer-enhanced-select-init', function () {
				// Regular select boxes
				$(
					':input.greattransfer-enhanced-select, :input.chosen_select'
				)
					.filter( ':not(.enhanced)' )
					.each( function () {
						const select2Args = $.extend(
							{
								minimumResultsForSearch: 10,
								allowClear: $( this ).data( 'allow_clear' )
									? true
									: false,
								placeholder: $( this ).data( 'placeholder' ),
							},
							getEnhancedSelectFormatString()
						);

						$( this )
							.selectWoo( select2Args )
							.addClass( 'enhanced' );
					} );

				$(
					':input.greattransfer-enhanced-select-nostd, :input.chosen_select_nostd'
				)
					.filter( ':not(.enhanced)' )
					.each( function () {
						const select2Args = $.extend(
							{
								minimumResultsForSearch: 10,
								allowClear: true,
								placeholder: $( this ).data( 'placeholder' ),
							},
							getEnhancedSelectFormatString()
						);

						$( this )
							.selectWoo( select2Args )
							.addClass( 'enhanced' );
					} );

				function displayResult( self, select2Args ) {
					select2Args = $.extend(
						select2Args,
						getEnhancedSelectFormatString()
					);

					$( self ).selectWoo( select2Args ).addClass( 'enhanced' );

					if ( $( self ).data( 'sortable' ) ) {
						const $select = $( self );
						const $list = $( self )
							.next( '.select2-container' )
							.find( 'ul.select2-selection__rendered' );

						$list.sortable( {
							placeholder:
								'ui-state-highlight select2-selection__choice',
							forcePlaceholderSize: true,
							items: 'li:not(.select2-search__field)',
							tolerance: 'pointer',
							stop() {
								$(
									$list
										.find( '.select2-selection__choice' )
										.get()
										.reverse()
								).each( function () {
									const id = $( this ).data( 'data' ).id;
									const option = $select.find(
										'option[value="' + id + '"]'
									)[ 0 ];
									$select.prepend( option );
								} );
							},
						} );
						// Keep multiselects ordered alphabetically if they are not sortable.
					} else if ( $( self ).prop( 'multiple' ) ) {
						$( self ).on( 'change', function () {
							const $children = $( self ).children();
							$children.sort( function ( a, b ) {
								const atext = a.text.toLowerCase();
								const btext = b.text.toLowerCase();

								if ( atext > btext ) {
									return 1;
								}
								if ( atext < btext ) {
									return -1;
								}
								return 0;
							} );
							$( self ).html( $children );
						} );
					}
				}

				// Ajax product search box
				$( ':input.greattransfer-product-search' )
					.filter( ':not(.enhanced)' )
					.each( function () {
						const select2Args = {
							allowClear: $( this ).data( 'allow_clear' )
								? true
								: false,
							placeholder: $( this ).data( 'placeholder' ),
							minimumInputLength: $( this ).data(
								'minimum_input_length'
							)
								? $( this ).data( 'minimum_input_length' )
								: '3',
							escapeMarkup( m ) {
								return m;
							},
							ajax: {
								url: greattransferEnhancedSelectParams.ajax_url,
								dataType: 'json',
								delay: 250,
								data( params ) {
									return {
										term: params.term,
										action:
											$( this ).data( 'action' ) ||
											'greattransfer_json_search_products_and_variations',
										security:
											greattransferEnhancedSelectParams.search_products_nonce,
										exclude: $( this ).data( 'exclude' ),
										exclude_type:
											$( this ).data( 'exclude_type' ),
										include: $( this ).data( 'include' ),
										limit: $( this ).data( 'limit' ),
										display_stock:
											$( this ).data( 'display_stock' ),
									};
								},
								processResults( data ) {
									const terms = [];
									if ( data ) {
										$.each( data, function ( id, text ) {
											terms.push( {
												id,
												text,
											} );
										} );
									}
									return {
										results: terms,
									};
								},
								cache: true,
							},
						};

						displayResult( this, select2Args );
					} );

				// Ajax Page Search.
				$( ':input.greattransfer-page-search' )
					.filter( ':not(.enhanced)' )
					.each( function () {
						const select2Args = {
							allowClear: $( this ).data( 'allow_clear' )
								? true
								: false,
							placeholder: $( this ).data( 'placeholder' ),
							minimumInputLength: $( this ).data(
								'minimum_input_length'
							)
								? $( this ).data( 'minimum_input_length' )
								: '3',
							escapeMarkup( m ) {
								return m;
							},
							ajax: {
								url: greattransferEnhancedSelectParams.ajax_url,
								dataType: 'json',
								delay: 250,
								data( params ) {
									return {
										term: params.term,
										action:
											$( this ).data( 'action' ) ||
											'greattransfer_json_search_pages',
										security:
											greattransferEnhancedSelectParams.search_pages_nonce,
										exclude: $( this ).data( 'exclude' ),
										post_status:
											$( this ).data( 'post_status' ),
										limit: $( this ).data( 'limit' ),
									};
								},
								processResults( data ) {
									const terms = [];
									if ( data ) {
										$.each( data, function ( id, text ) {
											terms.push( {
												id,
												text,
											} );
										} );
									}
									return {
										results: terms,
									};
								},
								cache: true,
							},
						};

						$( this )
							.selectWoo( select2Args )
							.addClass( 'enhanced' );
					} );

				// Ajax customer search boxes
				$( ':input.greattransfer-customer-search' )
					.filter( ':not(.enhanced)' )
					.each( function () {
						let select2Args = {
							allowClear: $( this ).data( 'allow_clear' )
								? true
								: false,
							placeholder: $( this ).data( 'placeholder' ),
							minimumInputLength: $( this ).data(
								'minimum_input_length'
							)
								? $( this ).data( 'minimum_input_length' )
								: '1',
							escapeMarkup( m ) {
								return m;
							},
							ajax: {
								url: greattransferEnhancedSelectParams.ajax_url,
								dataType: 'json',
								delay: 1000,
								data( params ) {
									return {
										term: params.term,
										action: 'greattransfer_json_search_customers',
										security:
											greattransferEnhancedSelectParams.search_customers_nonce,
										exclude: $( this ).data( 'exclude' ),
									};
								},
								processResults( data ) {
									const terms = [];
									if ( data ) {
										$.each( data, function ( id, text ) {
											terms.push( {
												id,
												text,
											} );
										} );
									}
									return {
										results: terms,
									};
								},
								cache: true,
							},
						};

						select2Args = $.extend(
							select2Args,
							getEnhancedSelectFormatString()
						);

						$( this )
							.selectWoo( select2Args )
							.addClass( 'enhanced' );

						if ( $( this ).data( 'sortable' ) ) {
							const $select = $( this );
							const $list = $( this )
								.next( '.select2-container' )
								.find( 'ul.select2-selection__rendered' );

							$list.sortable( {
								placeholder:
									'ui-state-highlight select2-selection__choice',
								forcePlaceholderSize: true,
								items: 'li:not(.select2-search__field)',
								tolerance: 'pointer',
								stop() {
									$(
										$list
											.find(
												'.select2-selection__choice'
											)
											.get()
											.reverse()
									).each( function () {
										const id = $( this ).data( 'data' ).id;
										const option = $select.find(
											'option[value="' + id + '"]'
										)[ 0 ];
										$select.prepend( option );
									} );
								},
							} );
						}
					} );

				// Ajax category search boxes
				$( ':input.greattransfer-category-search' )
					.filter( ':not(.enhanced)' )
					.each( function () {
						const returnFormat = $( this ).data( 'return_id' )
							? 'id'
							: 'slug';

						const select2Args = $.extend(
							{
								allowClear: $( this ).data( 'allow_clear' )
									? true
									: false,
								placeholder: $( this ).data( 'placeholder' ),
								minimumInputLength: $( this ).data(
									'minimum_input_length'
								)
									? $( this ).data( 'minimum_input_length' )
									: '3',
								escapeMarkup( m ) {
									return m;
								},
								ajax: {
									url: greattransferEnhancedSelectParams.ajax_url,
									dataType: 'json',
									delay: 250,
									data( params ) {
										return {
											term: params.term,
											action: 'greattransfer_json_search_categories',
											security:
												greattransferEnhancedSelectParams.search_categories_nonce,
										};
									},
									processResults( data ) {
										const terms = [];
										if ( data ) {
											$.each(
												data,
												function ( id, term ) {
													terms.push( {
														id:
															'id' ===
															returnFormat
																? term.term_id
																: term.slug,
														text: term.formatted_name,
													} );
												}
											);
										}
										return {
											results: terms,
										};
									},
									cache: true,
								},
							},
							getEnhancedSelectFormatString()
						);

						$( this )
							.selectWoo( select2Args )
							.addClass( 'enhanced' );
					} );

				// Ajax category search boxes
				$( ':input.greattransfer-taxonomy-term-search' )
					.filter( ':not(.enhanced)' )
					.each( function () {
						const returnFormat = $( this ).data( 'return_id' )
							? 'id'
							: 'slug';

						const select2Args = $.extend(
							{
								allowClear: $( this ).data( 'allow_clear' )
									? true
									: false,
								placeholder: $( this ).data( 'placeholder' ),
								minimumInputLength:
									$( this ).data( 'minimum_input_length' ) !==
										null &&
									$( this ).data( 'minimum_input_length' ) !==
										undefined
										? $( this ).data(
												'minimum_input_length'
										  )
										: '3',
								escapeMarkup( m ) {
									return m;
								},
								ajax: {
									url: greattransferEnhancedSelectParams.ajax_url,
									dataType: 'json',
									delay: 250,
									data( params ) {
										return {
											taxonomy:
												$( this ).data( 'taxonomy' ),
											limit: $( this ).data( 'limit' ),
											orderby:
												$( this ).data( 'orderby' ),
											term: params.term,
											action: 'greattransfer_json_search_taxonomy_terms',
											security:
												greattransferEnhancedSelectParams.search_taxonomy_terms_nonce,
										};
									},
									processResults( data ) {
										const terms = [];
										if ( data ) {
											$.each(
												data,
												function ( id, term ) {
													terms.push( {
														id:
															'id' ===
															returnFormat
																? term.term_id
																: term.slug,
														text: term.name,
													} );
												}
											);
										}
										return {
											results: terms,
										};
									},
									cache: true,
								},
							},
							getEnhancedSelectFormatString()
						);

						$( this )
							.selectWoo( select2Args )
							.addClass( 'enhanced' );
					} );

				$( ':input.greattransfer-attribute-search' )
					.filter( ':not(.enhanced)' )
					.each( function () {
						const select2Element = this;
						const select2Args = $.extend(
							{
								allowClear: $( this ).data( 'allow_clear' )
									? true
									: false,
								placeholder: $( this ).data( 'placeholder' ),
								minimumInputLength:
									$( this ).data( 'minimum_input_length' ) !==
										null &&
									$( this ).data( 'minimum_input_length' ) !==
										undefined
										? $( this ).data(
												'minimum_input_length'
										  )
										: '3',
								escapeMarkup( m ) {
									return m;
								},
								ajax: {
									url: greattransferEnhancedSelectParams.ajax_url,
									dataType: 'json',
									delay: 250,
									data( params ) {
										return {
											term: params.term,
											action: 'greattransfer_json_search_product_attributes',
											security:
												greattransferEnhancedSelectParams.search_product_attributes_nonce,
										};
									},
									processResults( data ) {
										const disabledItems =
											$( select2Element ).data(
												'disabled-items'
											) || [];
										const terms = [];
										if ( data ) {
											$.each(
												data,
												function ( id, term ) {
													terms.push( {
														id: term.slug,
														text: term.name,
														disabled:
															disabledItems.includes(
																term.slug
															),
													} );
												}
											);
										}
										return {
											results: terms,
										};
									},
									cache: true,
								},
							},
							getEnhancedSelectFormatString()
						);

						$( this )
							.selectWoo( select2Args )
							.addClass( 'enhanced' );
					} );
			} )

			// WooCommerce Backbone Modal
			.on( 'greattransfer_backbone_modal_before_remove', function () {
				$(
					'.greattransfer-enhanced-select, :input.greattransfer-product-search, :input.greattransfer-customer-search'
				)
					.filter( '.select2-hidden-accessible' )
					.selectWoo( 'close' );
			} )

			.trigger( 'greattransfer-enhanced-select-init' );

		$( 'html' ).on( 'click', function ( event ) {
			if ( this === event.target ) {
				$(
					'.greattransfer-enhanced-select, :input.greattransfer-product-search, :input.greattransfer-customer-search'
				)
					.filter( '.select2-hidden-accessible' )
					.selectWoo( 'close' );
			}
		} );
	} catch ( err ) {
		// If select2 failed (conflict?) log the error but don't stop other scripts breaking.
		window.console.log( err );
	}
} );
