# Science180 WordPress Workflow

This folder is a full WordPress copy. For Git, keep the repository focused on code and deployment configuration, not uploads, backups, cache, database dumps, or server credentials.

## Local URL

Default local URL in `wp-config.php`:

```text
http://localhost/Science
```

Override it on Windows if needed:

```powershell
$env:WP_LOCAL_URL = "http://localhost/science180"
```

## Staging URL

The SQL dump now has these `pKXuq_options` values:

```text
siteurl = https://happy-tan-hippopotamus.138-201-81-245.cpanel.site
home    = https://happy-tan-hippopotamus.138-201-81-245.cpanel.site
```

The original SQL dump is preserved as:

```text
wp_dlw2o.sql.bak
```

## Git Setup

```powershell
cd C:\Users\lenovo\Downloads\Science
git init
git add .gitignore .gitattributes .cpanel.yml deploy PROJECT_WORKFLOW.md wp-content
git commit -m "Initial Science180 WordPress workflow"
git branch -M main
git remote add origin https://github.com/tarunr2799/science180.git
git push -u origin main
```

Before committing, confirm that `wp-config.php`, SQL dumps, uploads, backups, `wp-admin`, and `wp-includes` are not staged:

```powershell
git status --short
```

## cPanel Deployment

The cPanel deployment target is configured in `.cpanel.yml`:

```text
/home-1tb/amen123jesus/public_html/
```

The deployment script syncs:

```text
wp-content -> public_html/wp-content
```

and skips generated folders such as uploads, cache, backups, and logs.

It also skips host-managed Toolkit/maintenance files and the inactive `wp-file-manager` plugin folder. Remove that exclusion only if you intentionally want to version and deploy that plugin.

After creating the cPanel Git repository, push the deployment branch:

```powershell
git remote add cpanel ssh://CPANEL_USER@CPANEL_HOST:22/home-1tb/amen123jesus/repositories/science180
git push cpanel main
```

Use the exact remote URL shown by cPanel if it differs.

## Database Migration

Prefer WP-CLI for full serialized URL replacement after importing the DB:

```bash
wp search-replace "https://science180.net" "https://happy-tan-hippopotamus.138-201-81-245.cpanel.site" --skip-columns=guid --dry-run
wp search-replace "https://science180.net" "https://happy-tan-hippopotamus.138-201-81-245.cpanel.site" --skip-columns=guid
```

The current SQL file was changed only for the `siteurl` and `home` rows because those were the requested values.
