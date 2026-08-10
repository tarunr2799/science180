param(
    [string] $RepoUrl = "https://github.com/tarunr2799/science180.git"
)

$ErrorActionPreference = "Stop"

Set-Location -Path (Split-Path -Parent $PSScriptRoot)

if (-not (git remote | Select-String -SimpleMatch "origin")) {
    git remote add origin $RepoUrl
} else {
    git remote set-url origin $RepoUrl
}

git status --short --branch
git push -u origin main
