<?php

namespace GreatTransfer\GreatTransfer;

defined( 'ABSPATH' ) || exit;

class Autoloader {

	private function __construct() {}

	public static function init() {
		$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';

		if ( ! is_readable( $autoloader ) ) {
			self::missing_autoloader();
			return false;
		}

		$autoloader_result = require $autoloader;
		if ( ! $autoloader_result ) {
			return false;
		}

		return $autoloader_result;
	}

	protected static function missing_autoloader() {
		$command   = 'composer install';
		$directory = str_replace( ABSPATH, '', dirname( GREATTRANSFER_PLUGIN_FILE ) );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				esc_html(
					sprintf(
						'Your installation of the GreatTransfer plugin is incomplete. Please run %1$s within the %2$s directory.',
						"`$command`",
						"`$directory`"
					)
				)
			);
			// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		add_action(
			'admin_notices',
			function () use ( $command, $directory ) {
				?>
				<div class="notice notice-error">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: 1: composer command. 2: plugin directory */
								__(
									'Your installation of the GreatTransfer plugin is incomplete. Please run %1$s within the %2$s directory.',
									'greattransfer'
								),
								"<code>$command</code>",
								"<code>$directory</code>"
							)
						);
						?>
					</p>
				</div>
				<?php
			}
		);
	}
}
