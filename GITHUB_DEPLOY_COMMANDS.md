# GitHub And Staging Commands

This file records the commands for pushing this WordPress repo to GitHub and pulling it on cPanel staging.

## Repository

Private repository created:

```text
tarunr2799/science180
```

## Local Push From Windows

From PowerShell:

```powershell
cd C:\Users\lenovo\Downloads\Science
.\scripts\push-to-github.ps1
```

Equivalent manual commands:

```powershell
cd C:\Users\lenovo\Downloads\Science
git remote add origin https://github.com/tarunr2799/science180.git
git push -u origin main
```

If `origin` already exists:

```powershell
git remote set-url origin https://github.com/tarunr2799/science180.git
git push -u origin main
```

## cPanel Git Clone

In cPanel **Git Version Control**, clone:

```text
https://github.com/tarunr2799/science180.git
```

Because this is a private repository, cPanel needs GitHub authentication before it can clone or pull. Recommended options:

- SSH deploy key: create an SSH key in cPanel and add its public key to GitHub repository deploy keys.
- HTTPS token: use a GitHub fine-grained token with repository read access.

Suggested repository path:

```text
/home-1tb/amen123jesus/repositories/science180
```

Deployment target:

```text
/home-1tb/amen123jesus/public_html
```

## Staging Pull And Deploy

If Terminal/SSH is available on cPanel:

```bash
cd /home-1tb/amen123jesus/repositories/science180
git pull --ff-only origin main
/bin/bash deploy/deploy.sh /home-1tb/amen123jesus/public_html/
```

Or run the saved helper:

```bash
cd /home-1tb/amen123jesus/repositories/science180
/bin/bash deploy/staging-pull-deploy.sh
```

## cPanel Auto Deploy

The `.cpanel.yml` file is already configured:

```yaml
deployment:
  tasks:
    - export DEPLOYPATH=/home-1tb/amen123jesus/public_html/
    - /bin/bash deploy/deploy.sh "$DEPLOYPATH"
```

When cPanel Git deployment is enabled, pushing or pulling the `main` branch can deploy `wp-content` into `public_html/wp-content`.

## Important

Do not push these files:

```text
wp-config.php
wp_dlw2o.sql
wp-content/uploads/
wp-admin/
wp-includes/
```

They are intentionally ignored by Git.
