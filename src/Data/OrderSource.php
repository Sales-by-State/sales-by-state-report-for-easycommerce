<?php
/**
 * Locates EasyCommerce orders.
 *
 * @package SalesByStateReportForEasyCommerce
 */

namespace SBSECOM\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Reads order IDs from EasyCommerce's orders table.
 *
 * Table names are written as literals so every identifier in the SQL is fixed.
 */
class OrderSource {

	/**
	 * Total number of orders on the site.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ec_orders" );
	}

	/**
	 * Number of orders above a cursor.
	 *
	 * @param int $cursor Highest order ID already processed.
	 * @return int
	 */
	public static function count_after( $cursor ) {
		global $wpdb;

		$cursor = max( 0, (int) $cursor );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ec_orders WHERE id > %d",
				$cursor
			)
		);
	}

	/**
	 * The next batch of order IDs after a cursor.
	 *
	 * @param int $cursor Highest order ID already processed.
	 * @param int $limit  Batch size.
	 * @return int[]
	 */
	public static function ids_after( $cursor, $limit ) {
		global $wpdb;

		$cursor = max( 0, (int) $cursor );
		$limit  = max( 1, (int) $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ec_orders
				 WHERE id > %d
				 ORDER BY id ASC
				 LIMIT %d",
				$cursor,
				$limit
			)
		);

		return array_map( 'intval', (array) $ids );
	}
}
