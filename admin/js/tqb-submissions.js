/**
 * Tavola Quote Builder — submissions page JS.
 * Handles the submission details modal and bulk delete functionality.
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

        var ENTITY_LABELS = {
            c_corp: 'C-Corp',
            s_corp: 'S-Corp',
            partnership: 'Partnership'
        };

        // Normalizes whatever comes back from the server into:
        //   { items: { 'individual-0-w2_wages': {selected,qty}, ... }, quoteTypes: [...], businesses: [...] }
        // Handles three shapes seen in the wild:
        //   1) data.answers already an object (server decoded it) — current combined format
        //   2) data.answers a JSON string of the same combined format (older/unpatched server)
        //   3) data.answers a flat item dict with no quote_types/businesses wrapper (legacy single-type rows)
        function normalizeAnswers(data) {
            var raw = data.answers;

            if (typeof raw === 'string') {
                try { raw = JSON.parse(raw); } catch(e) { raw = {}; }
            }
            if (!raw || typeof raw !== 'object') raw = {};

            // Combined shape: has its own nested "answers" plus quote_types/businesses.
            if (raw.answers && typeof raw.answers === 'object' && !('selected' in raw)) {
                var items = raw.answers;
                // Guard against the double-encoded case slipping through as a string.
                if (typeof items === 'string') {
                    try { items = JSON.parse(items); } catch(e) { items = {}; }
                }
                return {
                    items: items || {},
                    quoteTypes: Array.isArray(raw.quote_types) ? raw.quote_types : [],
                    businesses: Array.isArray(raw.businesses) ? raw.businesses : []
                };
            }

            // Legacy flat shape.
            return { items: raw, quoteTypes: [], businesses: [] };
        }

        function render(data) {
            var parsed = normalizeAnswers(data);
            var ans = parsed.items;
            var businesses = parsed.businesses;

            var h = '';

            // Helper: Get individual add-ons (answers for individual context)
            function getIndividualAddons() {
                var rows = [];
                Object.keys(ans).forEach(function(k) {
                    var v = ans[k];
                    if (k.match(/^individual-\d+-/) && typeof v === 'object' && v !== null && 'selected' in v && v.selected) {
                        var q = k.replace(/^individual-\d+-/, '').replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                        var displayV = 'Yes';
                        if (v.qty && v.qty > 1) displayV += ' (Qty: ' + v.qty + ')';
                        rows.push({ q: q, displayV: displayV });
                    }
                });
                return rows;
            }

            // Helper: Get business add-ons for a specific business index
            function getBusinessAddons(bizIndex) {
                var rows = [];
                Object.keys(ans).forEach(function(k) {
                    var v = ans[k];
                    var match = k.match(/^business-(\d+)-/);
                    if (match && parseInt(match[1], 10) === bizIndex && typeof v === 'object' && v !== null && 'selected' in v && v.selected) {
                        var q = k.replace(/^business-\d+-/, '').replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                        var displayV = 'Yes';
                        if (v.qty && v.qty > 1) displayV += ' (Qty: ' + v.qty + ')';
                        rows.push({ q: q, displayV: displayV });
                    }
                });
                return rows;
            }

            // Contact Information
            h += '<div class="tqb-modal-section">';
            h += '<div class="tqb-modal-section-title"><span class="dashicons dashicons-admin-users"></span> Contact Information</div>';
            h += '<div class="tqb-info-grid">';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Full Name</div><div class="tqb-info-value">' + escHtml(data.contact_name || '—') + '</div></div>';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Email Address</div><div class="tqb-info-value"><a href="mailto:' + escHtml(data.contact_email || '') + '">' + escHtml(data.contact_email || '—') + '</a></div></div>';
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Phone Number</div><div class="tqb-info-value">' + escHtml(data.contact_phone || '—') + '</div></div>';
            var quoteTypeLabel = '—';
            if (parsed.quoteTypes.length > 0) {
                var counts = {};
                parsed.quoteTypes.forEach(function(t) { counts[t] = (counts[t] || 0) + 1; });
                quoteTypeLabel = Object.keys(counts).map(function(t) {
                    var word = t.charAt(0).toUpperCase() + t.slice(1);
                    return counts[t] > 1 ? word + ' (x' + counts[t] + ')' : word;
                }).join(' + ');
            } else if (data.quote_type) {
                quoteTypeLabel = data.quote_type.charAt(0).toUpperCase() + data.quote_type.slice(1);
            }
            h += '<div class="tqb-info-card"><div class="tqb-info-label">Quote Type</div><div class="tqb-info-value"><span class="tqb-type-badge ' + escHtml(data.quote_type || '') + '">' + escHtml(quoteTypeLabel) + '</span></div></div>';
            h += '</div></div>';

            // Quote Information
            h += '<div class="tqb-modal-section">';
            h += '<div class="tqb-modal-section-title"><span class="dashicons dashicons-chart-pie"></span> Quote Information</div>';
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

            // Personal Tax Details with Add-ons
            var hasIndividual = parsed.quoteTypes.indexOf('individual') !== -1;
            if (hasIndividual) {
                var filingLabel = '—';
                if (data.filing_status) {
                    var filingLabels = { single: 'Single', mfj: 'Married Filing Jointly', mfs: 'Married Filing Separately', hoh: 'Head of Household' };
                    filingLabel = filingLabels[data.filing_status] || data.filing_status;
                }
                
                h += '<div class="tqb-modal-section">';
                h += '<div class="tqb-modal-section-title"><span class="dashicons dashicons-admin-users"></span> Personal Tax Details</div>';
                h += '<div class="tqb-info-grid">';
                h += '<div class="tqb-info-card"><div class="tqb-info-label">Filing Status</div><div class="tqb-info-value">' + escHtml(filingLabel) + '</div></div>';
                h += '</div></div>';

                // Personal Add-ons
                var individualRows = getIndividualAddons();
                if (individualRows.length > 0) {
                    h += '<div class="tqb-modal-section">';
                    h += '<div class="tqb-modal-section-title" style="padding-left:12px; font-size:14px; color:#64748b;"><span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px;"></span> Personal Add-ons</div>';
                    h += '<div class="tqb-answers-table-wrap"><table class="tqb-answers-table"><thead><tr><th>Question</th><th>Answer</th></tr></thead><tbody>';
                    individualRows.forEach(function(r) {
                        h += '<tr><td class="tqb-question-cell">' + escHtml(r.q) + '</td><td class="tqb-answer-cell tqb-answer-yes">' + escHtml(r.displayV) + '</td></tr>';
                    });
                    h += '</tbody></table></div></div>';
                }
            }

            // Business Details with Add-ons
            businesses.forEach(function(biz, i) {
                var label = businesses.length > 1 ? 'Business ' + (i + 1) : 'Business';
                h += '<div class="tqb-modal-section">';
                h += '<div class="tqb-modal-section-title"><span class="dashicons dashicons-building"></span> ' + escHtml(label) + '</div>';
                h += '<div class="tqb-info-grid">';
                var entityLabel = ENTITY_LABELS[biz.entity_type] || biz.entity_type || '—';
                h += '<div class="tqb-info-card"><div class="tqb-info-label">Entity Type</div><div class="tqb-info-value">' + escHtml(entityLabel) + '</div></div>';
                h += '<div class="tqb-info-card"><div class="tqb-info-label">Total Assets</div><div class="tqb-info-value">' + escHtml(biz.asset_band || '—') + '</div></div>';
                h += '<div class="tqb-info-card"><div class="tqb-info-label">Annual Revenue</div><div class="tqb-info-value">' + escHtml(biz.revenue_band || '—') + '</div></div>';
                if (biz.business_name) {
                    h += '<div class="tqb-info-card full"><div class="tqb-info-label">Business Name</div><div class="tqb-info-value">' + escHtml(biz.business_name) + '</div></div>';
                }
                h += '</div></div>';

                // Business Add-ons
                var bizRows = getBusinessAddons(i);
                if (bizRows.length > 0) {
                    var bizLabel = businesses.length > 1 ? 'Business ' + (i + 1) + ' Add-ons' : 'Business Add-ons';
                    h += '<div class="tqb-modal-section">';
                    h += '<div class="tqb-modal-section-title" style="padding-left:12px; font-size:14px; color:#64748b;"><span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px;"></span> ' + escHtml(bizLabel) + '</div>';
                    h += '<div class="tqb-answers-table-wrap"><table class="tqb-answers-table"><thead><tr><th>Question</th><th>Answer</th></tr></thead><tbody>';
                    bizRows.forEach(function(r) {
                        h += '<tr><td class="tqb-question-cell">' + escHtml(r.q) + '</td><td class="tqb-answer-cell tqb-answer-yes">' + escHtml(r.displayV) + '</td></tr>';
                    });
                    h += '</tbody></table></div></div>';
                }
            });

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

        // Bulk delete functionality
        function updateBulkActionsUI() {
            var selectedCount = $('.tqb-delete-checkbox:checked').length;
            var $bulkBar = $('#tqb-bulk-bar');
            var $bulkBarRight = $('#tqb-bulk-bar-right');
            var $selectedCount = $('#tqb-selected-count');
            var $selectedNumber = $('#tqb-selected-number');
            
            if (selectedCount > 0) {
                $bulkBar.addClass('visible');
                $bulkBarRight.show();
                $selectedCount.show();
                $selectedNumber.text(selectedCount);
            } else {
                $bulkBar.removeClass('visible');
                $bulkBarRight.hide();
                $selectedCount.hide();
            }
        }

        // Select all checkbox
        $('#tqb-select-all').on('change', function() {
            $('.tqb-delete-checkbox').prop('checked', $(this).prop('checked'));
            updateBulkActionsUI();
        });

        // Individual checkbox change
        $(document).on('change', '.tqb-delete-checkbox', function() {
            // Update select all checkbox state
            var totalCheckboxes = $('.tqb-delete-checkbox').length;
            var checkedCheckboxes = $('.tqb-delete-checkbox:checked').length;
            $('#tqb-select-all').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
            $('#tqb-select-all').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
            
            updateBulkActionsUI();
        });

        // Bulk apply button click
        $('#tqb-bulk-apply-btn').on('click', function() {
            var selectedCount = $('.tqb-delete-checkbox:checked').length;
            var action = $('#tqb-bulk-action-select').val();
            
            if (!action) {
                alert('Please select an action.');
                return;
            }
            
            if (selectedCount === 0) {
                alert('Please select at least one submission to delete.');
                return;
            }
            
            if (action === 'delete') {
                if (!confirm('Are you sure you want to delete ' + selectedCount + ' submission(s)? This cannot be undone.')) {
                    return;
                }
                
                // Get all selected IDs and populate the hidden form
                var $idsContainer = $('#tqb-bulk-ids-container');
                $idsContainer.empty();
                
                // Add bulk_action as a hidden field
                $idsContainer.append('<input type="hidden" name="bulk_action" value="delete" />');
                
                $('.tqb-delete-checkbox:checked').each(function() {
                    $idsContainer.append('<input type="hidden" name="delete_ids[]" value="' + $(this).val() + '" />');
                });
                
                // Submit the form
                $('#tqb-bulk-delete-form').submit();
            }
        });
    });
})(jQuery);
