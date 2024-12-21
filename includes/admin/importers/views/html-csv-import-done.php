<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="greattransfer-progress-form-content greattransfer-importer">
	<section class="greattransfer-importer-done">
		<?php
		$results = array();

		$translations = null;

		if ( 0 < $imported ) {
			if ( is_null( $translations ) ) {
				$translations = get_translations_for_domain( 'greattransfer' );
			}

			/* translators: %1$s: posts count, %2$s: Post/Taxonomy labels singular_name/name (plural) */
			$message = _nx(
				'%1$s %2$s imported',
				'%1$s %2$s imported',
				$imported,
				'male',
				'greattransfer'
			);
			if ( 'female' === $gender ) {
				/* translators: %1$s: posts count, %2$s: Post/Taxonomy labels singular_name/name (plural) */
				$message = _nx(
					'%1$s %2$s imported',
					'%1$s %2$s imported',
					$imported,
					'female',
					'greattransfer'
				);
			}

			$name_or_singular_name = $translations->translate_plural( $singular_name, $name, $imported );
			if ( ! str_starts_with( $message, '%$2s' ) ) {
				$name_or_singular_name = mb_strtolower( $name_or_singular_name );
			}

			$results[] = sprintf(
				$message,
				'<strong>' . number_format_i18n( $imported ) . '</strong>',
				$name_or_singular_name
			);
		}

		if ( 0 < $updated ) {
			if ( is_null( $translations ) ) {
				$translations = get_translations_for_domain( 'greattransfer' );
			}

			/* translators: %1$s: posts count, %2$s: Post/Taxonomy labels singular_name/name (plural) */
			$message = _nx(
				'%1$s %2$s updated',
				'%1$s %2$s updated',
				$updated,
				'male',
				'greattransfer'
			);
			if ( 'female' === $gender ) {
				/* translators: %1$s: posts count, %2$s: Post/Taxonomy labels singular_name/name (plural) */
				$message = _nx(
					'%1$s %2$s updated',
					'%1$s %2$s updated',
					$updated,
					'female',
					'greattransfer'
				);
			}

			$name_or_singular_name = $translations->translate_plural( $singular_name, $name, $updated );
			if ( ! str_starts_with( $message, '%$2s' ) ) {
				$name_or_singular_name = mb_strtolower( $name_or_singular_name );
			}

			$results[] = sprintf(
				$message,
				'<strong>' . number_format_i18n( $updated ) . '</strong>',
				$name_or_singular_name
			);
		}

		if ( 0 < $skipped ) {
			if ( is_null( $translations ) ) {
				$translations = get_translations_for_domain( 'greattransfer' );
			}

			/* translators: %1$s: posts count, %2$s: Post/Taxonomy labels singular_name/name (plural) */
			$message = _nx(
				'%1$s %2$s was skipped',
				'%1$s %2$s were skipped',
				$skipped,
				'male',
				'greattransfer'
			);
			if ( 'female' === $gender ) {
				/* translators: %1$s: posts count, %2$s: Post/Taxonomy labels singular_name/name (plural) */
				$message = _nx(
					'%1$s %2$s was skipped',
					'%1$s %2$s were skipped',
					$skipped,
					'female',
					'greattransfer'
				);
			}

			$name_or_singular_name = $translations->translate_plural( $singular_name, $name, $skipped );
			if ( ! str_starts_with( $message, '%$2s' ) ) {
				$name_or_singular_name = mb_strtolower( $name_or_singular_name );
			}

			$results[] = sprintf(
				$message,
				'<strong>' . number_format_i18n( $skipped ) . '</strong>',
				$name_or_singular_name
			);
		}

		if ( 0 < $failed ) {
			if ( is_null( $translations ) ) {
				$translations = get_translations_for_domain( 'greattransfer' );
			}

			/* translators: %1$s: posts count, %2$s: Post/Taxonomy labels singular_name/name (plural) */
			$message = __(
				'Failed to import %1$s %2$s',
				'greattransfer'
			);

			$name_or_singular_name = $translations->translate_plural( $singular_name, $name, $failed );
			if ( ! str_starts_with( $message, '%$2s' ) ) {
				$name_or_singular_name = mb_strtolower( $name_or_singular_name );
			}

			$results [] = sprintf(
				$message,
				'<strong>' . number_format_i18n( $failed ) . '</strong>',
				$name_or_singular_name
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
					<th><?php echo esc_html( $singular_name ); ?></th>
					<th><?php esc_html_e( 'Reason for failure', 'greattransfer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( is_array( $errors ) && count( $errors ) ) {
					foreach ( $errors as $error_item ) {
						if ( ! is_wp_error( $error_item ) ) {
							continue;
						}
						$error_data = $error_item->get_error_data();
						?>
						<tr>
							<th><code><?php echo esc_html( $error_data['row'] ); ?></code></th>
							<td><?php echo wp_kses_post( $error_item->get_error_message() ); ?></td>
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
		<a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $button ); ?></a>
	</div>
</div>
