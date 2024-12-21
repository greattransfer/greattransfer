<?php
defined( 'ABSPATH' ) || exit;
?>
<form class="greattransfer-progress-form-content greattransfer-importer" enctype="multipart/form-data" method="post">
	<header>
		<h2><?php echo esc_html( $subtitle ); ?></h2>
		<p><?php echo esc_html( $description ); ?></p>
	</header>
	<section>
		<table class="form-table greattransfer-importer-options">
			<tbody>
				<tr>
					<th scope="row">
						<label for="upload">
							<?php esc_html_e( 'Choose a CSV file from your computer:', 'greattransfer' ); ?>
						</label>
					</th>
					<td>
						<?php
						if ( ! empty( $upload_dir['error'] ) ) {
							?>
							<div class="inline error">
								<p><?php esc_html_e( 'Before you can upload your import file, you will need to fix the following error:', 'greattransfer' ); ?></p>
								<p><strong><?php echo esc_html( $upload_dir['error'] ); ?></strong></p>
							</div>
							<?php
						} else {
							?>
							<input type="file" id="upload" name="import" size="25" />
							<input type="hidden" name="action" value="save" />
							<input type="hidden" name="max_file_size" value="<?php echo esc_attr( $bytes ); ?>" />
							<br>
							<small>
								<?php
								printf(
									/* translators: %s: maximum upload size */
									esc_html__( 'Maximum size: %s', 'greattransfer' ),
									esc_html( $size )
								);
								?>
							</small>
							<?php
						}
						?>
					</td>
				</tr>
				<tr>
					<th><label for="greattransfer-importer-update-existing"><?php echo esc_html( $update_title ); ?></label><br/></th>
					<td>
						<input type="hidden" name="update_existing" value="0" />
						<input type="checkbox" id="greattransfer-importer-update-existing" name="update_existing" value="1" />
						<label for="greattransfer-importer-update-existing"><?php echo esc_html( $update_label ); ?></label>
					</td>
				</tr>
				<tr class="greattransfer-importer-advanced hidden">
					<th>
						<label for="greattransfer-importer-file-url"><?php esc_html_e( 'Alternatively, enter the path to a CSV file on your server:', 'greattransfer' ); ?></label>
					</th>
					<td>
						<label for="greattransfer-importer-file-url" class="greattransfer-importer-file-url-field-wrapper">
							<code><?php echo esc_html( ABSPATH ) . ' '; ?></code><input type="text" id="greattransfer-importer-file-url" name="file_url" />
						</label>
					</td>
				</tr>
				<tr class="greattransfer-importer-advanced hidden">
					<th><label><?php esc_html_e( 'CSV Delimiter', 'greattransfer' ); ?></label><br/></th>
					<td><input type="text" name="delimiter" placeholder="," size="2" /></td>
				</tr>
				<tr class="greattransfer-importer-advanced hidden">
					<th><label><?php esc_html_e( 'Use previous column mapping preferences?', 'greattransfer' ); ?></label><br/></th>
					<td><input type="checkbox" id="greattransfer-importer-map-preferences" name="map_preferences" value="1" /></td>
				</tr>
				<tr class="greattransfer-importer-advanced hidden">
					<th><label><?php esc_html_e( 'Character encoding of the file', 'greattransfer' ); ?></label><br/></th>
					<td><select id="greattransfer-importer-character-encoding" name="character_encoding">
							<option value="" selected><?php esc_html_e( 'Autodetect', 'greattransfer' ); ?></option>
							<?php
							$encodings = mb_list_encodings();
							sort( $encodings, SORT_NATURAL );
							foreach ( $encodings as $encoding ) {
								echo '<option>' . esc_html( $encoding ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>
	</section>
	<script type="text/javascript">
		jQuery(function() {
			jQuery( '.greattransfer-importer-toggle-advanced-options' ).on( 'click', function() {
				var elements = jQuery( '.greattransfer-importer-advanced' );
				if ( elements.is( '.hidden' ) ) {
					elements.removeClass( 'hidden' );
					jQuery( this ).text( jQuery( this ).data( 'hidetext' ) );
				} else {
					elements.addClass( 'hidden' );
					jQuery( this ).text( jQuery( this ).data( 'showtext' ) );
				}
				return false;
			} );
		});
	</script>
	<div class="greattransfer-actions">
		<a href="#" class="greattransfer-importer-toggle-advanced-options" data-hidetext="<?php esc_attr_e( 'Hide advanced options', 'greattransfer' ); ?>" data-showtext="<?php esc_attr_e( 'Show advanced options', 'greattransfer' ); ?>"><?php esc_html_e( 'Show advanced options', 'greattransfer' ); ?></a>
		<button type="submit" class="button button-primary button-next" value="<?php esc_attr_e( 'Continue', 'greattransfer' ); ?>" name="save_step"><?php esc_html_e( 'Continue', 'greattransfer' ); ?></button>
		<?php wp_nonce_field( 'greattransfer-csv-importer' ); ?>
	</div>
</form>
