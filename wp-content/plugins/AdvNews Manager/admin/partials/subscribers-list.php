<?php
// admin/partials/subscribers-list.php
if (!defined('ABSPATH')) exit;
$subscriber_class = new AdvNews_Subscriber();
$category_class = new AdvNews_Category();
// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['subscriber_ids'])) {
$this->handle_bulk_subscriber_actions();
}
// Get filter parameters
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$allowed_per_page = array(20, 50, 100, 200);
$limit = isset($_GET['per_page']) ? intval($_GET['per_page']) : intval(get_user_meta(get_current_user_id(), 'advnews_subscribers_per_page', true));
if (!in_array($limit, $allowed_per_page, true)) {
    $limit = 20;
}
update_user_meta(get_current_user_id(), 'advnews_subscribers_per_page', $limit);
$args = array(
'status' => $status,
'category_id' => $category_id,
'search' => $search,
'limit' => $limit,
'offset' => ($paged - 1) * $limit
);
$subscribers = $subscriber_class->get_all_subscribers($args);
$total = $subscriber_class->count_subscribers($args);
// Get categories for filter
$categories = $category_class->get_all_categories();
// Get total counts for summary
$total_active = $subscriber_class->count_subscribers(array('status' => 'active'));
$total_unsubscribed = $subscriber_class->count_subscribers(array('status' => 'unsubscribed'));
$total_bounced = $subscriber_class->count_subscribers(array('status' => 'bounced'));
?>
<div class="wrap">
<h1 class="wp-heading-inline"><?php _e('Subscribers', 'advnews-manager'); ?></h1>
<a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=add'); ?>" class="page-title-action">
<?php _e('Add New', 'advnews-manager'); ?>
</a>
<a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=import'); ?>" class="page-title-action">
<?php _e('Import', 'advnews-manager'); ?>
</a>
<a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=export'); ?>" class="page-title-action">
<?php _e('Export', 'advnews-manager'); ?>
</a>
<hr class="wp-header-end">
<?php if (isset($_GET['message'])): ?>
<?php
$message_key = sanitize_text_field(wp_unslash($_GET['message']));
$messages = array(
    'created' => __('Subscriber created successfully.', 'advnews-manager'),
    'updated' => __('Subscriber updated successfully.', 'advnews-manager'),
    'cooldown_reset' => __('Subscriber cooldown reset successfully.', 'advnews-manager'),
    'error' => __('Subscriber could not be saved.', 'advnews-manager')
);
$notice_class = $message_key === 'error' ? 'notice-error' : 'notice-success';
?>
<?php if (isset($messages[$message_key])): ?>
<div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible">
<p><?php echo esc_html($messages[$message_key]); ?></p>
</div>
<?php endif; ?>
<?php endif; ?>
<!-- Summary Cards -->
<div class="advnews-summary-cards" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
<div class="summary-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px; text-align: center;">
<div style="font-size: 28px; font-weight: 600; color: #2271b1;"><?php echo esc_html(number_format($total)); ?></div>
<div style="color: #646970;"><?php _e('Total', 'advnews-manager'); ?></div>
</div>
<div class="summary-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px; text-align: center;">
<div style="font-size: 28px; font-weight: 600; color: #00a32a;"><?php echo esc_html(number_format($total_active)); ?></div>
<div style="color: #646970;"><?php _e('Active', 'advnews-manager'); ?></div>
</div>
<div class="summary-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px; text-align: center;">
<div style="font-size: 28px; font-weight: 600; color: #d63638;"><?php echo esc_html(number_format($total_unsubscribed)); ?></div>
<div style="color: #646970;"><?php _e('Unsubscribed', 'advnews-manager'); ?></div>
</div>
<div class="summary-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px; text-align: center;">
<div style="font-size: 28px; font-weight: 600; color: #f0c33c;"><?php echo esc_html(number_format($total_bounced)); ?></div>
<div style="color: #646970;"><?php _e('Bounced', 'advnews-manager'); ?></div>
</div>
</div>
<!-- Filters -->
<div class="advnews-filters" style="background: #f6f7f7; border: 1px solid #c3c4c7; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
<form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
<input type="hidden" name="page" value="advnews-subscribers">
<select name="status" style="height: 35px;">
<option value=""><?php _e('All Statuses', 'advnews-manager'); ?></option>
<option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'advnews-manager'); ?></option>
<option value="unsubscribed" <?php selected($status, 'unsubscribed'); ?>><?php _e('Unsubscribed', 'advnews-manager'); ?></option>
<option value="bounced" <?php selected($status, 'bounced'); ?>><?php _e('Bounced', 'advnews-manager'); ?></option>
</select>
<select name="category_id" style="height: 35px;">
<option value=""><?php _e('All Categories', 'advnews-manager'); ?></option>
<?php foreach ($categories as $category): ?>
<option value="<?php echo esc_attr($category->id); ?>" <?php selected($category_id, $category->id); ?>>
<?php echo esc_html($category->name); ?>
</option>
<?php endforeach; ?>
</select>
<input type="text" name="s" placeholder="<?php _e('Search by email, name, or organization', 'advnews-manager'); ?>"
value="<?php echo esc_attr($search); ?>" style="height: 35px; min-width: 250px;">
<select name="per_page" style="height: 35px;">
<?php foreach ($allowed_per_page as $per_page_option): ?>
<option value="<?php echo esc_attr($per_page_option); ?>" <?php selected($limit, $per_page_option); ?>>
<?php echo esc_html(sprintf(__('%d per page', 'advnews-manager'), $per_page_option)); ?>
</option>
<?php endforeach; ?>
</select>
<input type="submit" class="button" value="<?php _e('Filter', 'advnews-manager'); ?>">
<?php if ($status || $category_id || $search): ?>
<a href="<?php echo admin_url('admin.php?page=advnews-subscribers'); ?>" class="button">
<?php _e('Clear Filters', 'advnews-manager'); ?>
</a>
<?php endif; ?>
</form>
</div>
<!-- Bulk Actions Form -->
<form method="post" action="">
<?php wp_nonce_field('advnews_bulk_subscribers'); ?>
<div class="tablenav top">
<div class="alignleft actions bulkactions">
<select name="bulk_action">
<option value=""><?php _e('Bulk Actions', 'advnews-manager'); ?></option>
<option value="delete"><?php _e('Delete (Anonymize)', 'advnews-manager'); ?></option>
<option value="unsubscribe"><?php _e('Unsubscribe', 'advnews-manager'); ?></option>
<option value="activate"><?php _e('Activate', 'advnews-manager'); ?></option>
<option value="export"><?php _e('Export Selected', 'advnews-manager'); ?></option>
</select>
<input type="submit" class="button action" value="<?php _e('Apply', 'advnews-manager'); ?>">
</div>
<div class="tablenav-pages">
<?php
$total_pages = ceil($total / $limit);
if ($total_pages > 1) {
echo '<span class="displaying-num">' . sprintf(__('%s items', 'advnews-manager'), number_format($total)) . '</span>';
echo paginate_links(array(
'base' => add_query_arg('paged', '%#%'),
'format' => '',
'prev_text' => __('&laquo;'),
'next_text' => __('&raquo;'),
'total' => $total_pages,
'current' => $paged
));
}
?>
</div>
</div>
<!-- Subscribers Table -->
<table class="wp-list-table widefat fixed striped">
<thead>
<tr>
<td class="manage-column column-cb check-column">
<input type="checkbox" id="cb-select-all-1">
</td>
<th><?php _e('Email', 'advnews-manager'); ?></th>
<th><?php _e('Name', 'advnews-manager'); ?></th>
<th><?php _e('Organization', 'advnews-manager'); ?></th>
<th><?php _e('Categories', 'advnews-manager'); ?></th>
<th><?php _e('Status', 'advnews-manager'); ?></th>
<th><?php _e('Open Rate', 'advnews-manager'); ?></th>
<th><?php _e('Last Activity', 'advnews-manager'); ?></th>
<th><?php _e('Actions', 'advnews-manager'); ?></th>
</tr>
</thead>
<tbody>
<?php if (empty($subscribers)): ?>
<tr>
<td colspan="9">
<?php _e('No subscribers found.', 'advnews-manager'); ?>
<?php if ($search || $status || $category_id): ?>
<a href="<?php echo admin_url('admin.php?page=advnews-subscribers'); ?>"><?php _e('Clear filters', 'advnews-manager'); ?></a>
<?php endif; ?>
</td>
</tr>
<?php else: ?>
<?php foreach ($subscribers as $subscriber): ?>
<?php
$categories = $subscriber_class->get_subscriber_categories($subscriber->id);
$category_badges = array();
foreach ($categories as $cat) {
$category_badges[] = '<span class="category-badge" style="background-color: ' . esc_attr($cat->color) . '; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin: 2px; display: inline-block;">' . esc_html($cat->name) . '</span>';
}
$last_activity = $subscriber->last_activity_at ?
human_time_diff(strtotime($subscriber->last_activity_at), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager') :
__('Never', 'advnews-manager');
?>
<tr>
<th scope="row" class="check-column">
<input type="checkbox" name="subscriber_ids[]" value="<?php echo esc_attr($subscriber->id); ?>">
</th>
<td>
<strong>
<a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=edit&id=' . $subscriber->id); ?>">
<?php echo esc_html($subscriber->email); ?>
</a>
</strong>
<?php if ($subscriber->email_verified): ?>
<span class="dashicons dashicons-yes" style="color: #00a32a; font-size: 14px; width: 14px; height: 14px;" title="<?php _e('Verified', 'advnews-manager'); ?>"></span>
<?php endif; ?>
</td>
<td><?php echo esc_html(trim($subscriber->first_name . ' ' . $subscriber->last_name)); ?></td>
<td><?php echo esc_html($subscriber->organization); ?></td>
<td>
<?php if (!empty($category_badges)): ?>
<?php echo implode(' ', $category_badges); ?>
<?php else: ?>
<span style="color: #999;">—</span>
<?php endif; ?>
</td>
<td>
<span class="subscriber-status status-<?php echo esc_attr($subscriber->status); ?>"
style="display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;
<?php echo $subscriber->status == 'active' ? 'background: #d4edda; color: #155724;' :
($subscriber->status == 'unsubscribed' ? 'background: #f8d7da; color: #721c24;' :
'background: #fff3cd; color: #856404;'); ?>">
<?php echo esc_html(ucfirst($subscriber->status)); ?>
</span>
</td>
<td><?php echo esc_html($subscriber->open_rate); ?>%</td>
<td><?php echo esc_html($last_activity); ?></td>
<td>
<div class="row-actions">
    <a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=edit&id=' . $subscriber->id); ?>">
        <?php _e('Edit', 'advnews-manager'); ?>
    </a> |
    <a href="<?php echo admin_url('admin.php?page=advnews-subscribers&action=view&id=' . $subscriber->id); ?>">
        <?php _e('View', 'advnews-manager'); ?>
    </a> |
    <?php if ($subscriber->status === 'active'): ?>
    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-subscribers&action=unsubscribe&id=' . $subscriber->id), 'advnews_unsubscribe_subscriber'); ?>"
       class="unsubscribe-link"
       onclick="return confirm('<?php _e('Are you sure you want to unsubscribe this subscriber?', 'advnews-manager'); ?>');">
        <?php _e('Unsubscribe', 'advnews-manager'); ?>
    </a> |
    <?php elseif ($subscriber->status === 'unsubscribed'): ?>
    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-subscribers&action=resubscribe&id=' . $subscriber->id), 'advnews_resubscribe_subscriber'); ?>"
       class="resubscribe-link">
        <?php _e('Resubscribe', 'advnews-manager'); ?>
    </a> |
    <?php endif; ?>

    <!-- ✅ RESET COOLDOWN LINK -->
    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-subscribers&action=reset_cooldown&id=' . $subscriber->id), 'advnews_reset_cooldown_subscriber'); ?>"
       class="reset-cooldown-link"
       onclick="return confirm('<?php _e('Reset the cooldown delay for this subscriber? They will be eligible to receive emails immediately.', 'advnews-manager'); ?>');"
       title="<?php _e('Bypass cooldown delay for testing', 'advnews-manager'); ?>">
        <?php _e('Reset Cooldown', 'advnews-manager'); ?>
    </a> |

    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-subscribers&action=delete&id=' . $subscriber->id), 'advnews_delete_subscriber'); ?>"
       class="delete-link"
       onclick="return confirm('<?php _e('Are you sure? This will anonymize the subscriber data.', 'advnews-manager'); ?>');">
        <?php _e('Delete', 'advnews-manager'); ?>
    </a>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
<tfoot>
<tr>
<td class="manage-column column-cb check-column">
<input type="checkbox" id="cb-select-all-2">
</td>
<th><?php _e('Email', 'advnews-manager'); ?></th>
<th><?php _e('Name', 'advnews-manager'); ?></th>
<th><?php _e('Organization', 'advnews-manager'); ?></th>
<th><?php _e('Categories', 'advnews-manager'); ?></th>
<th><?php _e('Status', 'advnews-manager'); ?></th>
<th><?php _e('Open Rate', 'advnews-manager'); ?></th>
<th><?php _e('Last Activity', 'advnews-manager'); ?></th>
<th><?php _e('Actions', 'advnews-manager'); ?></th>
</tr>
</tfoot>
</table>
<div class="tablenav bottom">
<div class="alignleft actions bulkactions">
<select name="bulk_action2">
<option value=""><?php _e('Bulk Actions', 'advnews-manager'); ?></option>
<option value="delete"><?php _e('Delete (Anonymize)', 'advnews-manager'); ?></option>
<option value="unsubscribe"><?php _e('Unsubscribe', 'advnews-manager'); ?></option>
<option value="activate"><?php _e('Activate', 'advnews-manager'); ?></option>
<option value="export"><?php _e('Export Selected', 'advnews-manager'); ?></option>
</select>
<input type="submit" class="button action" value="<?php _e('Apply', 'advnews-manager'); ?>">
</div>
<div class="tablenav-pages">
<?php
if ($total_pages > 1) {
echo '<span class="displaying-num">' . sprintf(__('%s items', 'advnews-manager'), number_format($total)) . '</span>';
echo paginate_links(array(
'base' => add_query_arg('paged', '%#%'),
'format' => '',
'prev_text' => __('&laquo;'),
'next_text' => __('&raquo;'),
'total' => $total_pages,
'current' => $paged
));
}
?>
</div>
</div>
</form>
</div>
<style>
.category-badge {
display: inline-block;
padding: 2px 8px;
border-radius: 3px;
font-size: 11px;
margin: 2px;
color: #fff;
}
.row-actions {
color: #ddd;
font-size: 12px;
}
.row-actions a {
text-decoration: none;
}
.row-actions a:hover {
text-decoration: underline;
}
.delete-link {
color: #d63638;
}
.delete-link:hover {
color: #b32d2e;
}
.unsubscribe-link {
color: #d63638;
}
.resubscribe-link {
color: #00a32a;
}
.subscriber-status {
display: inline-block;
padding: 3px 8px;
border-radius: 3px;
font-size: 11px;
font-weight: 600;
}
.tablenav-pages,
.tablenav-pages .page-numbers {
font-size: 16px;
line-height: 1.7;
}
.tablenav-pages .page-numbers {
min-width: 34px;
min-height: 34px;
display: inline-flex;
align-items: center;
justify-content: center;
}

/* ✅ NEW: Reset Cooldown Link Styles */
.reset-cooldown-link {
    color: #f0c33c !important; /* Distinct yellow/orange color */
    font-weight: 600;
}
.reset-cooldown-link:hover {
    color: #d63638 !important;
    text-decoration: underline;
}

@media screen and (max-width: 782px) {
.advnews-summary-cards {
grid-template-columns: repeat(2, 1fr) !important;
}
.advnews-filters form {
flex-direction: column;
align-items: stretch;
}
.advnews-filters select,
.advnews-filters input[type="text"] {
width: 100%;
}
}
</style>
<script>
jQuery(document).ready(function($) {
// Select all checkboxes
$('#cb-select-all-1, #cb-select-all-2').on('click', function() {
var checkboxes = $(this).closest('table').find('tbody input[type="checkbox"]');
checkboxes.prop('checked', $(this).is(':checked'));
});
// Bulk action confirmation
$('.bulkactions .action').on('click', function(e) {
var selectedAction = $(this).closest('.bulkactions').find('select').val();
if (!selectedAction) {
alert('<?php _e('Please select an action.', 'advnews-manager'); ?>');
e.preventDefault();
return false;
}
var selectedItems = $(this).closest('form').find('input[type="checkbox"]:checked').length;
if (selectedItems === 0) {
alert('<?php _e('Please select at least one subscriber.', 'advnews-manager'); ?>');
e.preventDefault();
return false;
}
var confirmMessage = '';
switch(selectedAction) {
case 'delete':
confirmMessage = '<?php _e('Are you sure you want to delete the selected subscribers? This will anonymize their data.', 'advnews-manager'); ?>';
break;
case 'unsubscribe':
confirmMessage = '<?php _e('Are you sure you want to unsubscribe the selected subscribers?', 'advnews-manager'); ?>';
break;
case 'activate':
confirmMessage = '<?php _e('Are you sure you want to activate the selected subscribers?', 'advnews-manager'); ?>';
break;
default:
return true;
}
if (!confirm(confirmMessage)) {
e.preventDefault();
return false;
}
return true;
});
});
</script>
