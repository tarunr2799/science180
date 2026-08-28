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
- Per-book private PDF selection plus configurable margin message, color, and top/left/right placement.
- Admin delivery of personalized protected PDFs or unchanged originals from a reviewer's request page.
- Personalized name/email footer on every page, flattened into the generated page content.
- Unique one-time download links; unused older links are revoked when a replacement is sent.
- Email-open and download analytics including timestamp, IP, location, device type, and user agent when available.
- Per-book delivery report with links back to each recipient's application.
- Configurable automatic review reminders after a chosen number of days.
- Hourly pending-verification cron with one configurable reminder and 48-hour verification links.
- Submission telemetry capture with automatic city/country/device backfill when data becomes available.

## Email

The plugin sends through WordPress `wp_mail()` and sets `Reply-To` to the applicant on review copy request emails. If Science180 Mail SMTP is active and configured, its SMTP hook carries these messages through the existing SMTP setup. This plugin does not store SMTP passwords.

## PDF protection

Generated personalized files are served only through tokenized download routes from a deny-listed private uploads directory. They are encrypted with printing allowed and copying/modification disabled in compliant PDF readers. The visible recipient footer and margin message are flattened into each page. PDF permissions and watermarks are deterrents; no PDF format can prevent a determined recipient from taking screenshots or reconstructing pages.

This plugin bundles the MIT-licensed Setasign FPDF, FPDI, and FPDI Protection libraries under `vendor/setasign/`.
