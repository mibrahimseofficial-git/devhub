<?php
/**
 * General settings: disclaimer text (shown on every proposal), the
 * scheduling link used on the custom-quote screen, and the internal team
 * notification email. Expects $disclaimer_text, $scheduling_link,
 * $notification_email — set by TQB_Admin::render_general_tab().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2>General Settings</h2>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_GENERAL, 'tqb_nonce' ); ?>
	<input type="hidden" name="action" value="tqb_save_general_settings" />

	<table class="form-table" role="presentation" style="max-width: 800px;">
		<tr>
			<th scope="row"><label for="tqb_disclaimer_text">Proposal Disclaimer</label></th>
			<td>
				<textarea id="tqb_disclaimer_text" name="disclaimer_text" rows="4" class="large-text"><?php echo esc_textarea( $disclaimer_text ); ?></textarea>
				<p class="description">Shown prominently on every instant-proposal screen, per the client's request.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tqb_scheduling_link">Scheduling Link</label></th>
			<td>
				<input type="url" id="tqb_scheduling_link" name="scheduling_link" class="regular-text"
					value="<?php echo esc_attr( $scheduling_link ); ?>" placeholder="https://calendly.com/..." />
				<p class="description">
					Shown to prospects whose situation requires a custom quote (crypto, foreign accounts, large businesses).
					<?php if ( empty( $scheduling_link ) ) : ?>
						<strong style="color:#b32d2e;">Not set yet — the custom-quote screen will hide the booking button until this is filled in.</strong>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tqb_notification_email">Team Notification Email</label></th>
			<td>
				<input type="email" id="tqb_notification_email" name="notification_email" class="regular-text"
					value="<?php echo esc_attr( $notification_email ); ?>" />
				<p class="description">Every submission sends a notification here.</p>
			</td>
		</tr>
	</table>

	<h2 style="margin-top: 30px;">HubSpot Integration</h2>
	<p class="description">Every submission automatically creates/updates a HubSpot contact and creates an associated deal. Leave the Service Key blank to disable this (submissions will still save and email normally, just without syncing to HubSpot).</p>

	<table class="form-table" role="presentation" style="max-width: 800px;">
		<tr>
			<th scope="row"><label for="tqb_hubspot_service_key">HubSpot Service Key</label></th>
			<td>
				<input type="password" id="tqb_hubspot_service_key" name="hubspot_service_key" class="regular-text"
					value="<?php echo esc_attr( $hubspot_service_key ); ?>" autocomplete="off" />
				<p class="description">
					From HubSpot: Settings → Integrations → Private Apps → Legacy Apps (or Service Keys, HubSpot's newer equivalent).
					<?php if ( empty( $hubspot_service_key ) ) : ?>
						<strong style="color:#b32d2e;">Not set — HubSpot sync is currently disabled.</strong>
					<?php else : ?>
						<strong style="color:#2f6f4e;">Configured — HubSpot sync is active.</strong>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tqb_hubspot_pipeline_select">Deal Pipeline</label></th>
			<td>
				<button type="button" id="tqb-refresh-pipelines" class="button" style="margin-bottom: 8px;">Refresh from HubSpot</button>
				<span id="tqb-pipeline-status" style="margin-left: 8px; font-size: 13px; color: #666;"></span>
				<br />
				<select id="tqb_hubspot_pipeline_select" style="min-width: 320px;">
					<option value="">— Select a pipeline —</option>
				</select>
				<input type="hidden" id="tqb_hubspot_pipeline_id" name="hubspot_pipeline_id" value="<?php echo esc_attr( $hubspot_pipeline_id ); ?>" />
				<p class="description">Click "Refresh from HubSpot" to load your pipelines (requires the Service Key above to already be saved). Leave unselected to use HubSpot's default pipeline.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tqb_hubspot_stage_new_select">Stage — Instant Quote</label></th>
			<td>
				<select id="tqb_hubspot_stage_new_select" style="min-width: 320px;" disabled>
					<option value="">— Select a pipeline first —</option>
				</select>
				<input type="hidden" id="tqb_hubspot_stage_new" name="hubspot_stage_new" value="<?php echo esc_attr( $hubspot_stage_new ); ?>" />
				<p class="description">Stage for deals where a price was calculated instantly.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tqb_hubspot_stage_custom_select">Stage — Custom Quote Requested</label></th>
			<td>
				<select id="tqb_hubspot_stage_custom_select" style="min-width: 320px;" disabled>
					<option value="">— Select a pipeline first —</option>
				</select>
				<input type="hidden" id="tqb_hubspot_stage_custom" name="hubspot_stage_custom" value="<?php echo esc_attr( $hubspot_stage_custom ); ?>" />
				<p class="description">Stage for deals that need manual pricing (crypto, foreign accounts, large business assets).</p>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary">Save Settings</button>
	</p>
</form>
