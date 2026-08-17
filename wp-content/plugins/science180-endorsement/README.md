# Science180 Endorsement

This plugin contains the Science180 public endorsement workflow.

## Public Shortcodes

- `[science180_endorsement_form]`
- `[science180_endorsements]`

On activation, the plugin creates `/endorsement/` if it does not already exist.

## Features

- Public endorsement form with email, name, country, organization, comment, and optional photo.
- Email verification link before the endorsement is saved for admin review.
- Daily admin notice cron for verified endorsements that need review.
- Approve, reject, delete, filter, bulk-select, and bulk-action admin tools.
- Public approved endorsement listing and individual endorsement detail pages.
- Submitter notification emails when an endorsement is approved or rejected.

## Email

The plugin sends through WordPress `wp_mail()`. If AdvNews Manager SMTP is active and configured, its SMTP hook carries these messages through the existing SMTP setup. This plugin does not store SMTP passwords.
