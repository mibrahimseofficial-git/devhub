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

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( TQB_Admin::NONCE_ACTION_GENERAL, 'tqb_nonce' ); ?>
	<input type="hidden" name="action" value="tqb_save_general_settings" />

	<!-- Basic Settings -->
	<div class="tqb-card">
		<div class="tqb-card-header">
			<h2>
				<span class="dashicons dashicons-admin-generic"></span>
				Basic Settings
			</h2>
		</div>
		<div class="tqb-card-body">
			<table class="tqb-form-table">
				<tr>
					<th scope="row"><label for="tqb_disclaimer_text">Proposal Disclaimer</label></th>
					<td>
						<textarea id="tqb_disclaimer_text" name="disclaimer_text" rows="4" style="width:100%; max-width:500px;"><?php echo esc_textarea( $disclaimer_text ); ?></textarea>
						<p class="tqb-description">Shown prominently on every instant-proposal screen, per the client's request.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tqb_scheduling_link">Scheduling Link</label></th>
					<td>
						<input type="url" id="tqb_scheduling_link" name="scheduling_link" style="width:100%; max-width:400px;"
							value="<?php echo esc_attr( $scheduling_link ); ?>" placeholder="https://calendly.com/..." />
						<p class="tqb-description">
							Shown to prospects whose situation requires a custom quote (crypto, foreign accounts, large businesses).
							<?php if ( empty( $scheduling_link ) ) : ?>
								<strong style="color:#dc2626;">Not set yet — the custom-quote screen will hide the booking button.</strong>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tqb_notification_email">Team Notification Email</label></th>
					<td>
						<input type="email" id="tqb_notification_email" name="notification_email" style="width:100%; max-width:300px;"
							value="<?php echo esc_attr( $notification_email ); ?>" />
						<p class="tqb-description">Every submission sends a notification here.</p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<!-- HubSpot Integration -->
	<div class="tqb-card">
		<div class="tqb-card-header">
			<h2>
				<span class="dashicons dashicons-rest-api"></span>
				HubSpot Integration
			</h2>
		</div>
		<div class="tqb-card-body">
			<div class="tqb-alert tqb-alert-info">
				<span class="dashicons dashicons-info"></span>
				<div>Every submission automatically creates/updates a HubSpot contact and creates an associated deal. Leave the Service Key blank to disable this (submissions will still save and email normally).</div>
			</div>

			<table class="tqb-form-table">
				<tr>
					<th scope="row"><label for="tqb_hubspot_service_key">HubSpot Service Key</label></th>
					<td>
						<input type="password" id="tqb_hubspot_service_key" name="hubspot_service_key" style="width:100%; max-width:400px;"
							value="<?php echo esc_attr( $hubspot_service_key ); ?>" autocomplete="off" />
						<p class="tqb-description">
							From HubSpot: Settings → Integrations → Private Apps → Legacy Apps (or Service Keys, HubSpot's newer equivalent).
							<?php if ( empty( $hubspot_service_key ) ) : ?>
								<strong style="color:#dc2626;">Not set — HubSpot sync is currently disabled.</strong>
							<?php else : ?>
								<strong style="color:#22c55e;">Configured — HubSpot sync is active.</strong>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tqb_hubspot_pipeline_select">Deal Pipeline</label></th>
					<td>
						<button type="button" id="tqb-refresh-pipelines" class="tqb-btn tqb-btn-secondary" style="margin-bottom: 12px;">
							<span class="dashicons dashicons-update" style="font-size:16px;"></span>
							Refresh from HubSpot
						</button>
						<span id="tqb-pipeline-status" style="margin-left: 12px; font-size: 13px; color: #64748b;"></span>
						<br /><br />
						<select id="tqb_hubspot_pipeline_select" style="width:100%; max-width:350px;">
							<option value="">— Select a pipeline —</option>
						</select>
						<input type="hidden" id="tqb_hubspot_pipeline_id" name="hubspot_pipeline_id" value="<?php echo esc_attr( $hubspot_pipeline_id ); ?>" />
						<p class="tqb-description">Click "Refresh from HubSpot" to load your pipelines (requires the Service Key above to already be saved). Leave unselected to use HubSpot's default pipeline.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tqb_hubspot_stage_new_select">Stage — Instant Quote</label></th>
					<td>
						<select id="tqb_hubspot_stage_new_select" style="width:100%; max-width:350px;" disabled>
							<option value="">— Select a pipeline first —</option>
						</select>
						<input type="hidden" id="tqb_hubspot_stage_new" name="hubspot_stage_new" value="<?php echo esc_attr( $hubspot_stage_new ); ?>" />
						<p class="tqb-description">Stage for deals where a price was calculated instantly.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tqb_hubspot_stage_custom_select">Stage — Custom Quote</label></th>
					<td>
						<select id="tqb_hubspot_stage_custom_select" style="width:100%; max-width:350px;" disabled>
							<option value="">— Select a pipeline first —</option>
						</select>
						<input type="hidden" id="tqb_hubspot_stage_custom" name="hubspot_stage_custom" value="<?php echo esc_attr( $hubspot_stage_custom ); ?>" />
						<p class="tqb-description">Stage for deals that need manual pricing (crypto, foreign accounts, large business assets).</p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<!-- Abandoned Quote Follow-Up -->
	<div class="tqb-card">
		<div class="tqb-card-header">
			<h2>
				<span class="dashicons dashicons-email-alt"></span>
				Abandoned Quote Follow-Up
			</h2>
		</div>
		<div class="tqb-card-body">
			<div class="tqb-alert tqb-alert-info">
				<span class="dashicons dashicons-info"></span>
				<div>When someone starts a quote but doesn't finish, automated emails will encourage them to complete it.</div>
			</div>

			<table class="tqb-form-table">
				<tr>
					<th scope="row">Enable Follow-Up</th>
					<td>
						<label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
							<input type="checkbox" id="tqb_enable_abandoned_emails" name="enable_abandoned_emails" value="1" <?php checked( $enable_abandoned_emails, '1' ); ?> />
							<span>Send follow-up emails to incomplete quotes</span>
						</label>
						<p class="tqb-description">When enabled, incomplete quotes will receive reminder emails at the intervals below.</p>
					</td>
				</tr>
			</table>

			<h3 class="tqb-section-title" style="font-size:14px; border-bottom:none; padding-bottom:0; margin-top:24px;">
				<span class="dashicons dashicons-clock" style="font-size:16px;"></span>
				Email Timing
			</h3>

			<table class="tqb-form-table" style="max-width:500px;">
				<tr>
					<th scope="row" style="width:160px;"><label for="tqb_reminder_email_hours">Reminder Email</label></th>
					<td>
						<div style="display:flex; align-items:center; gap:12px;">
							<input type="number" id="tqb_reminder_email_hours" name="reminder_email_hours" 
								value="<?php echo esc_attr( $reminder_email_hours ); ?>" min="1" max="720" style="width:80px;" />
							<span style="color:#64748b;">hours after abandoning</span>
						</div>
						<p class="tqb-description">"You didn't finish your quote — here's a quick link to continue."</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tqb_followup_email_hours">Follow-Up Email</label></th>
					<td>
						<div style="display:flex; align-items:center; gap:12px;">
							<input type="number" id="tqb_followup_email_hours" name="followup_email_hours" 
								value="<?php echo esc_attr( $followup_email_hours ); ?>" min="1" max="720" style="width:80px;" />
							<span style="color:#64748b;">hours after abandoning</span>
						</div>
						<p class="tqb-description">"Need help? Schedule a call with our team."</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tqb_final_email_hours">Final Email</label></th>
					<td>
						<div style="display:flex; align-items:center; gap:12px;">
							<input type="number" id="tqb_final_email_hours" name="final_email_hours" 
								value="<?php echo esc_attr( $final_email_hours ); ?>" min="1" max="720" style="width:80px;" />
							<span style="color:#64748b;">hours after abandoning</span>
						</div>
						<p class="tqb-description">"Last chance — tax deadlines are approaching."</p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<div class="tqb-submit">
		<button type="submit" class="tqb-btn tqb-btn-primary">
			<span class="dashicons dashicons-saved" style="font-size:18px;"></span>
			Save Settings
		</button>
	</div>
</form>
