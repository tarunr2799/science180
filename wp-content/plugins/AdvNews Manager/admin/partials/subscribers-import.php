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
<div class="advnews-multiselect" data-placeholder="<?php esc_attr_e('Select categories', 'advnews-manager'); ?>" data-selected-singular="<?php esc_attr_e('category selected', 'advnews-manager'); ?>" data-selected-plural="<?php esc_attr_e('categories selected', 'advnews-manager'); ?>">
<button type="button" id="default_category" class="advnews-multiselect-toggle" aria-haspopup="listbox" aria-expanded="false">
<span class="advnews-multiselect-label"><?php _e('Select categories', 'advnews-manager'); ?></span>
<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
</button>
<div class="advnews-multiselect-menu" role="listbox" aria-multiselectable="true">
<?php if (empty($categories)): ?>
<p><?php _e('No categories found.', 'advnews-manager'); ?></p>
<?php else: ?>
<?php
foreach ($categories as $category):
?>
<label class="advnews-multiselect-option">
<input type="checkbox" name="default_category[]" value="<?php echo esc_attr($category->id); ?>">
<span class="advnews-multiselect-check" aria-hidden="true"></span>
<span class="advnews-multiselect-text">
<?php echo esc_html($category->name); ?>
</span>
</label>
<?php endforeach; ?>
<?php endif; ?>
</div>
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
function updateAdvNewsMultiSelect($select) {
var checked = $select.find('input[type="checkbox"]:checked:not(:disabled)');
var label = $select.find('.advnews-multiselect-label');
var placeholder = $select.data('placeholder') || '';
var plural = $select.data('selected-plural') || 'selected';

if (checked.length === 0) {
label.text(placeholder);
return;
}

if (checked.length === 1) {
label.text($.trim(checked.closest('.advnews-multiselect-option').find('.advnews-multiselect-text').first().text()));
return;
}

label.text(checked.length + ' ' + plural);
}

$('.advnews-multiselect').each(function() {
updateAdvNewsMultiSelect($(this));
});

$(document).on('click', '.advnews-multiselect-toggle', function(e) {
e.preventDefault();
var $select = $(this).closest('.advnews-multiselect');
$('.advnews-multiselect').not($select).removeClass('is-open').find('.advnews-multiselect-toggle').attr('aria-expanded', 'false');
$select.toggleClass('is-open');
$(this).attr('aria-expanded', $select.hasClass('is-open') ? 'true' : 'false');
});

$(document).on('change', '.advnews-multiselect input[type="checkbox"]', function() {
updateAdvNewsMultiSelect($(this).closest('.advnews-multiselect'));
});

$(document).on('click', function(e) {
if (!$(e.target).closest('.advnews-multiselect').length) {
$('.advnews-multiselect').removeClass('is-open').find('.advnews-multiselect-toggle').attr('aria-expanded', 'false');
}
});

$(document).on('keydown', function(e) {
if (e.key === 'Escape') {
$('.advnews-multiselect').removeClass('is-open').find('.advnews-multiselect-toggle').attr('aria-expanded', 'false');
}
});

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
setTimeout(function() {
$('.advnews-multiselect').each(function() {
updateAdvNewsMultiSelect($(this));
});
}, 0);
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
.advnews-multiselect {
position: relative;
max-width: 420px;
}
.advnews-multiselect-toggle {
width: 100%;
min-height: 36px;
display: flex;
align-items: center;
justify-content: space-between;
gap: 10px;
padding: 0 10px;
border: 1px solid #8c8f94;
border-radius: 4px;
background: #fff;
color: #2c3338;
cursor: pointer;
text-align: left;
}
.advnews-multiselect-toggle:focus {
border-color: #2271b1;
box-shadow: 0 0 0 1px #2271b1;
outline: 2px solid transparent;
}
.advnews-multiselect-label {
overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
}
.advnews-multiselect-menu {
display: none;
position: absolute;
z-index: 1000;
top: calc(100% + 4px);
left: 0;
right: 0;
max-height: 220px;
overflow-y: auto;
padding: 6px;
border: 1px solid #8c8f94;
border-radius: 4px;
background: #fff;
box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
}
.advnews-multiselect.is-open .advnews-multiselect-menu {
display: block;
}
.advnews-multiselect-option {
display: flex;
align-items: center;
gap: 8px;
min-height: 30px;
padding: 5px 6px;
border-radius: 3px;
cursor: pointer;
}
.advnews-multiselect-option:hover {
background: #f0f6fc;
}
.advnews-multiselect-option input {
position: absolute;
opacity: 0;
pointer-events: none;
}
.advnews-multiselect-check {
width: 16px;
height: 16px;
border: 1px solid #8c8f94;
border-radius: 3px;
background: #fff;
box-sizing: border-box;
}
.advnews-multiselect-option input:checked + .advnews-multiselect-check {
border-color: #2271b1;
background: #2271b1;
}
.advnews-multiselect-option input:checked + .advnews-multiselect-check::after {
content: "";
display: block;
width: 4px;
height: 8px;
margin: 1px 0 0 5px;
border: solid #fff;
border-width: 0 2px 2px 0;
transform: rotate(45deg);
}
.advnews-multiselect-text {
line-height: 1.3;
}
</style>
