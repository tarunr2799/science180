# Science180 Review Requests and Endorsements

This plugin implements the two workflows requested in the uploaded task document:

- Review copy request page with single book selection, cover preview, duplicate email/book blocking, database storage, admin notification email, and a copy-ready mailing address at the top of the email.
- Endorsement form with email verification, daily admin review notice, approve/reject moderation, public endorsement listing, and individual public endorsement pages.

## Public Shortcodes

- `[science180_review_request]`
- `[science180_endorsement_form]`
- `[science180_endorsements]`

On activation, the plugin creates two pages if they do not already exist:

- `/review-copy-request/`
- `/endorsement/`

## Email

The plugin sends through WordPress `wp_mail()` and sets `Reply-To` to the applicant on review-copy-request emails. If AdvNews Manager SMTP is active and configured, its `phpmailer_init` SMTP hook will carry these messages through the existing SMTP setup. This plugin does not store SMTP passwords.
