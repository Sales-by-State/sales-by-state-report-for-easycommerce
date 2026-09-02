<?php
/**
 * Keeps the report table in step with EasyCommerce orders.
 *
 * @package SalesByStateReportForEasyCommerce
 */

namespace SBSECOM\Data;

use SBSECOM\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Writes one row per order.
 */
class Sync {

	/**
	 * Order meta keys that affect the report row.
	 *
	 * @var string[]
	 */
	const MONEY_META = array( 'billing_address', 'shipping_address', 'shipping_fee', 'tax', 'shipping_tax', 'currency' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'easycommerce_after_create_order', array( $this, 'on_order_id' ), 20, 1 );
		add_action( 'easycommerce_after_order', array( $this, 'on_order_id' ), 20, 1 );
		add_action( 'easycommerce_order_status_updated', array( $this, 'on_order_id' ), 20, 1 );
		add_action( 'easycommerce-set_order_status', array( $this, 'on_status_object' ), 20, 2 );
		add_action( 'easycommerce_after_delete_order', array( $this, 'on_delete' ), 20, 1 );
		add_action( 'easycommerce_after_bulk_delete_order', array( $this, 'on_bulk_delete' ), 20, 1 );
		add_action( 'easycommerce_after_refund', array( $this, 'on_order_id' ), 20, 1 );
		add_action( 'easycommerce_after_refund_order', array( $this, 'on_refund_order' ), 20, 1 );
		add_action( 'easycommerce_after_add_meta', array( $this, 'on_meta' ), 20, 3 );
		add_action( 'easycommerce_after_update_meta', array( $this, 'on_meta' ), 20, 3 );
	}

	/**
	 * Handle a hook that passes an order ID first.
	 *
	 * @param mixed $order_id Order ID.
	 * @return void
	 */
	public function on_order_id( $order_id ) {
		$this->upsert( (int) $order_id );
	}

	/**
	 * Handle EasyCommerce's status setter, which passes the order object.
	 *
	 * @param string $status Status.
	 * @param mixed  $order  Order object.
	 * @return void
	 */
	public function on_status_object( $status, $order = null ) {
		unset( $status );
		$this->upsert( $this->id_from( $order ) );
	}

	/**
	 * Handle a refund hook that passes the order object.
	 *
	 * @param mixed $order Order object.
	 * @return void
	 */
	public function on_refund_order( $order ) {
		$this->upsert( $this->id_from( $order ) );
	}

	/**
	 * Refresh the row when address or money meta changes.
	 *
	 * EasyCommerce's meta class fires the same hook for every table, so the
	 * order is only written when the key is one this report uses and a matching
	 * order row exists.
	 *
	 * @param int    $unique_id Object ID.
	 * @param string $key       Meta key.
	 * @param mixed  $value     Meta value.
	 * @return void
	 */
	public function on_meta( $unique_id, $key = '', $value = null ) {
		unset( $value );

		if ( ! in_array( (string) $key, self::MONEY_META, true ) ) {
			return;
		}

		$this->upsert( (int) $unique_id );
	}

	/**
	 * Remove the row when an order is deleted.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function on_delete( $order_id ) {
		$this->delete( (int) $order_id );
	}

	/**
	 * Remove rows after a bulk delete.
	 *
	 * @param int[] $order_ids Order IDs.
	 * @return void
	 */
	public function on_bulk_delete( $order_ids ) {
		foreach ( (array) $order_ids as $order_id ) {
			$this->delete( (int) $order_id );
		}
	}

	/**
	 * Insert or update the row for one order.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function upsert( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;

		if ( ! $order_id ) {
			return false;
		}

		$row = self::build_row( $order_id );

		if ( ! $row ) {
			$this->delete( $order_id );
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->replace(
			Schema::table(),
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f' )
		);
	}

	/**
	 * Remove the row for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function delete( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Schema::table(), array( 'order_id' => (int) $order_id ), array( '%d' ) );
	}

	/**
	 * Build the row for an order from EasyCommerce tables.
	 *
	 * Addresses live in order meta. The report groups by shipping region,
	 * falling back to billing when the order has no ship-to address. Net sales
	 * is the order total minus product tax, shipping tax, and shipping.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string,mixed>|false
	 */
	public static function build_row( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status, total, created_at FROM {$wpdb->prefix}ec_orders WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return false;
		}

		$meta = self::order_meta( $order_id );

		$billing  = self::address_from_meta( $meta['billing_address'] ?? '' );
		$shipping = self::address_from_meta( $meta['shipping_address'] ?? '' );

		$billing_country  = self::country_code( $billing['country'] ?? '' );
		$billing_state    = self::state_code( $billing['state'] ?? '', $billing_country );
		$shipping_country = self::country_code( $shipping['country'] ?? '' );
		$shipping_state   = self::state_code( $shipping['state'] ?? '', $shipping_country );

		if ( '' === $shipping_country ) {
			$shipping_country = $billing_country;
			$shipping_state   = $billing_state;
		}

		$total    = round( (float) $order->total, 2 );
		$tax      = round( (float) ( $meta['tax'] ?? 0 ) + (float) ( $meta['shipping_tax'] ?? 0 ), 2 );
		$shipping = round( (float) ( $meta['shipping_fee'] ?? 0 ), 2 );
		$created  = self::normalize_datetime( $order->created_at );
		$paid     = self::paid_datetime( $order_id );

		$currency = strtoupper( substr( (string) ( $meta['currency'] ?? '' ), 0, 3 ) );

		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) && function_exists( 'easycommerce_currency' ) ) {
			$currency = strtoupper( substr( (string) easycommerce_currency(), 0, 3 ) );
		}

		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$currency = 'USD';
		}

		return array(
			'order_id'         => (int) $order->id,
			'status'           => substr( sanitize_key( (string) $order->status ), 0, 32 ),
			'date_created'     => $created ? $created : '0000-00-00 00:00:00',
			'date_paid'        => $paid,
			'billing_country'  => $billing_country,
			'billing_state'    => substr( $billing_state, 0, 50 ),
			'shipping_country' => $shipping_country,
			'shipping_state'   => substr( $shipping_state, 0, 50 ),
			'currency'         => $currency,
			'total_sales'      => $total,
			'tax_total'        => $tax,
			'shipping_total'   => $shipping,
			'net_total'        => $total - $tax - $shipping,
		);
	}

	/**
	 * Load the meta keys used by the report.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string,mixed>
	 */
	private static function order_meta( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->prefix}ec_order_meta
				 WHERE order_id = %d
				   AND meta_key IN ( 'billing_address', 'shipping_address', 'shipping_fee', 'tax', 'shipping_tax', 'currency' )",
				$order_id
			)
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->meta_key ] = self::unserialize_meta( $row->meta_value );
		}

		return $out;
	}

	/**
	 * First completed payment datetime, if EasyCommerce recorded one.
	 *
	 * @param int $order_id Order ID.
	 * @return string|null
	 */
	private static function paid_datetime( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$paid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT created_at FROM {$wpdb->prefix}ec_transactions
				 WHERE order_id = %d
				   AND type = 'payment'
				   AND status = 'completed'
				 ORDER BY created_at ASC
				 LIMIT 1",
				$order_id
			)
		);

		return self::normalize_datetime( $paid );
	}

	/**
	 * Turn stored address meta into an array.
	 *
	 * @param mixed $value Meta value.
	 * @return array<string,mixed>
	 */
	private static function address_from_meta( $value ) {
		$value = self::unserialize_meta( $value );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Unwrap EasyCommerce meta, which may be serialized more than once.
	 *
	 * @param mixed $value Meta value.
	 * @return mixed
	 */
	private static function unserialize_meta( $value ) {
		while ( is_serialized( $value ) ) {
			$value = maybe_unserialize( $value );
		}

		return $value;
	}

	/**
	 * Two-letter country code.
	 *
	 * @param mixed $country Country.
	 * @return string
	 */
	private static function country_code( $country ) {
		$country = trim( (string) $country );

		if ( function_exists( 'easycommerce_country_code' ) ) {
			$country = (string) easycommerce_country_code( $country );
		}

		return strtoupper( substr( $country, 0, 2 ) );
	}

	/**
	 * State / county as EasyCommerce stores it.
	 *
	 * US and Canada use two-letter codes. The UK stores the county name.
	 *
	 * @param mixed  $state   State.
	 * @param string $country Country code.
	 * @return string
	 */
	private static function state_code( $state, $country ) {
		$state = trim( (string) $state );

		if ( in_array( $country, array( 'US', 'CA' ), true ) ) {
			return strtoupper( $state );
		}

		return $state;
	}

	/**
	 * Pull an order ID from an EasyCommerce object or scalar.
	 *
	 * @param mixed $order Order object or ID.
	 * @return int
	 */
	private function id_from( $order ) {
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			return (int) $order->get_id();
		}

		if ( is_object( $order ) && isset( $order->id ) ) {
			return (int) $order->id;
		}

		return (int) $order;
	}

	/**
	 * Normalise a datetime string.
	 *
	 * @param mixed $value Datetime.
	 * @return string|null
	 */
	private static function normalize_datetime( $value ) {
		$value = (string) $value;

		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		$ts = strtotime( $value );

		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}
}
