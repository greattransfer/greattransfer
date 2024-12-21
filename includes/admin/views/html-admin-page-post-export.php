<?php

defined( 'ABSPATH' ) || exit;

wp_enqueue_script( 'greattransfer-post-export' );

$exporter = new GreatTransfer_Post_CSV_Exporter(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="wrap greattransfer">
	<h1><?php echo esc_html( $exporter->title() ); ?></h1>

	<div class="greattransfer-exporter-wrapper">
		<form class="greattransfer-exporter">
			<header>
				<span class="spinner is-active"></span>
				<h2><?php echo esc_html( $exporter->subtitle() ); ?></h2>
				<p><?php echo esc_html( $exporter->description() ); ?></p>
			</header>
			<section>
				<table class="form-table greattransfer-exporter-options">
					<tbody>
						<tr>
							<th scope="row">
								<label for="greattransfer-exporter-post-statuses"><?php esc_html_e( 'Which post status should be exported?', 'greattransfer' ); ?></label>
							</th>
							<td>
								<select id="greattransfer-exporter-post-statuses" class="greattransfer-exporter-post-statuses greattransfer-enhanced-select" style="width:100%;" multiple data-placeholder="<?php esc_attr_e( 'Export all post status', 'greattransfer' ); ?>">
									<?php
									foreach ( $exporter->get_default_post_statuses() as $post_status => $post_status_name ) {
										echo '<option value="' . esc_attr( $post_status ) . '">' . esc_html( $post_status_name ) . '</option>';
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="greattransfer-exporter-columns"><?php esc_html_e( 'Which columns should be exported?', 'greattransfer' ); ?></label>
							</th>
							<td>
								<select id="greattransfer-exporter-columns" class="greattransfer-exporter-columns greattransfer-enhanced-select" style="width:100%;" multiple data-placeholder="<?php esc_attr_e( 'Export all columns', 'greattransfer' ); ?>">
									<?php
									foreach ( $exporter->get_default_column_names() as $column_id => $column_name ) {
										echo '<option value="' . esc_attr( $column_id ) . '">' . esc_html( $column_name ) . '</option>';
									}
									?>
								</select>
							</td>
						</tr>

						<?php
						$taxonomies = array_reverse( $exporter->taxonomies() );
						foreach ( $taxonomies as $taxonomy => $labels ) :
							$id = 'greattransfer-exporter-taxonomy-' . $taxonomy;

							$classes = implode(
								' ',
								array(
									'greattransfer-exporter-taxonomy',
									$id,
									'greattransfer-enhanced-select',
								)
							);

							$all_male   = _x( 'All', 'male', 'greattransfer' );
							$all_female = _x( 'All', 'female', 'greattransfer' );

							$gender = 'neutral';
							if ( $all_male !== $all_female ) {
								if ( str_starts_with( $labels['all_items'], $all_male ) ) {
									$gender = 'male';
								} elseif ( str_starts_with( $labels['all_items'], $all_male ) ) {
									$gender = 'female';
								}
							}

							/* translators: Taxonomy labels singular_name */
							$label = __( 'Which %s should be exported?', 'greattransfer' );
							if ( 'male' === $gender ) {
								/* translators: Taxonomy labels singular_name */
								$label = _x( 'Which %s should be exported?', 'male', 'greattransfer' );
							} elseif ( 'female' === $gender ) {
								/* translators: Taxonomy labels singular_name */
								$label = __x( 'Which %s should be exported?', 'female', 'greattransfer' );
							}

							$singular_name = $labels['singular_name'];
							if ( ! str_starts_with( $label, '%s' ) ) {
								$singular_name = mb_strtolower( $singular_name );
							}

							$label = sprintf( $label, $singular_name );

							/* translators: Post/Taxonomy labels all_items */
							$placeholder = _x( 'Export %s', 'all_items', 'greattransfer' );

							$all_items = $labels['all_items'];
							if ( ! str_starts_with( $label, '%s' ) ) {
								$all_items = mb_strtolower( $all_items );
							}

							$placeholder = sprintf( $placeholder, $all_items );
							?>

							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $id ); ?>">
										<?php echo esc_html( $label ); ?>
									</label>
								</th>
								<td>
									<select
										id="<?php echo esc_attr( $id ); ?>"
										class="<?php echo esc_attr( $classes ); ?>"
										style="width:100%;"
										multiple
										data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
										data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
									<?php
									$terms = get_categories(
										array(
											'taxonomy'   => $taxonomy,
											'hide_empty' => false,
										)
									);
									foreach ( $terms as $term ) {
										echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
									}
									?>
									</select>
								</td>
							</tr>

						<?php endforeach; ?>

						<tr>
							<th scope="row">
								<label for="greattransfer-exporter-meta"><?php esc_html_e( 'Export custom meta?', 'greattransfer' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="greattransfer-exporter-meta" value="1" />
								<label for="greattransfer-exporter-meta"><?php esc_html_e( 'Yes, export all custom meta', 'greattransfer' ); ?></label>
							</td>
						</tr>
						<?php do_action( 'greattransfer_product_export_row' ); ?>
					</tbody>
				</table>
				<progress class="greattransfer-exporter-progress" max="100" value="0"></progress>
			</section>
			<div class="greattransfer-actions">
				<button type="submit" class="greattransfer-exporter-button button button-primary" value="<?php esc_attr_e( 'Generate CSV', 'greattransfer' ); ?>"><?php esc_html_e( 'Generate CSV', 'greattransfer' ); ?></button>
			</div>
		</form>
	</div>
</div>
