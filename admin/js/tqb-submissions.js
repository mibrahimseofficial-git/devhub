/**
 * Tavola Quote Builder — submissions page JS.
 * Handles the submission details modal.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        var $modal = $('#tqb-modal'),
            $overlay = $('#tqb-modal-overlay'),
            $content = $('#tqb-modal-content');

        function formatDate(s) {
            if (!s) return '<span style="color:#94a3b8;">N/A</span>';
            var d = new Date(s);
            return d.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function getStatusBadge(s) {
            var c = 'completed', l = 'Completed';
            if (s === 'in_progress') { c = 'in_progress'; l = 'In Progress'; }
            else if (s === 'abandoned') { c = 'abandoned'; l = 'Abandoned'; }
            return '<span class="tqb-status-badge ' + c + '"><span class="dot"></span>' + l + '</span>';
        }

        function escHtml(s) {
            if (!s) return '';
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function render(data) {
            var ans = {};
            try { ans = JSON.parse(data.answers || '{}'); } catch(e) {}

            var h = '';

            // Contact Information
            h += '<div class="tqb-modal-section">';
            h += '<div class="tqb-modal-section-title"><span class="dashicons dashicons-admin-users"></span> Contact Information</div>';
            h += '<div class="tqb-info-grid">';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Full Name</div><div class="tqb-info-value">' + escHtml(data.contact_name || '—') + '</div></div>';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Email Address</div><div class="tqb-info-value"><a href="mailto:' + escHtml(data.contact_email || '') + '">' + escHtml(data.contact_email || '—') + '</a></div></div>';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Phone Number</div><div class="tqb-info-value">' + escHtml(data.contact_phone || '—') + '</div></div>';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Quote Type</div><div class="tqb-info-value"><span class="tqb-type-badge ' + escHtml(data.quote_type || '') + '">' + (data.quote_type ? data.quote_type.charAt(0).toUpperCase() + data.quote_type.slice(1) : '—') + '</span></div></div>';
            h += '</div></div>';

            // Quote Details
            h += '<div class="tqb-modal-section">';
            h += '<div class="tqb-modal-section-title"><span class="dashicons dashicons-money-alt"></span> Quote Details</div>';
            h += '<div class="tqb-info-grid">';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Status</div><div class="tqb-info-value">' + getStatusBadge(data.status) + '</div></div>';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Progress</div><div class="tqb-info-value"><span class="tqb-progress-badge">' + (function() {
                var steps = ['', '1-Type', '2-Contact', '3-Questions', '4-Review', '5-Complete'];
                return steps[data.last_completed_step] || '—';
            })() + '</span></div></div>';
            
            if (data.is_custom_quote == 1) {
                h += '<div class="tqb-info-card full"><div class="tqb-info-label">Quote Amount</div><div class="tqb-info-value" style="color:#d97706; font-size:18px;">Custom Quote Required</div></div>';
                if (data.custom_quote_reason) {
                    h += '<div class="tqb-info-card full"><div class="tqb-info-label">Reason</div><div class="tqb-info-value">' + escHtml(data.custom_quote_reason) + '</div></div>';
                }
            } else {
                var total = data.calculated_total ? parseFloat(data.calculated_total).toFixed(2) : '0.00';
                h += '<div class="tqb-info-card full"><div class="tqb-info-label">Quote Amount</div><div class="tqb-info-value" style="color:#001a44; font-size:24px; font-weight:700;">$' + total + '</div></div>';
            }
            h += '</div></div>';

            // Submitted Answers
            h += '<div class="tqb-modal-section">';
            h += '<div class="tqb-modal-section-title"><span class="dashicons dashicons-list-view"></span> Submitted Answers</div>';
            
            if (Object.keys(ans).length > 0) {
                h += '<table class="tqb-answers-table"><thead><tr><th>Question</th><th>Answer</th></tr></thead><tbody>';
                for (var k in ans) {
                    var v = ans[k];
                    var displayV = '—';
                    var answerClass = '';
                    
                    if (typeof v === 'object' && v !== null && 'selected' in v) {
                        if (v.selected) {
                            displayV = 'Yes';
                            answerClass = 'tqb-answer-yes';
                            if (v.qty && v.qty > 1) displayV += ' (Qty: ' + v.qty + ')';
                        } else {
                            displayV = 'No';
                            answerClass = 'tqb-answer-no';
                        }
                    } else if (typeof v === 'object') {
                        displayV = JSON.stringify(v);
                    } else {
                        displayV = String(v);
                    }
                    
                    var q = k.replace(/^(business|individual)-\d+-/, '').replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                    h += '<tr><td class="tqb-question-cell">' + escHtml(q) + '</td><td class="tqb-answer-cell ' + answerClass + '">' + escHtml(displayV) + '</td></tr>';
                }
                h += '</tbody></table>';
            } else {
                h += '<div style="text-align:center; padding:40px; background:#f8fafc; border-radius:10px; color:#64748b;">';
                h += '<span class="dashicons dashicons-clipboard" style="font-size:48px; width:48px; height:48px; color:#cbd5e1;"></span>';
                h += '<p style="margin:16px 0 0; font-size:14px;">No answers recorded for this submission.</p>';
                h += '</div>';
            }
            h += '</div>';

            // Timestamps
            h += '<div class="tqb-modal-section">';
            h += '<div class="tqb-modal-section-title"><span class="dashicons dashicons-clock"></span> Timeline</div>';
            h += '<div class="tqb-info-grid">';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Submitted</div><div class="tqb-info-value">' + formatDate(data.created_at) + ' CT</div></div>';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Last Updated</div><div class="tqb-info-value">' + formatDate(data.updated_at) + ' CT</div></div>';
            h += '</div></div>';

            return h;
        }

        // Open modal
        $('button.tqb-action-btn.view, .tqb-action-btn.view').on('click', function() {
            var id = $(this).data('id');
            $content.html('<div class="tqb-loading"><div class="tqb-loading-spinner"></div>Loading submission details...</div>');
            $modal.fadeIn(200);
            $overlay.fadeIn(200);
            $('body').css('overflow', 'hidden');
            
            $.post(tqbAdminData.ajaxUrl, {
                action: 'tqb_get_submission',
                id: id,
                nonce: tqbAdminData.nonce
            }, function(resp) {
                if (resp.success) {
                    $content.html(render(resp.data));
                } else {
                    $content.html('<div style="text-align:center; padding:60px 40px;"><span class="dashicons dashicons-warning" style="font-size:64px; width:64px; height:64px; color:#dc2626;"></span><h3 style="margin:16px 0 8px; color:#dc2626;">Error Loading</h3><p style="color:#64748b;">' + (resp.data || 'Could not load submission details.') + '</p></div>');
                }
            }, 'json');
        });

        // Close modal
        function closeModal() {
            $modal.fadeOut(200);
            $overlay.fadeOut(200);
            $('body').css('overflow', '');
        }
        
        $('#tqb-modal-close, #tqb-modal-overlay').on('click', closeModal);
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) closeModal();
        });

        // Select all checkbox
        $('#tqb-select-all').on('change', function() {
            $('.tqb-delete-checkbox').prop('checked', $(this).prop('checked'));
        });
    });
})(jQuery);
