<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="greattransfer-progress-form-content greattransfer-importer">
	<section class="greattransfer-importer-done">
		<?php
		$results = array();

		if ( 0 < $imported ) {
			$results[] = sprintf(
				/* translators: %d: products count */
				_n( '%s product imported', '%s products imported', $imported, 'greattransfer' ),
				'<strong>' . number_format_i18n( $imported ) . '</strong>'
			);
		}

		if ( 0 < $updated ) {
			$results[] = sprintf(
				/* translators: %d: products count */
				_n( '%s product updated', '%s products updated', $updated, 'greattransfer' ),
				'<strong>' . number_format_i18n( $updated ) . '</strong>'
			);
		}

		if ( 0 < $imported_variations ) {
			$results[] = sprintf(
				/* translators: %d: products count */
				_n( '%s variations imported', '%s variations imported', $imported_variations, 'greattransfer' ),
				'<strong>' . number_format_i18n( $imported_variations ) . '</strong>'
			);
		}

		if ( 0 < $skipped ) {
			$results[] = sprintf(
				/* translators: %d: products count */
				_n( '%s product was skipped', '%s products were skipped', $skipped, 'greattransfer' ),
				'<strong>' . number_format_i18n( $skipped ) . '</strong>'
			);
		}

		if ( 0 < $failed ) {
			$results [] = sprintf(
				/* translators: %d: products count */
				_n( 'Failed to import %s product', 'Failed to import %s products', $failed, 'greattransfer' ),
				'<strong>' . number_format_i18n( $failed ) . '</strong>'
			);
		}

		if ( 0 < $failed || 0 < $skipped ) {
			$results[] = '<a href="#" class="greattransfer-importer-done-view-errors">' . __( 'View import log', 'greattransfer' ) . '</a>';
		}

		if ( ! empty( $file_name ) ) {
			$results[] = sprintf(
				/* translators: %s: File name */
				__( 'File uploaded: %s', 'greattransfer' ),
				'<strong>' . $file_name . '</strong>'
			);
		}

		/* translators: %d: import results */
		echo wp_kses_post( __( 'Import complete!', 'greattransfer' ) . ' ' . implode( '. ', $results ) );
		?>
	</section>
	<section class="greattransfer-importer-error-log" style="display:none">
		<table class="widefat greattransfer-importer-error-log-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'greattransfer' ); ?></th>
					<th><?php esc_html_e( 'Reason for failure', 'greattransfer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( is_array( $errors ) && count( $errors ) ) {
					foreach ( $errors as $error ) {
						if ( ! is_wp_error( $error ) ) {
							continue;
						}
						$error_data = $error->get_error_data();
						?>
						<tr>
							<th><code><?php echo esc_html( $error_data['row'] ); ?></code></th>
							<td><?php echo wp_kses_post( $error->get_error_message() ); ?></td>
						</tr>
						<?php
					}
				}
				?>
			</tbody>
		</table>
	</section>
	<script type="text/javascript">
		jQuery(function() {
			jQuery( '.greattransfer-importer-done-view-errors' ).on( 'click', function() {
				jQuery( '.greattransfer-importer-error-log' ).slideToggle();
				return false;
			} );
		} );
	</script>
	<div class="greattransfer-actions">
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><?php esc_html_e( 'View products', 'greattransfer' ); ?></a>
	</div>
</div>
