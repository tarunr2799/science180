<?php
// admin/partials/subscribers-import.php
if (!defined('ABSPATH')) exit;
?>
<?php
// At top of subscribers-import.php, after security check
$admin = new AdvNews_Admin();
$server_issues = $admin->check_import_requirements();
if (!empty($server_issues)): ?>
<div class="notice notice-warning">
<p><strong><?php _e('Server Configuration Warning:', 'advnews-manager'); ?></strong></p>
<ul>
<?php foreach ($server_issues as $issue): ?>
<li><?php echo esc_html($issue); ?></li>
<?php endforeach; ?>
</ul>
<p><em><?php _e('These settings may cause import failures. Contact your host to adjust PHP-FPM/memory settings, or use smaller CSV files.', 'advnews-manager'); ?></em></p>
</div>
<?php endif; ?>
<div class="wrap">
<h1><?php _e('Import Subscribers', 'advnews-manager'); ?></h1>
<div class="postbox">
<div class="inside">
<form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" enctype="multipart/form-data" id="advnews-import-form">
<input type="hidden" name="action" value="advnews_import_subscribers">
<!-- Nonce field for security -->
<?php wp_nonce_field('advnews_ajax_nonce', '_wpnonce', false, true); ?>
<!-- Backup nonce field for compatibility -->
<input type="hidden" name="nonce" value="<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>">
<h3><?php _e('Upload CSV or Excel File', 'advnews-manager'); ?></h3>
<p>
<input type="file" name="csv_file" accept=".csv,.xlsx" required>
<br>
<small><?php _e('Maximum file size: 10MB. Supported formats: CSV and Excel (.xlsx).', 'advnews-manager'); ?></small>
</p>
<h3><?php _e('Import Settings', 'advnews-manager'); ?></h3>
<table class="form-table">
<tr>
<th><label for="update_existing"><?php _e('Update Existing:', 'advnews-manager'); ?></label></th>
<td>
<input type="checkbox" id="update_existing" name="update_existing" value="1">
<label for="update_existing"><?php _e('Update information for existing subscribers', 'advnews-manager'); ?></label>
</td>
</tr>
<tr>
<th><label for="skip_duplicates"><?php _e('Skip Duplicates:', 'advnews-manager'); ?></label></th>
<td>
<input type="checkbox" id="skip_duplicates" name="skip_duplicates" value="1" checked>
<label for="skip_duplicates"><?php _e('Skip duplicate email addresses', 'advnews-manager'); ?></label>
</td>
</tr>
<tr>
<th><label for="default_category"><?php _e('Default Categories:', 'advnews-manager'); ?></label></th>
<td>
<?php
global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
$categories = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}{$table_prefix}categories ORDER BY name");
?>
<div id="default_category" class="advnews-import-category-list">
<?php if (empty($categories)): ?>
<p><?php _e('No categories found.', 'advnews-manager'); ?></p>
<?php else: ?>
<?php
foreach ($categories as $category):
?>
<label class="advnews-import-category-option">
<input type="checkbox" name="default_category[]" value="<?php echo esc_attr($category->id); ?>">
<?php echo esc_html($category->name); ?>
</label>
<?php endforeach; ?>
<?php endif; ?>
</div>
<p class="description">
<?php _e('Click one or more categories. Subscribers will be assigned to all selected categories.', 'advnews-manager'); ?>
</p>
</td>
</tr>
</table>
<p class="submit">
<input type="submit" class="button button-primary" value="<?php _e('Import Subscribers', 'advnews-manager'); ?>" id="import-submit">
<span class="spinner" id="import-spinner" style="float:none; margin: 0 0 0 10px;"></span>
</p>
</form>
<div id="import-results" style="display:none;"></div>
</div>
</div>
</div>
<script>
jQuery(document).ready(function($) {
// Intercept form submission for better error handling
$('#advnews-import-form').on('submit', function(e) {
e.preventDefault();
var form = $(this);
var submitBtn = $('#import-submit');
var spinner = $('#import-spinner');
var resultsDiv = $('#import-results');
// Validate file is selected
var fileInput = form.find('input[name="csv_file"]');
if (!fileInput.val()) {
alert('<?php _e('Please select a CSV or Excel file to import.', 'advnews-manager'); ?>');
return false;
}
// Show loading state
submitBtn.prop('disabled', true);
spinner.addClass('is-active');
resultsDiv.hide().removeClass('success error');
// Submit via AJAX for better error handling
$.ajax({
url: form.attr('action'),
type: 'POST',
data: new FormData(form[0]),
processData: false,
contentType: false,
success: function(response) {
console.log('Import Response:', response);
if (response.success) {
resultsDiv.addClass('success')
.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>')
.show();
form[0].reset();
} else {
resultsDiv.addClass('error')
.html('<div class="notice notice-error"><p>' + (response.data.message || '<?php _e('Import failed.', 'advnews-manager'); ?>') + '</p></div>')
.show();
}
},
error: function(xhr, status, error) {
console.error('Import Error:', xhr.responseText);
resultsDiv.addClass('error')
.html('<div class="notice notice-error"><p><?php _e('An error occurred. Please check the debug log.', 'advnews-manager'); ?></p>' +
'<pre>' + xhr.responseText + '</pre></div>')
.show();
},
complete: function() {
submitBtn.prop('disabled', false);
spinner.removeClass('is-active');
}
});
return false;
});
});
</script>
<style>
#import-results .notice {
margin: 20px 0 0;
padding: 15px;
}
#import-results.success .notice {
border-left-color: #00a32a;
}
#import-results.error .notice {
border-left-color: #d63638;
}
#import-results pre {
background: #f0f0f1;
padding: 10px;
border-radius: 4px;
overflow-x: auto;
font-size: 12px;
margin-top: 10px;
}
.advnews-import-category-list {
max-width: 420px;
max-height: 180px;
overflow-y: auto;
border: 1px solid #ddd;
border-radius: 4px;
background: #fff;
padding: 8px 10px;
}
.advnews-import-category-option {
display: block;
margin: 0 0 8px;
cursor: pointer;
}
.advnews-import-category-option:last-child {
margin-bottom: 0;
}
</style>
