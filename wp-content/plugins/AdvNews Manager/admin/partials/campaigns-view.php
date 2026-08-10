<?php
// admin/partials/campaigns-view.php
if (!defined('ABSPATH')) exit;
$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$campaign_class = new AdvNews_Campaign();
$tracking_class = new AdvNews_Tracking();
$campaign = $campaign_class->get_campaign($campaign_id);
if (!$campaign) {
echo '<div class="notice notice-error"><p>' . __('Campaign not found.', 'advnews-manager') . '</p></div>';
return;
}
$stats = $campaign_class->get_campaign_stats($campaign_id);
$analytics = $tracking_class->get_campaign_analytics($campaign_id);
?>
<div class="wrap advnews-campaign-view">
<h1 class="wp-heading-inline"><?php echo esc_html($campaign->name); ?></h1>
<a href="<?php echo admin_url('admin.php?page=advnews-campaigns&action=edit&id=' . $campaign_id); ?>" class="page-title-action">
<?php _e('Edit Campaign', 'advnews-manager'); ?>
</a>
<a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id); ?>" class="page-title-action">
<?php _e('View Full Analytics', 'advnews-manager'); ?>
</a>
<hr class="wp-header-end">
<div class="advnews-campaign-stats-grid">
<div class="stat-card">
<div class="stat-value"><?php echo esc_html($stats['total']); ?></div>
<div class="stat-label"><?php _e('Total Recipients', 'advnews-manager'); ?></div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo esc_html($stats['delivered']); ?></div>
<div class="stat-label"><?php _e('Delivered', 'advnews-manager'); ?></div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo esc_html($stats['open_rate']); ?>%</div>
<div class="stat-label"><?php _e('Open Rate', 'advnews-manager'); ?></div>
</div>
<div class="stat-card">
<div class="stat-value"><?php echo esc_html($stats['click_rate']); ?>%</div>
<div class="stat-label"><?php _e('Click Rate', 'advnews-manager'); ?></div>
</div>
</div>
<div id="poststuff">
<div id="post-body" class="metabox-holder columns-2">
<div id="post-body-content">
<div class="postbox">
<h2 class="hndle"><?php _e('Campaign Details', 'advnews-manager'); ?></h2>
<div class="inside">
<table class="form-table">
<tr>
<th><?php _e('Subject:', 'advnews-manager'); ?></th>
<td><?php echo esc_html($campaign->subject); ?></td>
</tr>
<tr>
<th><?php _e('Category:', 'advnews-manager'); ?></th>
<td><?php echo esc_html($campaign->category_name); ?></td>
</tr>
<tr>
<th><?php _e('Status:', 'advnews-manager'); ?></th>
<td>
<span class="campaign-status status-<?php echo esc_attr($campaign->status); ?>">
<?php echo esc_html(ucfirst($campaign->status)); ?>
</span>
</td>
</tr>
<tr>
<th><?php _e('Created:', 'advnews-manager'); ?></th>
<td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->created_at))); ?></td>
</tr>
<?php if ($campaign->scheduled_for): ?>
<tr>
<th><?php _e('Scheduled For:', 'advnews-manager'); ?></th>
<td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->scheduled_for))); ?></td>
</tr>
<?php endif; ?>
<?php if ($campaign->sent_at): ?>
<tr>
<th><?php _e('Sent At:', 'advnews-manager'); ?></th>
<td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->sent_at))); ?></td>
</tr>
<?php endif; ?>
</table>
</div>
</div>
<div class="postbox">
<h2 class="hndle"><?php _e('Email Preview', 'advnews-manager'); ?></h2>
<div class="inside">
<div class="advnews-email-preview">
<iframe id="campaign-preview" width="100%" height="500" style="border:1px solid #ddd;"></iframe>
</div>
</div>
</div>
</div>
<div id="postbox-container-1" class="postbox-container">
<div class="postbox">
<h2 class="hndle"><?php _e('Quick Actions', 'advnews-manager'); ?></h2>
<div class="inside">
<div class="advnews-action-buttons">
<?php if ($campaign->status === 'draft'): ?>
<button type="button" class="button button-primary button-large button-block" id="send-campaign-now">
<?php _e('Send Now', 'advnews-manager'); ?>
</button>
<?php endif; ?>
<?php if ($campaign->status === 'scheduled'): ?>
<button type="button" class="button button-primary button-large button-block" id="pause-campaign">
<?php _e('Pause Campaign', 'advnews-manager'); ?>
</button>
<?php endif; ?>
<?php if ($campaign->status === 'paused'): ?>
<button type="button" class="button button-primary button-large button-block" id="resume-campaign">
<?php _e('Resume Campaign', 'advnews-manager'); ?>
</button>
<?php endif; ?>
<button type="button" class="button button-large button-block" id="duplicate-campaign" data-campaign-id="<?php echo $campaign_id; ?>">
<?php _e('Duplicate Campaign', 'advnews-manager'); ?>
</button>
<button type="button" class="button button-link-delete button-large button-block" id="delete-campaign" data-campaign-id="<?php echo $campaign_id; ?>">
<?php _e('Delete Campaign', 'advnews-manager'); ?>
</button>
</div>
</div>
</div>
<div class="postbox">
<h2 class="hndle"><?php _e('Recipient List', 'advnews-manager'); ?></h2>
<div class="inside">
<div class="advnews-recipient-summary">
<p>
<strong><?php _e('Total:', 'advnews-manager'); ?></strong>
<?php echo esc_html($stats['total']); ?>
</p>
<p>
<strong><?php _e('Opened:', 'advnews-manager'); ?></strong>
<?php echo esc_html($stats['opened']); ?>
</p>
<p>
<strong><?php _e('Clicked:', 'advnews-manager'); ?></strong>
<?php echo esc_html($stats['clicked']); ?>
</p>
<p>
<strong><?php _e('Bounced:', 'advnews-manager'); ?></strong>
<?php echo esc_html($stats['bounced']); ?>
</p>
<p>
<strong><?php _e('Unsubscribed:', 'advnews-manager'); ?></strong>
<?php echo esc_html($stats['unsubscribed']); ?>
</p>
</div>
<p>
<a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id); ?>" class="button button-small">
<?php _e('View Full Report', 'advnews-manager'); ?>
</a>
</p>
</div>
</div>
</div>
</div>
</div>
</div>
<div id="campaign-action-result" style="display:none; margin-top:20px;"></div>
<script>
jQuery(document).ready(function($) {
// Load email preview
var previewFrame = document.getElementById('campaign-preview');
if (previewFrame) {
var content = <?php echo json_encode($campaign->content); ?>;
previewFrame.contentDocument.write(content);
previewFrame.contentDocument.close();
}
// Send campaign now
$('#send-campaign-now').on('click', function() {
if (confirm('<?php _e('Are you sure you want to send this campaign now?', 'advnews-manager'); ?>')) {
$.ajax({
url: advnews_ajax.ajax_url,
type: 'POST',
data: {
action: 'advnews_send_campaign',
campaign_id: <?php echo $campaign_id; ?>,
nonce: advnews_ajax.nonce
},
success: function(response) {
if (response.success) {
alert(response.data.message);
location.reload();
} else {
alert(response.data.message);
}
}
});
}
});
// Pause campaign
$('#pause-campaign').on('click', function() {
if (confirm('<?php _e('Are you sure you want to pause this campaign?', 'advnews-manager'); ?>')) {
$.ajax({
url: advnews_ajax.ajax_url,
type: 'POST',
data: {
action: 'advnews_pause_campaign',
campaign_id: <?php echo $campaign_id; ?>,
nonce: advnews_ajax.nonce
},
success: function(response) {
if (response.success) {
alert(response.data.message);
location.reload();
}
}
});
}
});
// Resume campaign
$('#resume-campaign').on('click', function() {
if (confirm('<?php _e('Are you sure you want to resume this campaign?', 'advnews-manager'); ?>')) {
$.ajax({
url: advnews_ajax.ajax_url,
type: 'POST',
data: {
action: 'advnews_resume_campaign',
campaign_id: <?php echo $campaign_id; ?>,
nonce: advnews_ajax.nonce
},
success: function(response) {
if (response.success) {
alert(response.data.message);
location.reload();
}
}
});
}
});
// Duplicate campaign
$('#duplicate-campaign').on('click', function() {
if (confirm('<?php _e('Are you sure you want to duplicate this campaign?', 'advnews-manager'); ?>')) {
var button = $(this);
var resultDiv = $('#campaign-action-result');
button.prop('disabled', true).text('<?php _e('Duplicating...', 'advnews-manager'); ?>');
resultDiv.hide().removeClass('updated error');
$.ajax({
url: advnews_ajax.ajax_url,
type: 'POST',
data: {
action: 'advnews_duplicate_campaign',
campaign_id: <?php echo $campaign_id; ?>,
nonce: advnews_ajax.nonce
},
success: function(response) {
if (response.success) {
resultDiv.addClass('updated').html('<p>' + response.data.message + '</p>').show();
setTimeout(function() {
window.location.href = response.data.redirect_url;
}, 1500);
} else {
resultDiv.addClass('error').html('<p>' + response.data.message + '</p>').show();
button.prop('disabled', false).text('<?php _e('Duplicate Campaign', 'advnews-manager'); ?>');
}
},
error: function() {
resultDiv.addClass('error').html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>').show();
button.prop('disabled', false).text('<?php _e('Duplicate Campaign', 'advnews-manager'); ?>');
}
});
}
});
// Delete campaign - NOW VISIBLE FOR ALL STATUSES
$('#delete-campaign').on('click', function() {
if (confirm('<?php _e('Are you sure you want to delete this campaign? This action cannot be undone and will remove all analytics data.', 'advnews-manager'); ?>')) {
var button = $(this);
var resultDiv = $('#campaign-action-result');
button.prop('disabled', true).text('<?php _e('Deleting...', 'advnews-manager'); ?>');
resultDiv.hide().removeClass('updated error');
$.ajax({
url: advnews_ajax.ajax_url,
type: 'POST',
data: {
action: 'advnews_delete_campaign',
campaign_id: <?php echo $campaign_id; ?>,
nonce: advnews_ajax.nonce
},
success: function(response) {
if (response.success) {
resultDiv.addClass('updated').html('<p>' + response.data.message + '</p>').show();
setTimeout(function() {
window.location.href = response.data.redirect_url;
}, 1500);
} else {
resultDiv.addClass('error').html('<p>' + response.data.message + '</p>').show();
button.prop('disabled', false).text('<?php _e('Delete Campaign', 'advnews-manager'); ?>');
}
},
error: function() {
resultDiv.addClass('error').html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>').show();
button.prop('disabled', false).text('<?php _e('Delete Campaign', 'advnews-manager'); ?>');
}
});
}
});
});
</script>
<style>
.advnews-campaign-stats-grid {
display: grid;
grid-template-columns: repeat(4, 1fr);
gap: 20px;
margin: 20px 0;
}
.stat-card {
background: #fff;
border: 1px solid #ccd0d4;
border-radius: 4px;
padding: 20px;
text-align: center;
box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}
.stat-value {
font-size: 32px;
font-weight: 600;
color: #2271b1;
line-height: 1;
margin-bottom: 10px;
}
.stat-label {
font-size: 14px;
color: #646970;
}
.button-block {
display: block;
width: 100%;
text-align: center;
margin-bottom: 10px;
}
.button-block:last-child {
margin-bottom: 0;
}
.advnews-recipient-summary p {
margin: 5px 0;
padding: 5px 0;
border-bottom: 1px solid #f0f0f0;
}
.advnews-recipient-summary p:last-child {
border-bottom: none;
}
#campaign-action-result.updated {
background: #d4edda;
border-left: 4px solid #00a32a;
padding: 15px;
}
#campaign-action-result.error {
background: #f8d7da;
border-left: 4px solid #d63638;
padding: 15px;
}
.button-link-delete {
color: #d63638;
border-color: #d63638;
}
.button-link-delete:hover {
background: #d63638;
color: #fff;
border-color: #d63638;
}
</style>
