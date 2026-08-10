param(
    [Parameter(Mandatory = $true)]
    [string] $RepoUrl
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

