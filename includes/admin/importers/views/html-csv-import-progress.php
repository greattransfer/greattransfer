<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="greattransfer-progress-form-content greattransfer-importer greattransfer-importer__importing">
	<header>
		<span class="spinner is-active"></span>
		<h2><?php esc_html_e( 'Importing', 'greattransfer' ); ?></h2>
		<p><?php echo esc_html( $description ); ?></p>
	</header>
	<section>
		<progress class="greattransfer-importer-progress" max="100" value="0"></progress>
	</section>
</div>
