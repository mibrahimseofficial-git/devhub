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

		<h2 style="margin-top: 30px;">Abandoned Quote Follow-Up</h2>
		<p class="description">When someone starts a quote but doesn't finish, automated emails will encourage them to complete it.</p>

		<table class="form-table" role="presentation" style="max-width: 800px;">
				<tr>
						<th scope="row">Enable Follow-Up Emails</th>
						<td>
								<label for="tqb_enable_abandoned_emails">
										<input type="checkbox" id="tqb_enable_abandoned_emails" name="enable_abandoned_emails" value="1" <?php checked( $enable_abandoned_emails, '1' ); ?> />
										Send follow-up emails to incomplete quotes
								</label>
								<p class="description">When enabled, incomplete quotes will receive reminder emails at the intervals below.</p>
						</td>
				</tr>
		</table>

		<h3 style="margin-top: 20px;">Email Timing</h3>
		<table class="form-table" role="presentation" style="max-width: 600px;">
				<tr>
						<th scope="row" style="width: 200px;"><label for="tqb_reminder_email_hours">Reminder Email</label></th>
						<td>
								<input type="number" id="tqb_reminder_email_hours" name="reminder_email_hours" 
										value="<?php echo esc_attr( $reminder_email_hours ); ?>" min="1" max="720" style="width: 80px;" />
								<span>hours after abandoning quote</span>
								<p class="description">"You didn't finish your quote — here's a quick link to continue."</p>
						</td>
				</tr>
				<tr>
						<th scope="row"><label for="tqb_followup_email_hours">Follow-Up Email</label></th>
						<td>
								<input type="number" id="tqb_followup_email_hours" name="followup_email_hours" 
										value="<?php echo esc_attr( $followup_email_hours ); ?>" min="1" max="720" style="width: 80px;" />
								<span>hours after abandoning quote</span>
								<p class="description">"Need help? Schedule a call with our team."</p>
						</td>
				</tr>
				<tr>
						<th scope="row"><label for="tqb_final_email_hours">Final Email</label></th>
						<td>
								<input type="number" id="tqb_final_email_hours" name="final_email_hours" 
										value="<?php echo esc_attr( $final_email_hours ); ?>" min="1" max="720" style="width: 80px;" />
								<span>hours after abandoning quote</span>
								<p class="description">"Last chance — tax deadlines are approaching."</p>
						</td>
				</tr>
		</table>

	<p class="submit">
		<button type="submit" class="button button-primary">Save Settings</button>
	</p>
</form>

<h2 style="margin-top: 40px;">Database Maintenance</h2>
<p class="description">Use these tools to clean up duplicate entries or reset data.</p>

<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; max-width: 800px; margin-top: 15px;">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row" style="width: 300px;">Remove Duplicate Rate Bands</th>
			<td>
				<p style="margin: 0 0 10px 0;">Removes duplicate entries from the rate bands table. Current count: <strong id="tqb-rate-bands-count">Loading...</strong></p>
				<button type="button" id="tqb-cleanup-rate-bands" class="button">Remove Duplicates</button>
				<span id="tqb-rate-bands-status" style="margin-left: 10px;"></span>
			</td>
		</tr>
		<tr>
			<th scope="row">Remove Duplicate Line Items</th>
			<td>
				<p style="margin: 0 0 10px 0;">Removes duplicate entries from the line items table. Current count: <strong id="tqb-line-items-count">Loading...</strong></p>
				<button type="button" id="tqb-cleanup-line-items" class="button">Remove Duplicates</button>
				<span id="tqb-line-items-status" style="margin-left: 10px;"></span>
			</td>
		</tr>
		<tr>
			<th scope="row">Reset to Defaults</th>
			<td>
				<p style="margin: 0 0 10px 0; color: #b32d2e;"><strong>Warning:</strong> This will delete ALL line items, rate bands, and settings, then re-seed from default data.</p>
				<button type="button" id="tqb-reset-all" class="button button-secondary" style="border-color: #b32d2e; color: #b32d2e;">Reset Everything</button>
				<span id="tqb-reset-status" style="margin-left: 10px;"></span>
			</td>
		</tr>
	</table>
</div>

<script>
(function() {
	// Load counts on page load
	fetch(tqbAdminData.ajaxUrl, {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'action=tqb_get_counts&nonce=' + tqbAdminData.nonce
	})
	.then(r => r.json())
	.then(d => {
		if (d.success) {
			document.getElementById('tqb-rate-bands-count').textContent = d.data.rate_bands + ' entries';
			document.getElementById('tqb-line-items-count').textContent = d.data.line_items + ' entries';
		}
	});

	// Cleanup rate bands
	document.getElementById('tqb-cleanup-rate-bands').onclick = function() {
		if (!confirm('Remove duplicate rate bands? This cannot be undone.')) return;
		var btn = this;
		var status = document.getElementById('tqb-rate-bands-status');
		btn.disabled = true;
		status.textContent = 'Working...';
		fetch(tqbAdminData.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=tqb_cleanup_rate_bands&nonce=' + tqbAdminData.nonce
		})
		.then(r => r.json())
		.then(d => {
			btn.disabled = false;
			if (d.success) {
				status.textContent = 'Done! Removed ' + d.data.deleted + ' duplicates.';
				status.style.color = '#2f6f4e';
				// Refresh count
				document.getElementById('tqb-rate-bands-count').textContent = d.data.remaining + ' entries';
			} else {
				status.textContent = 'Error: ' + (d.data || 'Unknown error');
				status.style.color = '#b32d2e';
			}
		});
	};

	// Cleanup line items
	document.getElementById('tqb-cleanup-line-items').onclick = function() {
		if (!confirm('Remove duplicate line items? This cannot be undone.')) return;
		var btn = this;
		var status = document.getElementById('tqb-line-items-status');
		btn.disabled = true;
		status.textContent = 'Working...';
		fetch(tqbAdminData.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=tqb_cleanup_line_items&nonce=' + tqbAdminData.nonce
		})
		.then(r => r.json())
		.then(d => {
			btn.disabled = false;
			if (d.success) {
				status.textContent = 'Done! Removed ' + d.data.deleted + ' duplicates.';
				status.style.color = '#2f6f4e';
				// Refresh count
				document.getElementById('tqb-line-items-count').textContent = d.data.remaining + ' entries';
			} else {
				status.textContent = 'Error: ' + (d.data || 'Unknown error');
				status.style.color = '#b32d2e';
			}
		});
	};

	// Reset all
	document.getElementById('tqb-reset-all').onclick = function() {
		if (!confirm('RESET EVERYTHING?\n\nThis will delete all line items, rate bands, and settings, then re-seed defaults.\n\nThis cannot be undone!')) return;
		if (!confirm('Are you REALLY sure? Type "RESET" to confirm.')) return;
		var btn = this;
		var status = document.getElementById('tqb-reset-status');
		btn.disabled = true;
		status.textContent = 'Resetting...';
		fetch(tqbAdminData.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=tqb_reset_to_defaults&nonce=' + tqbAdminData.nonce
		})
		.then(r => r.json())
		.then(d => {
			btn.disabled = false;
			if (d.success) {
				status.textContent = 'Done! Reset complete.';
				status.style.color = '#2f6f4e';
				location.reload();
			} else {
				status.textContent = 'Error: ' + (d.data || 'Unknown error');
				status.style.color = '#b32d2e';
			}
		});
	};
})();
</script>
