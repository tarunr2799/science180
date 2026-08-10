# GitHub And Staging Commands

This file records the commands for pushing this WordPress repo to GitHub and pulling it on cPanel staging.

## Repository

Recommended private repository:

```text
Koploseus/science180
```

If `science180` already exists, use:

```text
Koploseus/science180-staging
```

## Local Push From Windows

From PowerShell:

```powershell
cd C:\Users\lenovo\Downloads\Science
.\scripts\push-to-github.ps1 -RepoUrl "https://github.com/Koploseus/science180.git"
```

Equivalent manual commands:

```powershell
cd C:\Users\lenovo\Downloads\Science
git remote add origin https://github.com/Koploseus/science180.git
git push -u origin main
```

If `origin` already exists:

```powershell
git remote set-url origin https://github.com/Koploseus/science180.git
git push -u origin main
```

## cPanel Git Clone

In cPanel **Git Version Control**, clone:

```text
https://github.com/Koploseus/science180.git
```

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

