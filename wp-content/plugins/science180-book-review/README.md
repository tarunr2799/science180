# Science180 Book Review

This plugin contains the Science180 book review copy request workflow.

## Public Shortcode

- `[science180_review_request]`

On activation, the plugin creates `/review-copy-request/` if it does not already exist.

## Features

- Single book selection with cover preview and automatic scroll to the request form.
- Duplicate email/book blocking.
- Review request database storage.
- Admin book management and review request status management.
- Admin notification email for new requests.
- Reviewer notification emails when a request status is updated.

## Email

The plugin sends through WordPress `wp_mail()` and sets `Reply-To` to the applicant on review copy request emails. If AdvNews Manager SMTP is active and configured, its SMTP hook carries these messages through the existing SMTP setup. This plugin does not store SMTP passwords.
