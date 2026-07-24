<?php
/**
 * TQB_Hubspot
 *
 * Syncs each submission to HubSpot as a contact + an associated deal, using
 * a Service Key / private app access token as a Bearer token (see
 * PROJECT_SPEC.md Section 8 — client confirmed direct API integration,
 * not Zapier). Uses WordPress's wp_remote_* HTTP API rather than raw curl,
 * per WP plugin conventions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TQB_Hubspot {

	const API_BASE = 'https://api.hubapi.com';

	/**
	 * Fetches all deal pipelines (and their stages) from HubSpot, for the
	 * admin dashboard's dynamic dropdowns. Read-only — uses the same
	 * Service Key, no extra scope needed since crm.objects.deals.read
	 * already covers pipeline metadata.
	 *
	 * @return array|WP_Error  Array of [ 'id', 'label', 'stages' => [ ['id','label'], ... ] ] on success
	 */
	public static function get_pipelines( $service_key ) {
		if ( empty( $service_key ) ) {
			return new WP_Error( 'tqb_no_key', 'No HubSpot Service Key configured.' );
		}

		$response = wp_remote_get(
			self::API_BASE . '/crm/v3/pipelines/deals',
			array(
				'headers' => self::auth_headers( $service_key ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'tqb_hubspot_error', 'HubSpot returned HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['results'] ) ) {
			return array();
		}

		$pipelines = array();
		foreach ( $body['results'] as $pipeline ) {
			$stages = array();
			foreach ( ( $pipeline['stages'] ?? array() ) as $stage ) {
				$stages[] = array(
					'id'    => $stage['id'],
					'label' => $stage['label'],
				);
			}
			$pipelines[] = array(
				'id'     => $pipeline['id'],
				'label'  => $pipeline['label'],
				'stages' => $stages,
			);
		}

		return $pipelines;
	}

	/**
	 * Syncs one submission to HubSpot: find-or-create the contact, then
	 * create a deal associated with it. Called right after a submission is
	 * saved (same place TQB_Email is triggered from). Failures are logged,
	 * not thrown — a HubSpot outage should never block the prospect from
	 * seeing their result, since calculation + save already succeeded.
	 */
	public static function sync_submission( $submission_id ) {
		$service_key = get_option( 'tqb_hubspot_service_key', '' );

		if ( empty( $service_key ) ) {
			// Not configured yet — silently skip. This is expected until
			// the client's token is pasted into General Settings.
			return;
		}

		$submission = TQB_DB::get_submission( $submission_id );
		if ( ! $submission ) {
			return;
		}

		$contact_id = self::find_or_create_contact( $submission, $service_key );

		if ( ! $contact_id ) {
			self::log_error( 'Could not create/find HubSpot contact for submission #' . $submission_id );
			return;
		}

		$deal_id = self::create_deal( $submission, $contact_id, $service_key );

		if ( $deal_id ) {
			TQB_DB::mark_hubspot_synced( $submission_id, $contact_id, $deal_id );
		} else {
			// Contact synced but deal failed — still record the contact ID
			// so we're not stuck retrying the whole thing blind next time.
			TQB_DB::mark_hubspot_synced( $submission_id, $contact_id, null );
			self::log_error( 'HubSpot contact synced but deal creation failed for submission #' . $submission_id );
		}
	}

	/**
	 * Searches for an existing contact by email; updates it if found,
	 * otherwise creates a new one. Returns the HubSpot contact ID, or null
	 * on failure.
	 */
	private static function find_or_create_contact( array $submission, $service_key ) {
		$existing_id = self::search_contact_by_email( $submission['contact_email'], $service_key );

		$name_parts = self::split_name( $submission['contact_name'] );

		$properties = array(
			'email'     => $submission['contact_email'],
			'firstname' => $name_parts['first'],
			'lastname'  => $name_parts['last'],
			'phone'     => $submission['contact_phone'],
		);

		if ( $existing_id ) {
			$response = wp_remote_request(
				self::API_BASE . '/crm/v3/objects/contacts/' . rawurlencode( $existing_id ),
				array(
					'method'  => 'PATCH',
					'headers' => self::auth_headers( $service_key ),
					'body'    => wp_json_encode( array( 'properties' => $properties ) ),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				self::log_error( 'HubSpot contact update failed: ' . self::describe_response( $response ) );
				return null;
			}

			return $existing_id;
		}

		$response = wp_remote_post(
			self::API_BASE . '/crm/v3/objects/contacts',
			array(
				'headers' => self::auth_headers( $service_key ),
				'body'    => wp_json_encode( array( 'properties' => $properties ) ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 201 !== wp_remote_retrieve_response_code( $response ) ) {
			self::log_error( 'HubSpot contact create failed: ' . self::describe_response( $response ) );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['id'] ?? null;
	}

	/**
	 * Looks up a contact by email via the CRM search endpoint. Returns the
	 * contact ID if found, or null (not found, or the request failed).
	 */
	private static function search_contact_by_email( $email, $service_key ) {
		$response = wp_remote_post(
			self::API_BASE . '/crm/v3/objects/contacts/search',
			array(
				'headers' => self::auth_headers( $service_key ),
				'body'    => wp_json_encode( array(
					'filterGroups' => array(
						array(
							'filters' => array(
								array(
									'propertyName' => 'email',
									'operator'     => 'EQ',
									'value'        => $email,
								),
							),
						),
					),
					'limit' => 1,
				) ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Not fatal — we just fall through to "create new" if search fails.
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['results'][0]['id'] ) ) {
			return $body['results'][0]['id'];
		}

		return null;
	}

	/**
	 * Creates a deal and associates it with the given contact in the same
	 * request (association type 3 = the standard HubSpot-defined
	 * "deal to contact" primary association).
	 *
	 * Pipeline/deal stage are optional settings (General Settings tab) —
	 * if left blank, HubSpot's account default pipeline/stage is used.
	 * Deal name and amount reflect the quote result; for custom-quote
	 * submissions, amount is omitted and the reason goes in the deal name
	 * and a note property instead, since there's no calculated number yet.
	 */
	private static function create_deal( array $submission, $contact_id, $service_key ) {
		$quote_type_label = ( 'business' === $submission['quote_type'] ) ? 'Business' : 'Individual';

		$properties = array(
			'dealname' => $quote_type_label . ' Tax Return Quote — ' . $submission['contact_name'],
		);

		if ( $submission['is_custom_quote'] ) {
			$properties['dealname'] .= ' (Custom Quote Needed)';
		} else {
			$properties['amount'] = (string) $submission['calculated_total'];
		}

		$pipeline_id = get_option( 'tqb_hubspot_pipeline_id', '' );
		if ( ! empty( $pipeline_id ) ) {
			$properties['pipeline'] = $pipeline_id;
		}

		// Route to a different stage depending on whether this needed a
		// custom quote — lets the sales team immediately see, from the deal
		// board alone, which leads need manual pricing vs. which already
		// got an instant number. Falls back to the single legacy stage
		// setting if the two-stage settings aren't configured yet.
		$stage_id = $submission['is_custom_quote']
			? get_option( 'tqb_hubspot_stage_custom', '' )
			: get_option( 'tqb_hubspot_stage_new', '' );

		if ( empty( $stage_id ) ) {
			$stage_id = get_option( 'tqb_hubspot_deal_stage_id', '' ); // legacy fallback
		}

		if ( ! empty( $stage_id ) ) {
			$properties['dealstage'] = $stage_id;
		}

		$payload = array(
			'properties'   => $properties,
			'associations' => array(
				array(
					'to'    => array( 'id' => $contact_id ),
					'types' => array(
						array(
							'associationCategory' => 'HUBSPOT_DEFINED',
							'associationTypeId'   => 3, // deal_to_contact (primary)
						),
					),
				),
			),
		);

		$response = wp_remote_post(
			self::API_BASE . '/crm/v3/objects/deals',
			array(
				'headers' => self::auth_headers( $service_key ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 201 !== wp_remote_retrieve_response_code( $response ) ) {
			self::log_error( 'HubSpot deal create failed: ' . self::describe_response( $response ) );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['id'] ?? null;
	}

	private static function auth_headers( $service_key ) {
		return array(
			'Authorization' => 'Bearer ' . $service_key,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Splits a single "full name" field into first/last for HubSpot's
	 * separate firstname/lastname properties. Last word becomes the last
	 * name; everything before it becomes the first name. Single-word names
	 * are stored entirely as firstname, lastname left blank.
	 */
	private static function split_name( $full_name ) {
		$full_name = trim( $full_name );
		$parts     = preg_split( '/\s+/', $full_name );

		if ( count( $parts ) < 2 ) {
			return array( 'first' => $full_name, 'last' => '' );
		}

		$last  = array_pop( $parts );
		$first = implode( ' ', $parts );

		return array( 'first' => $first, 'last' => $last );
	}

	private static function describe_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}
		return 'HTTP ' . wp_remote_retrieve_response_code( $response ) . ' — ' . wp_remote_retrieve_body( $response );
	}

	private static function log_error( $message ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional: surfaces sync failures without blocking the user-facing flow.
		error_log( 'TQB_Hubspot: ' . $message );
	}
}
