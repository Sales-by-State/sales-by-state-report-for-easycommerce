<?php
/**
 * Site Health entries for the report table.
 *
 * @package SalesByStateReportForEasyCommerce
 */

namespace SBSECOM\Admin;

use SBSECOM\Data\Backfill;
use SBSECOM\Install\Schema;
use SBSECOM\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a read-only data check under Site Health → Info.
 */
class Tools {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
	}

	/**
	 * Add a Site Health section.
	 *
	 * @param array $info Existing debug information.
	 * @return array
	 */
	public function debug_information( $info ) {
		if ( ! Plugin::can_view() ) {
			return $info;
		}

		$info['sbsecom-sales-by-state'] = array(
			'label'  => __( 'Sales by State Report for EasyCommerce', 'sales-by-state-report-for-easycommerce' ),
			'fields' => $this->fields(),
		);

		return $info;
	}

	/**
	 * Field list for Site Health.
	 *
	 * @return array
	 */
	private function fields() {
		global $wpdb;

		if ( ! Schema::table_exists() ) {
			return array(
				'table' => array(
					'label' => __( 'Report table', 'sales-by-state-report-for-easycommerce' ),
					'value' => __( 'Missing', 'sales-by-state-report-for-easycommerce' ),
				),
			);
		}

		$counts    = Schema::counts();
		$remaining = Backfill::remaining();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$by_status = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$wpdb->prefix}sbsecom_order_state GROUP BY status ORDER BY total DESC", ARRAY_A );
		$countries = $wpdb->get_results( "SELECT shipping_country AS country, COUNT(*) AS total FROM {$wpdb->prefix}sbsecom_order_state GROUP BY shipping_country ORDER BY total DESC", ARRAY_A );
		$no_state  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sbsecom_order_state WHERE shipping_state = '' AND billing_state = ''" );
		// phpcs:enable

		$fields = array(
			'rows'      => array(
				'label' => __( 'Rows in report table', 'sales-by-state-report-for-easycommerce' ),
				'value' => number_format_i18n( $counts['rows'] ),
			),
			'orders'    => array(
				'label' => __( 'Orders in EasyCommerce', 'sales-by-state-report-for-easycommerce' ),
				'value' => number_format_i18n( $counts['orders'] ),
			),
			'remaining' => array(
				'label' => __( 'Orders still to import', 'sales-by-state-report-for-easycommerce' ),
				'value' => number_format_i18n( $remaining ),
			),
		);

		if ( $no_state > 0 ) {
			$fields['no_state'] = array(
				'label' => __( 'Rows with no state', 'sales-by-state-report-for-easycommerce' ),
				'value' => number_format_i18n( $no_state ),
			);
		}

		$bits = array();

		foreach ( (array) $by_status as $row ) {
			$bits[] = $row['status'] . ': ' . number_format_i18n( (int) $row['total'] );
		}

		if ( $bits ) {
			$fields['by_status'] = array(
				'label' => __( 'By status', 'sales-by-state-report-for-easycommerce' ),
				'value' => implode( ', ', $bits ),
			);
		}

		$bits = array();

		foreach ( (array) $countries as $row ) {
			$code   = '' === $row['country'] ? __( '(blank)', 'sales-by-state-report-for-easycommerce' ) : $row['country'];
			$bits[] = $code . ': ' . number_format_i18n( (int) $row['total'] );
		}

		if ( $bits ) {
			$fields['countries'] = array(
				'label' => __( 'Countries', 'sales-by-state-report-for-easycommerce' ),
				'value' => implode( ', ', $bits ),
			);
		}

		return $fields;
	}
}
