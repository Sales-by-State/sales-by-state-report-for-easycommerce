<?php
/**
 * The values the report's three filters can take.
 *
 * @package SalesByStateReportForEasyCommerce
 */

namespace SBSECOM;

use EasyCommerce\Helpers\Utility;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the filter options and validates what comes back from the browser.
 */
class Filters {

	/**
	 * Option holding the first year offered by the year filter.
	 */
	const YEAR_START_OPTION = 'sbsecom_year_start';

	/**
	 * Number of years offered when the list is first created.
	 */
	const YEAR_WINDOW = 10;

	/**
	 * The columns shown in the report table and summary.
	 *
	 * @return array<string,array{label:string,type:string}>
	 */
	public static function measures() {
		return array(
			'net_revenue'   => array(
				'label' => __( 'Net Sales', 'sales-by-state-report-for-easycommerce' ),
				'type'  => 'currency',
			),
			'gross_revenue' => array(
				'label' => __( 'Gross Sales', 'sales-by-state-report-for-easycommerce' ),
				'type'  => 'currency',
			),
		);
	}

	/**
	 * Measure keys.
	 *
	 * @return string[]
	 */
	public static function measure_keys() {
		return array_keys( self::measures() );
	}

	/**
	 * Order statuses the filter offers.
	 *
	 * Keys are EasyCommerce status slugs.
	 *
	 * @return array<string,string>
	 */
	public static function order_statuses() {
		if ( function_exists( 'easycommerce_order_statuses' ) ) {
			$statuses = easycommerce_order_statuses();
		} else {
			$statuses = array(
				'pending'            => __( 'Pending', 'sales-by-state-report-for-easycommerce' ),
				'processing'         => __( 'Processing', 'sales-by-state-report-for-easycommerce' ),
				'completed'          => __( 'Completed', 'sales-by-state-report-for-easycommerce' ),
				'cancelled'          => __( 'Cancelled', 'sales-by-state-report-for-easycommerce' ),
				'on_hold'            => __( 'On Hold', 'sales-by-state-report-for-easycommerce' ),
				'partially_refunded' => __( 'Partially Refunded', 'sales-by-state-report-for-easycommerce' ),
				'refunded'           => __( 'Refunded', 'sales-by-state-report-for-easycommerce' ),
				'failed'             => __( 'Failed', 'sales-by-state-report-for-easycommerce' ),
			);
		}

		$offered = array();

		foreach ( (array) $statuses as $key => $label ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			$offered[ $key ] = html_entity_decode( (string) $label, ENT_QUOTES, 'UTF-8' );
		}

		return $offered;
	}

	/**
	 * The statuses ticked when the report is opened with no explicit filter.
	 *
	 * Defaults to EasyCommerce paid statuses (processing and completed).
	 *
	 * @return string[]
	 */
	public static function default_statuses() {
		$defaults = array( 'processing', 'completed' );

		/**
		 * Filters the order statuses the report starts on.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $statuses Status keys.
		 */
		$statuses = (array) apply_filters( 'sbsecom_default_statuses', $defaults );

		return array_values( array_intersect( array_map( 'strval', $statuses ), self::status_keys() ) );
	}

	/**
	 * Status slugs as strings.
	 *
	 * @return string[]
	 */
	public static function status_keys() {
		return array_map( 'strval', array_keys( self::order_statuses() ) );
	}

	/**
	 * Reduce a request value to statuses this report recognises.
	 *
	 * @param string|array $value Comma-separated list or array of statuses.
	 * @return string[]
	 */
	public static function normalize_statuses( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$offered  = self::status_keys();
		$accepted = array();

		foreach ( $value as $status ) {
			$status = sanitize_key( trim( (string) $status ) );

			if ( in_array( $status, $offered, true ) ) {
				$accepted[] = $status;
			}
		}

		return array_values( array_unique( $accepted ) );
	}

	/**
	 * Years the filter offers, newest first.
	 *
	 * @return int[]
	 */
	public static function years() {
		$current  = self::default_year();
		$earliest = (int) get_option( self::YEAR_START_OPTION, 0 );

		if ( $earliest <= 0 ) {
			$earliest = $current - ( self::YEAR_WINDOW - 1 );
			update_option( self::YEAR_START_OPTION, $earliest, false );
		}

		if ( $earliest > $current ) {
			$earliest = $current;
		}

		return array_map( 'intval', range( $current, $earliest ) );
	}

	/**
	 * The year the report opens on.
	 *
	 * @return int
	 */
	public static function default_year() {
		return (int) current_time( 'Y' );
	}

	/**
	 * Reduce a request value to a year the filter offers.
	 *
	 * @param mixed $year Requested year.
	 * @return int
	 */
	public static function normalize_year( $year ) {
		$year = (int) $year;

		return in_array( $year, self::years(), true ) ? $year : self::default_year();
	}

	/**
	 * The country the report opens on.
	 *
	 * @return string Two-letter country code.
	 */
	public static function default_country() {
		$code = 'US';

		if ( class_exists( Utility::class ) ) {
			$base = strtoupper( (string) Utility::get_option( 'general', 'business', 'country', 'US' ) );

			if ( preg_match( '/^[A-Z]{2}$/', $base ) ) {
				$code = $base;
			}
		}

		if ( self::states_for( $code ) ) {
			return $code;
		}

		foreach ( array_keys( self::countries_with_states() ) as $candidate ) {
			return $candidate;
		}

		return $code;
	}

	/**
	 * Reduce a request value to a two-letter country code.
	 *
	 * @param mixed $country Requested country.
	 * @return string
	 */
	public static function normalize_country( $country ) {
		$country = strtoupper( (string) $country );

		return preg_match( '/^[A-Z]{2}$/', $country ) ? $country : self::default_country();
	}

	/**
	 * Countries the country dropdown always offers.
	 *
	 * @return array<string,string>
	 */
	public static function countries_with_states() {
		return array(
			'US' => __( 'United States', 'sales-by-state-report-for-easycommerce' ),
			'CA' => __( 'Canada', 'sales-by-state-report-for-easycommerce' ),
			'GB' => __( 'United Kingdom', 'sales-by-state-report-for-easycommerce' ),
		);
	}

	/**
	 * State code => name for a country.
	 *
	 * US, Canada, and the UK use the same fixed catalogs as the other
	 * Sales by State reports. EasyCommerce's locale file includes extra
	 * territories and outlying codes that would add empty rows.
	 *
	 * @param string $country Country code.
	 * @return array<string,string>
	 */
	public static function states_for( $country ) {
		return Regions::states_for( $country );
	}
}
