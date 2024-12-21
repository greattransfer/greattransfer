/*global greattransfer_enhanced_select_params */
jQuery( function ( $ ) {
	function getEnhancedSelectFormatString() {
		return {
			language: {
				errorLoading: function () {
					// Workaround for https://github.com/select2/select2/issues/4355 instead of i18n_ajax_error.
					return greattransfer_enhanced_select_params.i18n_searching;
				},
				inputTooLong: function ( args ) {
					var overChars = args.input.length - args.maximum;

					if ( 1 === overChars ) {
						return greattransfer_enhanced_select_params.i18n_input_too_long_1;
					}

					return greattransfer_enhanced_select_params.i18n_input_too_long_n.replace(
						'%qty%',
						overChars
					);
				},
				inputTooShort: function ( args ) {
					var remainingChars = args.minimum - args.input.length;

					if ( 1 === remainingChars ) {
						return greattransfer_enhanced_select_params.i18n_input_too_short_1;
					}

					return greattransfer_enhanced_select_params.i18n_input_too_short_n.replace(
						'%qty%',
						remainingChars
					);
				},
				loadingMore: function () {
					return greattransfer_enhanced_select_params.i18n_load_more;
				},
				maximumSelected: function ( args ) {
					if ( args.maximum === 1 ) {
						return greattransfer_enhanced_select_params.i18n_selection_too_long_1;
					}

					return greattransfer_enhanced_select_params.i18n_selection_too_long_n.replace(
						'%qty%',
						args.maximum
					);
				},
				noResults: function () {
					return greattransfer_enhanced_select_params.i18n_no_matches;
				},
				searching: function () {
					return greattransfer_enhanced_select_params.i18n_searching;
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
						var select2_args = $.extend(
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
							.selectWoo( select2_args )
							.addClass( 'enhanced' );
					} );

				$(
					':input.greattransfer-enhanced-select-nostd, :input.chosen_select_nostd'
				)
					.filter( ':not(.enhanced)' )
					.each( function () {
						var select2_args = $.extend(
							{
								minimumResultsForSearch: 10,
								allowClear: true,
								placeholder: $( this ).data( 'placeholder' ),
							},
							getEnhancedSelectFormatString()
						);

						$( this )
							.selectWoo( select2_args )
							.addClass( 'enhanced' );
					} );

				function display_result( self, select2_args ) {
					select2_args = $.extend(
						select2_args,
						getEnhancedSelectFormatString()
					);

					$( self ).selectWoo( select2_args ).addClass( 'enhanced' );

					if ( $( self ).data( 'sortable' ) ) {
						var $select = $( self );
						var $list = $( self )
							.next( '.select2-container' )
							.find( 'ul.select2-selection__rendered' );

						$list.sortable( {
							placeholder:
								'ui-state-highlight select2-selection__choice',
							forcePlaceholderSize: true,
							items: 'li:not(.select2-search__field)',
							tolerance: 'pointer',
							stop: function () {
								$(
									$list
										.find( '.select2-selection__choice' )
										.get()
										.reverse()
								).each( function () {
									var id = $( this ).data( 'data' ).id;
									var option = $select.find(
										'option[value="' + id + '"]'
									)[ 0 ];
									$select.prepend( option );
								} );
							},
						} );
						// Keep multiselects ordered alphabetically if they are not sortable.
					} else if ( $( self ).prop( 'multiple' ) ) {
						$( self ).on( 'change', function () {
							var $children = $( self ).children();
							$children.sort( function ( a, b ) {
								var atext = a.text.toLowerCase();
								var btext = b.text.toLowerCase();

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
						var select2_args = {
							allowClear: $( this ).data( 'allow_clear' )
								? true
								: false,
							placeholder: $( this ).data( 'placeholder' ),
							minimumInputLength: $( this ).data(
								'minimum_input_length'
							)
								? $( this ).data( 'minimum_input_length' )
								: '3',
							escapeMarkup: function ( m ) {
								return m;
							},
							ajax: {
								url: greattransfer_enhanced_select_params.ajax_url,
								dataType: 'json',
								delay: 250,
								data: function ( params ) {
									return {
										term: params.term,
										action:
											$( this ).data( 'action' ) ||
											'greattransfer_json_search_products_and_variations',
										security:
											greattransfer_enhanced_select_params.search_products_nonce,
										exclude: $( this ).data( 'exclude' ),
										exclude_type:
											$( this ).data( 'exclude_type' ),
										include: $( this ).data( 'include' ),
										limit: $( this ).data( 'limit' ),
										display_stock:
											$( this ).data( 'display_stock' ),
									};
								},
								processResults: function ( data ) {
									var terms = [];
									if ( data ) {
										$.each( data, function ( id, text ) {
											terms.push( {
												id: id,
												text: text,
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

						display_result( this, select2_args );
					} );

				// Ajax Page Search.
				$( ':input.greattransfer-page-search' )
					.filter( ':not(.enhanced)' )
					.each( function () {
						var select2_args = {
							allowClear: $( this ).data( 'allow_clear' )
								? true
								: false,
							placeholder: $( this ).data( 'placeholder' ),
							minimumInputLength: $( this ).data(
								'minimum_input_length'
							)
								? $( this ).data( 'minimum_input_length' )
								: '3',
							escapeMarkup: function ( m ) {
								return m;
							},
							ajax: {
								url: greattransfer_enhanced_select_params.ajax_url,
								dataType: 'json',
								delay: 250,
								data: function ( params ) {
									return {
										term: params.term,
										action:
											$( this ).data( 'action' ) ||
											'greattransfer_json_search_pages',
										security:
											greattransfer_enhanced_select_params.search_pages_nonce,
										exclude: $( this ).data( 'exclude' ),
										post_status:
											$( this ).data( 'post_status' ),
										limit: $( this ).data( 'limit' ),
									};
								},
								processResults: function ( data ) {
									var terms = [];
									if ( data ) {
										$.each( data, function ( id, text ) {
											terms.push( {
												id: id,
												text: text,
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
							.selectWoo( select2_args )
							.addClass( 'enhanced' );
					} );

				// Ajax customer search boxes
				$( ':input.greattransfer-customer-search' )
					.filter( ':not(.enhanced)' )
					.each( function () {
						var select2_args = {
							allowClear: $( this ).data( 'allow_clear' )
								? true
								: false,
							placeholder: $( this ).data( 'placeholder' ),
							minimumInputLength: $( this ).data(
								'minimum_input_length'
							)
								? $( this ).data( 'minimum_input_length' )
								: '1',
							escapeMarkup: function ( m ) {
								return m;
							},
							ajax: {
								url: greattransfer_enhanced_select_params.ajax_url,
								dataType: 'json',
								delay: 1000,
								data: function ( params ) {
									return {
										term: params.term,
										action: 'greattransfer_json_search_customers',
										security:
											greattransfer_enhanced_select_params.search_customers_nonce,
										exclude: $( this ).data( 'exclude' ),
									};
								},
								processResults: function ( data ) {
									var terms = [];
									if ( data ) {
										$.each( data, function ( id, text ) {
											terms.push( {
												id: id,
												text: text,
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

						select2_args = $.extend(
							select2_args,
							getEnhancedSelectFormatString()
						);

						$( this )
							.selectWoo( select2_args )
							.addClass( 'enhanced' );

						if ( $( this ).data( 'sortable' ) ) {
							var $select = $( this );
							var $list = $( this )
								.next( '.select2-container' )
								.find( 'ul.select2-selection__rendered' );

							$list.sortable( {
								placeholder:
									'ui-state-highlight select2-selection__choice',
								forcePlaceholderSize: true,
								items: 'li:not(.select2-search__field)',
								tolerance: 'pointer',
								stop: function () {
									$(
										$list
											.find(
												'.select2-selection__choice'
											)
											.get()
											.reverse()
									).each( function () {
										var id = $( this ).data( 'data' ).id;
										var option = $select.find(
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
						var return_format = $( this ).data( 'return_id' )
							? 'id'
							: 'slug';

						var select2_args = $.extend(
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
								escapeMarkup: function ( m ) {
									return m;
								},
								ajax: {
									url: greattransfer_enhanced_select_params.ajax_url,
									dataType: 'json',
									delay: 250,
									data: function ( params ) {
										return {
											term: params.term,
											action: 'greattransfer_json_search_categories',
											security:
												greattransfer_enhanced_select_params.search_categories_nonce,
										};
									},
									processResults: function ( data ) {
										var terms = [];
										if ( data ) {
											$.each(
												data,
												function ( id, term ) {
													terms.push( {
														id:
															'id' ===
															return_format
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
							.selectWoo( select2_args )
							.addClass( 'enhanced' );
					} );

				// Ajax category search boxes
				$( ':input.greattransfer-taxonomy-term-search' )
					.filter( ':not(.enhanced)' )
					.each( function () {
						var return_format = $( this ).data( 'return_id' )
							? 'id'
							: 'slug';

						var select2_args = $.extend(
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
								escapeMarkup: function ( m ) {
									return m;
								},
								ajax: {
									url: greattransfer_enhanced_select_params.ajax_url,
									dataType: 'json',
									delay: 250,
									data: function ( params ) {
										return {
											taxonomy:
												$( this ).data( 'taxonomy' ),
											limit: $( this ).data( 'limit' ),
											orderby:
												$( this ).data( 'orderby' ),
											term: params.term,
											action: 'greattransfer_json_search_taxonomy_terms',
											security:
												greattransfer_enhanced_select_params.search_taxonomy_terms_nonce,
										};
									},
									processResults: function ( data ) {
										var terms = [];
										if ( data ) {
											$.each(
												data,
												function ( id, term ) {
													terms.push( {
														id:
															'id' ===
															return_format
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
							.selectWoo( select2_args )
							.addClass( 'enhanced' );
					} );

				$( ':input.greattransfer-attribute-search' )
					.filter( ':not(.enhanced)' )
					.each( function () {
						var select2Element = this;
						var select2_args = $.extend(
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
								escapeMarkup: function ( m ) {
									return m;
								},
								ajax: {
									url: greattransfer_enhanced_select_params.ajax_url,
									dataType: 'json',
									delay: 250,
									data: function ( params ) {
										return {
											term: params.term,
											action: 'greattransfer_json_search_product_attributes',
											security:
												greattransfer_enhanced_select_params.search_product_attributes_nonce,
										};
									},
									processResults: function ( data ) {
										var disabledItems =
											$( select2Element ).data(
												'disabled-items'
											) || [];
										var terms = [];
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
							.selectWoo( select2_args )
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
