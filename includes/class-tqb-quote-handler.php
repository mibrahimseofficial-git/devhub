<?php
/**
 * TQB_Quote_Handler
 *
 * The WordPress-facing bridge between raw form input, the database (via
 * TQB_DB), and the pure calculation logic (TQB_Pricing_Engine). This is
 * where $wpdb-backed data gets shaped into the plain arrays the engine
 * expects — the engine itself never touches WordPress or the database.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Quote_Handler {

	/**
	 * Process an Individual quote submission end to end: fetch config,
	 * calculate, save to DB. Does NOT send emails or sync HubSpot — those
	 * are separate phases, hooked in afterward via the returned submission ID.
	 *
	 * @param array $contact  [ 'name' => ..., 'email' => ..., 'phone' => ... ]
	 * @param array $answers  [ item_key => [ 'selected' => bool, 'qty' => int ], ... ]
	 * @return array [ 'submission_id' => int, 'result' => <engine result array> ]
	 */
	public static function process_individual_quote( array $contact, array $answers ) {
		$line_items = TQB_DB::get_line_items( 'individual', false );

		$result = TQB_Pricing_Engine::calculate_individual( $line_items, $answers );

		$submission_id = TQB_DB::insert_submission( array(
			'quote_type'          => 'individual',
			'contact_name'        => $contact['name'],
			'contact_email'       => $contact['email'],
			'contact_phone'       => $contact['phone'],
			'answers'             => $answers,
			'calculated_total'    => $result['total'],
			'is_custom_quote'     => $result['is_custom_quote'],
			'custom_quote_reason' => $result['custom_quote_reason'],
		) );

		return array(
			'submission_id' => $submission_id,
			'result'        => $result,
		);
	}

	/**
	 * Process a Business quote submission end to end.
	 *
	 * @param array  $contact       Same shape as above
	 * @param string $entity_type   'c_corp' | 's_corp' | 'partnership'
	 * @param string $asset_band_label    e.g. 'Under $250K' — must match a
	 *                                    band_label in wp_tqb_rate_bands
	 * @param string $revenue_band_label  e.g. 'Under $250K'
	 * @param array  $extra_answers       Same shape as Individual $answers,
	 *                                    for the Part B extras
	 * @return array [ 'submission_id' => int, 'result' => <engine result array> ]
	 * @throws InvalidArgumentException if the asset/revenue band labels don't
	 *         match any row in the rate bands table (front-end should only
	 *         ever send values from the dropdowns populated by TQB_DB, so
	 *         this should not happen in normal operation — it's a safety net).
	 */
	public static function process_business_quote(
		array $contact,
		string $entity_type,
		string $asset_band_label,
		string $revenue_band_label,
		array $extra_answers
	) {
		$entity_group = ( 'partnership' === $entity_type ) ? 'partnership' : 'c_s_corp';

		$asset_bands = TQB_DB::get_asset_bands( $entity_group );
		$asset_band  = self::find_band_by_label( $asset_bands, $asset_band_label );

		$revenue_bands = TQB_DB::get_revenue_addons();
		$revenue_band  = self::find_band_by_label( $revenue_bands, $revenue_band_label );

		if ( ! $asset_band || ! $revenue_band ) {
			throw new InvalidArgumentException( 'Invalid asset or revenue band label — does not match any configured rate band.' );
		}

		$extra_items = TQB_DB::get_line_items( 'business', false );

		$result = TQB_Pricing_Engine::calculate_business(
			$entity_type,
			$asset_band,
			$revenue_band,
			$extra_items,
			$extra_answers
		);

		$answers_to_store = array_merge(
			array(
				'entity_type'   => $entity_type,
				'asset_band'    => $asset_band_label,
				'revenue_band'  => $revenue_band_label,
			),
			$extra_answers
		);

		$submission_id = TQB_DB::insert_submission( array(
			'quote_type'          => 'business',
			'contact_name'        => $contact['name'],
			'contact_email'       => $contact['email'],
			'contact_phone'       => $contact['phone'],
			'answers'             => $answers_to_store,
			'calculated_total'    => $result['total'],
			'is_custom_quote'     => $result['is_custom_quote'],
			'custom_quote_reason' => $result['custom_quote_reason'],
		) );

		return array(
			'submission_id' => $submission_id,
			'result'        => $result,
		);
	}

	private static function find_band_by_label( array $bands, string $label ) {
		foreach ( $bands as $band ) {
			if ( $band['band_label'] === $label ) {
				return $band;
			}
		}
		return null;
	}
}
