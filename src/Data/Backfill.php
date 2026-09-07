<?php
/**
 * Populates the report table from existing orders.
 *
 * @package SalesByStateReportForEasyCommerce
 */

namespace SBSECOM\Data;

use SBSECOM\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Walks every sale order once, in ascending ID order.
 */
class Backfill {

	/**
	 * Option holding the highest order ID processed so far.
	 */
	const CURSOR_OPTION = 'sbsecom_backfill_cursor';

	/**
	 * Process one batch.
	 *
	 * @param int $limit Orders per batch.
	 * @return array{processed:int,remaining:int,complete:bool,cursor:int}
	 */
	public static function run_batch( $limit = 500 ) {
		Schema::maybe_install();

		$cursor = (int) get_option( self::CURSOR_OPTION, 0 );
		$ids    = OrderSource::ids_after( $cursor, max( 1, (int) $limit ) );

		if ( ! $ids ) {
			return array(
				'processed' => 0,
				'remaining' => 0,
				'complete'  => true,
				'cursor'    => $cursor,
			);
		}

		$sync      = new Sync();
		$processed = 0;

		foreach ( $ids as $id ) {
			if ( $sync->upsert( $id ) ) {
				++$processed;
			}

			$cursor = max( $cursor, (int) $id );
		}

		update_option( self::CURSOR_OPTION, $cursor, false );

		$remaining = OrderSource::count_after( $cursor );

		return array(
			'processed' => $processed,
			'remaining' => $remaining,
			'complete'  => 0 === $remaining,
			'cursor'    => $cursor,
		);
	}

	/**
	 * Number of orders still to be walked.
	 *
	 * @return int
	 */
	public static function remaining() {
		return OrderSource::count_after( (int) get_option( self::CURSOR_OPTION, 0 ) );
	}

	/**
	 * Whether the table has been built.
	 *
	 * @return bool
	 */
	public static function is_complete() {
		return 0 === self::remaining();
	}

	/**
	 * Empty the table and start again.
	 *
	 * @return void
	 */
	public static function reset() {
		Schema::maybe_install();
		Schema::truncate();
		delete_option( self::CURSOR_OPTION );
	}

	/**
	 * Restart the import when the table is empty but the store has orders.
	 *
	 * @return void
	 */
	public static function heal_if_empty() {
		if ( ! Schema::table_exists() || ! self::is_complete() ) {
			return;
		}

		$counts = Schema::counts();

		if ( $counts['rows'] > 0 || $counts['orders'] <= 0 ) {
			return;
		}

		delete_option( self::CURSOR_OPTION );
	}
}
