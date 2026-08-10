<?php
// admin/partials/settings-cron.php
if (!defined('ABSPATH')) exit;
$emails_per_batch = get_option('advnews_emails_per_batch', 50);
$minutes_between_batches = get_option('advnews_minutes_between_batches', 20);
$cooldown_days = get_option('advnews_cooldown_days', 5);
$max_emails_per_day = get_option('advnews_max_emails_per_day', 1000);
$pause_start_hour = get_option('advnews_pause_start_hour', '');
$pause_end_hour = get_option('advnews_pause_end_hour', '');
$pause_timezone = get_option('advnews_pause_timezone', wp_timezone_string());
$cron_method = get_option('advnews_cron_method', 'wp_cron');
?>
<div class="advnews-settings-section">
<h2><?php _e('Cron & Scheduling Settings', 'advnews-manager'); ?></h2>
<!-- Cron Method Selection -->
<div class="settings-group">
<h3><?php _e('Cron Method', 'advnews-manager'); ?></h3>
<p class="description"><?php _e('Choose how the plugin processes your email queue. System Cron is recommended for high-volume sending.', 'advnews-manager'); ?></p>
<table class="form-table">
<tr>
<th scope="row">
<?php _e('Cron Type', 'advnews-manager'); ?>
</th>
<td>
<label>
<input type="radio" name="advnews_cron_method" value="wp_cron"
<?php checked($cron_method, 'wp_cron'); ?>>
<strong><?php _e('WordPress Cron (Default)', 'advnews-manager'); ?></strong>
</label>
<p class="description">
<?php _e('Runs on page loads. Good for low to medium volume sites with regular traffic.', 'advnews-manager'); ?>
</p>
<label style="margin-top:10px; display:block;">
<input type="radio" name="advnews_cron_method" value="system_cron"
<?php checked($cron_method, 'system_cron'); ?>>
<strong><?php _e('System Cron (Recommended for High Volume)', 'advnews-manager'); ?></strong>
</label>
<p class="description">
<?php _e('More reliable for large lists. Requires server configuration but works independently of site traffic.', 'advnews-manager'); ?>
</p>
</td>
</tr>
</table>
</div>
<!-- Queue Management -->
<div class="settings-group">
<h3><?php _e('Queue Management', 'advnews-manager'); ?></h3>
<table class="form-table">
<tr>
<th scope="row">
<label for="advnews_emails_per_batch"><?php _e('Emails per Batch', 'advnews-manager'); ?></label>
</th>
<td>
<input type="number" id="advnews_emails_per_batch" name="advnews_emails_per_batch"
value="<?php echo esc_attr($emails_per_batch); ?>" class="small-text"
min="1" max="500" step="1">
<p class="description">
<?php _e('Number of emails to send in each batch. Lower for shared hosting (10-50), higher for dedicated servers (100-500).', 'advnews-manager'); ?>
</p>
</td>
</tr>
<tr>
<th scope="row">
<label for="advnews_minutes_between_batches"><?php _e('Minutes Between Batches', 'advnews-manager'); ?></label>
</th>
<td>
<input type="number" id="advnews_minutes_between_batches" name="advnews_minutes_between_batches"
value="<?php echo esc_attr($minutes_between_batches); ?>" class="small-text"
min="1" max="120" step="1">
<p class="description">
<?php _e('Wait time between batches to prevent server overload and respect SMTP provider limits.', 'advnews-manager'); ?>
</p>
</td>
</tr>
<tr>
<th scope="row">
<label for="advnews_max_emails_per_day"><?php _e('Max Emails Per Day', 'advnews-manager'); ?></label>
</th>
<td>
<input type="number" id="advnews_max_emails_per_day" name="advnews_max_emails_per_day"
value="<?php echo esc_attr($max_emails_per_day); ?>" class="regular-text"
min="0" max="100000" step="100">
<p class="description">
<?php _e('Maximum emails to send per day (0 for unlimited). Should match your SMTP provider limits.', 'advnews-manager'); ?>
</p>
</td>
</tr>
</table>
<div class="queue-status">
<h4><?php _e('Current Queue Status', 'advnews-manager'); ?></h4>
<?php
$queue_class = new AdvNews_Queue();
$queue_status = $queue_class->get_queue_status();
?>
<div class="queue-stats-mini">
<div class="stat">
<span class="stat-label"><?php _e('Queued:', 'advnews-manager'); ?></span>
<span class="stat-value"><?php echo esc_html($queue_status['queued']); ?></span>
</div>
<div class="stat">
<span class="stat-label"><?php _e('On Cooldown:', 'advnews-manager'); ?></span>
<span class="stat-value" style="color:#f0c33c;"><?php echo esc_html($queue_status['on_cooldown']); ?></span>
</div>
<div class="stat">
<span class="stat-label"><?php _e('Sending:', 'advnews-manager'); ?></span>
<span class="stat-value"><?php echo esc_html($queue_status['sending']); ?></span>
</div>
<div class="stat">
<span class="stat-label"><?php _e('Failed:', 'advnews-manager'); ?></span>
<span class="stat-value"><?php echo esc_html($queue_status['failed']); ?></span>
</div>
<div class="stat">
<span class="stat-label"><?php _e('Completed:', 'advnews-manager'); ?></span>
<span class="stat-value"><?php echo esc_html($queue_status['delivered']); ?></span>
</div>
</div>
<?php if ($queue_status['on_cooldown'] > 0): ?>
<div class="notice notice-warning" style="margin:15px 0;">
<p>
<strong><?php _e('⚠ Cooldown Active:', 'advnews-manager'); ?></strong>
<?php printf(
__('%d emails are waiting for cooldown period to expire. These subscribers received emails recently and must wait before receiving more.', 'advnews-manager'),
$queue_status['on_cooldown']
); ?>
</p>
</div>
<?php endif; ?>
<div class="queue-actions">
<button type="button" class="button" id="pause-queue"><?php _e('Pause Queue', 'advnews-manager'); ?></button>
<button type="button" class="button" id="resume-queue"><?php _e('Resume Queue', 'advnews-manager'); ?></button>
<button type="button" class="button" id="clear-queue"><?php _e('Clear Stuck', 'advnews-manager'); ?></button>
<button type="button" class="button" id="clear-cooldown"><?php _e('Clear Cooldown Delays', 'advnews-manager'); ?></button>
<button type="button" class="button button-primary" id="process-queue-now"><?php _e('Process Queue Now', 'advnews-manager'); ?></button>
</div>
<div id="process-queue-result" style="display:none; margin-top:15px;"></div>
</div>
</div>
<!-- Cooldown Settings -->
<div class="settings-group">
<h3><?php _e('Cooldown Protection', 'advnews-manager'); ?></h3>
<p class="description"><?php _e('Prevent subscribers from receiving too many emails in a short period.', 'advnews-manager'); ?></p>
<table class="form-table">
<tr>
<th scope="row">
<label for="advnews_cooldown_days"><?php _e('Days Between Emails', 'advnews-manager'); ?></label>
</th>
<td>
<input type="number" id="advnews_cooldown_days" name="advnews_cooldown_days"
value="<?php echo esc_attr($cooldown_days); ?>" class="small-text"
min="0" max="30" step="1">
<p class="description">
<?php _e('Minimum days a subscriber must wait between emails from different campaigns. Set to 0 to disable cooldown.', 'advnews-manager'); ?>
</p>
</td>
</tr>
<tr>
<th scope="row">
<?php _e('Cooldown Exceptions', 'advnews-manager'); ?>
</th>
<td>
<label>
<input type="checkbox" name="advnews_cooldown_exceptions[]" value="high_priority"
<?php checked(in_array('high_priority', (array)get_option('advnews_cooldown_exceptions', []))); ?>>
<?php _e('High priority campaigns bypass cooldown', 'advnews-manager'); ?>
</label><br>
<label>
<input type="checkbox" name="advnews_cooldown_exceptions[]" value="transactional"
<?php checked(in_array('transactional', (array)get_option('advnews_cooldown_exceptions', []))); ?>>
<?php _e('Transactional emails bypass cooldown', 'advnews-manager'); ?>
</label>
</td>
</tr>
</table>
</div>
<!-- Pause Schedule -->
<div class="settings-group">
<h3><?php _e('Pause Schedule', 'advnews-manager'); ?></h3>
<p class="description"><?php _e('Pause sending during specific hours (useful for respecting local quiet hours).', 'advnews-manager'); ?></p>
<table class="form-table">
<tr>
<th scope="row">
<label for="advnews_pause_start_hour"><?php _e('Pause From', 'advnews-manager'); ?></label>
</th>
<td>
<input type="time" id="advnews_pause_start_hour" name="advnews_pause_start_hour"
value="<?php echo esc_attr($pause_start_hour); ?>" step="3600">
<span class="description"><?php _e('to', 'advnews-manager'); ?></span>
<input type="time" id="advnews_pause_end_hour" name="advnews_pause_end_hour"
value="<?php echo esc_attr($pause_end_hour); ?>" step="3600">
</td>
</tr>
<tr>
<th scope="row">
<label for="pause_timezone"><?php _e('Timezone', 'advnews-manager'); ?></label>
</th>
<td>
<select id="pause_timezone" name="pause_timezone">
<?php
$timezones = timezone_identifiers_list();
foreach ($timezones as $timezone): ?>
<option value="<?php echo esc_attr($timezone); ?>" <?php selected($pause_timezone, $timezone); ?>>
<?php echo esc_html(str_replace('_', ' ', $timezone)); ?>
</option>
<?php endforeach; ?>
</select>
<p class="description">
<?php _e('Current time in this timezone:', 'advnews-manager'); ?>
<strong><?php echo esc_html(current_time('H:i')); ?></strong>
</p>
</td>
</tr>
</table>
</div>
<!-- Scheduled Tasks -->
<div class="settings-group" id="scheduled-tasks">
<h3><?php _e('Scheduled Tasks', 'advnews-manager'); ?></h3>
<table class="wp-list-table widefat fixed striped">
<thead>
<tr>
<th><?php _e('Task', 'advnews-manager'); ?></th>
<th><?php _e('Next Run', 'advnews-manager'); ?></th>
<th><?php _e('Schedule', 'advnews-manager'); ?></th>
<th><?php _e('Actions', 'advnews-manager'); ?></th>
</tr>
</thead>
<tbody>
<?php
$cron_tasks = array(
'advnews_process_queue' => __('Process Email Queue', 'advnews-manager'),
'advnews_daily_maintenance' => __('Daily Maintenance', 'advnews-manager'),
'advnews_weekly_reports' => __('Weekly Reports', 'advnews-manager')
);
foreach ($cron_tasks as $hook => $name):
$next_run = wp_next_scheduled($hook);
?>
<tr>
<td><?php echo esc_html($name); ?></td>
<td>
<?php if ($next_run): ?>
<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run)); ?>
<br><small><?php echo esc_html(human_time_diff($next_run, current_time('timestamp')) . ' ' . __('from now', 'advnews-manager')); ?></small>
<?php else: ?>
<span class="status-error"><?php _e('Not Scheduled', 'advnews-manager'); ?></span>
<?php endif; ?>
</td>
<td>
<?php
$schedule = wp_get_schedule($hook);
echo $schedule ? esc_html($schedule) : '-';
?>
</td>
<td>
<?php if ($next_run): ?>
<button type="button" class="button button-small run-task-now" data-hook="<?php echo esc_attr($hook); ?>">
<?php _e('Run Now', 'advnews-manager'); ?>
</button>
<button type="button" class="button button-small unschedule-task" data-hook="<?php echo esc_attr($hook); ?>">
<?php _e('Unschedule', 'advnews-manager'); ?>
</button>
<?php else: ?>
<button type="button" class="button button-small schedule-task" data-hook="<?php echo esc_attr($hook); ?>">
<?php _e('Schedule', 'advnews-manager'); ?>
</button>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<!-- Manual Processing -->
<div class="settings-group">
<h3><?php _e('Manual Processing', 'advnews-manager'); ?></h3>
<div class="manual-actions">
<button type="button" class="button" id="process-queue-now-bottom"><?php _e('Process Queue Now', 'advnews-manager'); ?></button>
<button type="button" class="button" id="check-cron-status"><?php _e('Check Cron Status', 'advnews-manager'); ?></button>
</div>
<div id="cron-result" style="display:none; margin-top:15px;"></div>
</div>
</div>
<script>
jQuery(document).ready(function($) {
// Queue actions
$('#pause-queue').on('click', function() {
if (confirm('<?php _e('Are you sure you want to pause the queue?', 'advnews-manager'); ?>')) {
$.ajax({
url: '<?php echo admin_url('admin-ajax.php'); ?>',
type: 'POST',
data: {
action: 'advnews_pause_queue',
nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
},
success: function(response) {
if (response.success) {
alert(response.data.message);
location.reload();
} else {
alert(response.data.message);
}
},
error: function() {
alert('<?php _e('An error occurred.', 'advnews-manager'); ?>');
}
});
}
});
$('#resume-queue').on('click', function() {
if (confirm('<?php _e('Resume the queue?', 'advnews-manager'); ?>')) {
$.ajax({
url: '<?php echo admin_url('admin-ajax.php'); ?>',
type: 'POST',
data: {
action: 'advnews_resume_queue',
nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
},
success: function(response) {
if (response.success) {
alert(response.data.message);
location.reload();
} else {
alert(response.data.message);
}
},
error: function() {
alert('<?php _e('An error occurred.', 'advnews-manager'); ?>');
}
});
}
});
$('#clear-queue').on('click', function() {
if (confirm('<?php _e('Clear all stuck emails from the queue?', 'advnews-manager'); ?>')) {
var button = $(this);
var originalText = button.text();
button.prop('disabled', true).text('<?php _e('Clearing...', 'advnews-manager'); ?>');
$.ajax({
url: '<?php echo admin_url('admin-ajax.php'); ?>',
type: 'POST',
data: {
action: 'advnews_clear_stuck_queue',
nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
},
success: function(response) {
if (response.success) {
alert(response.data.message);
location.reload();
} else {
alert(response.data.message);
button.prop('disabled', false).text(originalText);
}
},
error: function() {
alert('<?php _e('An error occurred.', 'advnews-manager'); ?>');
button.prop('disabled', false).text(originalText);
}
});
}
});
// NEW: Clear cooldown delays
$('#clear-cooldown').on('click', function() {
if (confirm('<?php _e('WARNING: This will remove cooldown delays from ALL queued emails. They will be sent immediately on next queue processing. Continue?', 'advnews-manager'); ?>')) {
var button = $(this);
var originalText = button.text();
var resultDiv = $('#process-queue-result');
button.prop('disabled', true).text('<?php _e('Clearing...', 'advnews-manager'); ?>');
resultDiv.hide().removeClass('updated error');
$.ajax({
url: '<?php echo admin_url('admin-ajax.php'); ?>',
type: 'POST',
data: {
action: 'advnews_clear_cooldown_delays',
nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
},
success: function(response) {
if (response.success) {
resultDiv.removeClass('error').addClass('updated')
.html('<p><strong><?php _e('Success!', 'advnews-manager'); ?></strong> ' + response.data.message + '</p>')
.show();
setTimeout(function() {
location.reload();
}, 2000);
} else {
resultDiv.removeClass('updated').addClass('error')
.html('<p><strong><?php _e('Error!', 'advnews-manager'); ?></strong> ' + response.data.message + '</p>')
.show();
button.prop('disabled', false).text(originalText);
}
},
error: function() {
resultDiv.removeClass('updated').addClass('error')
.html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>')
.show();
button.prop('disabled', false).text(originalText);
}
});
}
});
// Process queue now
$('#process-queue-now, #process-queue-now-bottom').on('click', function() {
var button = $(this);
var originalText = button.text();
var resultDiv = $('#process-queue-result');
button.prop('disabled', true).text('<?php _e('Processing...', 'advnews-manager'); ?>');
resultDiv.hide().removeClass('updated error');
$.ajax({
url: '<?php echo admin_url('admin-ajax.php'); ?>',
type: 'POST',
data: {
action: 'advnews_process_queue_now',
nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
},
success: function(response) {
resultDiv.show();
if (response.success) {
resultDiv.removeClass('error').addClass('updated')
.html('<p><strong><?php _e('Success!', 'advnews-manager'); ?></strong> ' + response.data.message + '</p>' +
(response.data.data && response.data.data.on_cooldown > 0 ?
'<p style="color:#f0c33c;"><strong>⚠ Note:</strong> ' + response.data.data.on_cooldown + ' <?php _e('emails are still on cooldown and will send later.', 'advnews-manager'); ?></p>' : '') +
(response.data.data ? '<pre>' + JSON.stringify(response.data.data, null, 2) + '</pre>' : ''))
.fadeIn();
setTimeout(function() {
location.reload();
}, 3000);
} else {
resultDiv.removeClass('updated').addClass('error')
.html('<p><strong><?php _e('Error!', 'advnews-manager'); ?></strong> ' +
(response.data.message || '<?php _e('An error occurred.', 'advnews-manager'); ?>') + '</p>')
.fadeIn();
button.prop('disabled', false).text(originalText);
}
},
error: function(xhr, status, error) {
resultDiv.show().removeClass('updated').addClass('error')
.html('<p><strong><?php _e('Error!', 'advnews-manager'); ?></strong> ' +
'<?php _e('Connection failed. Please check your settings and server logs.', 'advnews-manager'); ?>' +
(xhr.responseText ? '<pre>' + xhr.responseText + '</pre>' : '') + '</p>')
.fadeIn();
button.prop('disabled', false).text(originalText);
}
});
});
// Check cron status
$('#check-cron-status').on('click', function() {
var resultDiv = $('#cron-result');
resultDiv.show().html('<p><?php _e('Checking...', 'advnews-manager'); ?></p>');
$.ajax({
url: '<?php echo admin_url('admin-ajax.php'); ?>',
type: 'POST',
data: {
action: 'advnews_check_cron',
nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
},
success: function(response) {
if (response.success) {
var html = '<p><strong><?php _e('Cron Status:', 'advnews-manager'); ?></strong> ' + response.data.message + '</p>';
if (response.data.details) {
html += '<ul style="margin-left: 20px;">';
$.each(response.data.details, function(key, value) {
html += '<li><strong>' + key + ':</strong> ' + (value.next_run || value.message) + '</li>';
});
html += '</ul>';
}
resultDiv.removeClass('error').addClass('updated').html(html).show();
} else {
resultDiv.removeClass('updated').addClass('error')
.html('<p>' + response.data.message + '</p>').show();
}
},
error: function() {
resultDiv.removeClass('updated').addClass('error')
.html('<p><?php _e('Cron check failed.', 'advnews-manager'); ?></p>').show();
}
});
});
// Run task now
$('.run-task-now').on('click', function() {
var hook = $(this).data('hook');
var resultDiv = $('#cron-result');
$.ajax({
url: '<?php echo admin_url('admin-ajax.php'); ?>',
type: 'POST',
data: {
action: 'advnews_run_cron_task',
hook: hook,
nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
},
success: function(response) {
if (response.success) {
resultDiv.removeClass('error').addClass('updated')
.html('<p>' + response.data.message + '</p>').show();
} else {
resultDiv.removeClass('updated').addClass('error')
.html('<p>' + response.data.message + '</p>').show();
}
},
error: function() {
resultDiv.removeClass('updated').addClass('error')
.html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>').show();
}
});
});
// Schedule/unschedule tasks
$('.schedule-task').on('click', function() {
var hook = $(this).data('hook');
var resultDiv = $('#cron-result');
$.ajax({
url: '<?php echo admin_url('admin-ajax.php'); ?>',
type: 'POST',
data: {
action: 'advnews_schedule_task',
hook: hook,
nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
},
success: function(response) {
if (response.success) {
location.reload();
} else {
resultDiv.removeClass('updated').addClass('error')
.html('<p>' + response.data.message + '</p>').show();
}
},
error: function() {
resultDiv.removeClass('updated').addClass('error')
.html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>').show();
}
});
});
$('.unschedule-task').on('click', function() {
var hook = $(this).data('hook');
var resultDiv = $('#cron-result');
$.ajax({
url: '<?php echo admin_url('admin-ajax.php'); ?>',
type: 'POST',
data: {
action: 'advnews_unschedule_task',
hook: hook,
nonce: '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>'
},
success: function(response) {
if (response.success) {
location.reload();
} else {
resultDiv.removeClass('updated').addClass('error')
.html('<p>' + response.data.message + '</p>').show();
}
},
error: function() {
resultDiv.removeClass('updated').addClass('error')
.html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>').show();
}
});
});
});
</script>
<style>
.advnews-settings-section {
max-width: 100%;
}
.settings-group {
background: #fff;
border: 1px solid #ccd0d4;
border-radius: 4px;
padding: 20px;
margin-bottom: 20px;
}
.settings-group h3 {
margin-top: 0;
margin-bottom: 15px;
padding-bottom: 10px;
border-bottom: 1px solid #f0f0f0;
}
#cron-guide-result.updated,
#cron-result.updated,
#process-queue-result.updated {
background: #d4edda;
border-left: 4px solid #00a32a;
padding: 10px 15px;
}
#cron-guide-result.error,
#cron-result.error,
#process-queue-result.error {
background: #f8d7da;
border-left: 4px solid #d63638;
padding: 10px 15px;
}
#cron-guide-result pre,
#cron-result pre,
#process-queue-result pre {
background: rgba(0, 0, 0, 0.05);
padding: 10px;
border-radius: 4px;
overflow-x: auto;
font-size: 12px;
margin-top: 10px;
}
.queue-status {
background: #f8f9fa;
border: 1px solid #ccd0d4;
border-radius: 4px;
padding: 15px;
margin-top: 15px;
}
.queue-stats-mini {
display: grid;
grid-template-columns: repeat(5, 1fr);
gap: 10px;
margin-bottom: 15px;
}
.queue-stats-mini .stat {
text-align: center;
padding: 10px;
background: #fff;
border-radius: 4px;
}
.queue-stats-mini .stat-label {
display: block;
font-size: 11px;
color: #646970;
}
.queue-stats-mini .stat-value {
display: block;
font-size: 16px;
font-weight: 600;
color: #2271b1;
}
.queue-actions,
.manual-actions {
display: flex;
gap: 10px;
justify-content: flex-end;
flex-wrap: wrap;
}
.status-error {
color: #d63638;
font-weight: 600;
}
.dashicons-yes {
color: #00a32a;
}
.dashicons-no {
color: #d63638;
}
@media (max-width: 782px) {
.queue-stats-mini {
grid-template-columns: repeat(2, 1fr);
}
.queue-actions,
.manual-actions {
flex-direction: column;
}
.queue-actions .button,
.manual-actions .button {
width: 100%;
}
}
</style>
