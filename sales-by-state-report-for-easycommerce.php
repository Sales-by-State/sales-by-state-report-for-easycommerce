<?php
/**
 * Plugin Name:          Sales by State Report for EasyCommerce
 * Plugin URI:           https://salesbystate.com/
 * Description:          See a yearly breakdown of EasyCommerce sales by state / county / province for a given country, filterable by order status.
 * Version:              1.0.0
 * Author:               Rodolfo Melogli
 * Author URI:           https://www.businessbloomer.com/
 * Developer:            Rodolfo Melogli
 * Developer URI:        https://www.businessbloomer.com/
 * Text Domain:          sales-by-state-report-for-easycommerce
 * Domain Path:          /languages
 * Requires at least:    6.7
 * Requires PHP:         8.0
 * Requires Plugins:     easycommerce
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SalesByStateReportForEasyCommerce
 * @copyright 2026 Rodolfo Melogli
 */

defined( 'ABSPATH' ) || exit;

define( 'SBSECOM_VERSION', '1.0.0' );
define( 'SBSECOM_FILE', __FILE__ );
define( 'SBSECOM_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBSECOM_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'SBSECOM\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = SBSECOM_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'EASYCOMMERCE_VERSION' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Sales by State Report for EasyCommerce requires EasyCommerce to be installed and active.', 'sales-by-state-report-for-easycommerce' )
					);
				}
			);

			return;
		}

		SBSECOM\Plugin::instance()->init();
	},
	20
);

register_activation_hook(
	SBSECOM_FILE,
	function () {
		require_once SBSECOM_DIR . 'src/Install/Schema.php';
		SBSECOM\Install\Schema::install();
	}
);

register_deactivation_hook(
	SBSECOM_FILE,
	function () {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sbsecom_backfill_batch', array(), 'sales-by-state-report-for-easycommerce' );
		}
	}
);
