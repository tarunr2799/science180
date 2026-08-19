# Science180 Book Review

This plugin contains the Science180 book review copy request workflow.

## Public Shortcode

- `[science180_review_request]`

On activation, the plugin creates `/BookReviewRequest/` if it does not already exist.

## Features

- Single book selection with larger cover preview, book-specific public URLs, and automatic scroll to the request form.
- Duplicate email/book blocking.
- Review request database storage only after the applicant clicks the verification email link.
- Admin book management, request filtering, request deletion, and review request status management.
- Daily admin notice for verified requests waiting for review.
- Reviewer notification emails when a request status is updated.

## Email

The plugin sends through WordPress `wp_mail()` and sets `Reply-To` to the applicant on review copy request emails. If Science180 Mail SMTP is active and configured, its SMTP hook carries these messages through the existing SMTP setup. This plugin does not store SMTP passwords.
