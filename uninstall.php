<?php
/**
 * Removes the plugin's data when it is deleted.
 *
 * @package SalesByStateReportForEasyCommerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$sbsecom_options = array(
	'sbsecom_db_version',
	'sbsecom_backfill_cursor',
	'sbsecom_year_start',
);

foreach ( $sbsecom_options as $sbsecom_option ) {
	delete_option( $sbsecom_option );
}

if ( is_multisite() ) {
	foreach ( $sbsecom_options as $sbsecom_option ) {
		delete_site_option( $sbsecom_option );
	}
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sbsecom_order_state" );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'sbsecom_backfill_batch', array(), 'sales-by-state-report-for-easycommerce' );
}
