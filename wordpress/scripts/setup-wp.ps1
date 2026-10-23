<#
.SYNOPSIS
    Idempotent provisioning script for the NovaTech Solutions WordPress environment (Stage 1).
.DESCRIPTION
    Starts Docker containers, initializes WordPress 6.8.2 (en-US), installs ACF Free and Contact Form 7,
    activates the ai-hub-theme child theme, configures pretty permalinks, and seeds all ACF content,
    Media Library illustrations, and Contact Form 7 configuration.
#>

$ErrorActionPreference = "Continue"
$WpDir = Split-Path -Parent $PSScriptRoot

function Invoke-WpCli {
    param(
        [Parameter(Mandatory = $true)]
        [AllowEmptyString()]
        [string[]]$WpArgs,
        [switch]$IgnoreError
    )
    & docker compose --profile cli run --rm -T --no-deps wpcli wp @WpArgs --path=/var/www/html --url=http://localhost:8001
    if (-not $IgnoreError -and $LASTEXITCODE -ne 0) {
        throw "WP-CLI command failed with exit code $LASTEXITCODE : wp $($WpArgs -join ' ')"
    }
}

Push-Location $WpDir
try {
    if (-not (Test-Path ".env")) {
        Write-Host "[setup] Creating .env from .env.example..." -ForegroundColor Cyan
        Copy-Item ".env.example" ".env"
    }

    $envVars = @{}
    Get-Content ".env" | ForEach-Object {
        if ($_ -match '^\s*([^#][^=]+)=(.*)$') {
            $key = $matches[1].Trim()
            $val = $matches[2].Trim().Trim('"')
            $envVars[$key] = $val
        }
    }

    $siteUrl     = if ($envVars["WP_SITE_URL"]) { $envVars["WP_SITE_URL"] } else { "http://localhost:8001" }
    $siteTitle   = if ($envVars["WP_SITE_TITLE"]) { $envVars["WP_SITE_TITLE"] } else { "NovaTech Solutions" }
    $adminUser   = if ($envVars["WP_ADMIN_USER"]) { $envVars["WP_ADMIN_USER"] } else { "lab_admin" }
    $adminPass   = if ($envVars["WP_ADMIN_PASSWORD"]) { $envVars["WP_ADMIN_PASSWORD"] } else { "CHANGE_ME_LOCAL_DEV_ONLY" }
    $adminEmail  = if ($envVars["WP_ADMIN_EMAIL"]) { $envVars["WP_ADMIN_EMAIL"] } else { "admin@novatech.local" }

    Write-Host "[1/6] Starting Docker containers (db, wordpress, mailpit)..." -ForegroundColor Cyan
    & docker compose up -d db wordpress mailpit
    if ($LASTEXITCODE -ne 0) {
        throw "docker compose up failed with exit code $LASTEXITCODE"
    }

    Write-Host "[2/6] Waiting for WordPress container and database readiness..." -ForegroundColor Cyan
    $maxAttempts = 30
    $attempt = 0
    while ($attempt -lt $maxAttempts) {
        $attempt++
        & docker compose --profile cli run --rm -T --no-deps wpcli wp db check --path=/var/www/html
        if ($LASTEXITCODE -eq 0) {
            break
        }
        Start-Sleep -Seconds 2
    }

    Write-Host "[3/6] Ensuring WordPress Core is installed (en_US)..." -ForegroundColor Cyan
    & docker compose --profile cli run --rm -T --no-deps wpcli wp core is-installed --path=/var/www/html --url=http://localhost:8001
    if ($LASTEXITCODE -ne 0) {
        Invoke-WpCli -WpArgs @(
            "core", "install",
            "--url=$siteUrl",
            "--title=$siteTitle",
            "--admin_user=$adminUser",
            "--admin_password=$adminPass",
            "--admin_email=$adminEmail",
            "--skip-email"
        )
    }
    Invoke-WpCli -WpArgs @("option", "update", "blogname", "NovaTech Solutions")
    Invoke-WpCli -WpArgs @("option", "update", "blogdescription", "Enterprise Cloud, Data & Platform Engineering")

    Write-Host "[4/6] Installing and activating ACF Free and Contact Form 7..." -ForegroundColor Cyan
    Invoke-WpCli -WpArgs @("plugin", "install", "advanced-custom-fields", "--version=6.4.3", "--activate")
    Invoke-WpCli -WpArgs @("plugin", "install", "contact-form-7", "--version=6.0.6", "--activate")

    Write-Host "[5/6] Activating ai-hub-theme and configuring permalinks..." -ForegroundColor Cyan
    Invoke-WpCli -WpArgs @("theme", "activate", "ai-hub-theme")
    Invoke-WpCli -WpArgs @("rewrite", "structure", "/%postname%/", "--hard")
    Invoke-WpCli -WpArgs @("rewrite", "flush", "--hard")

    Write-Host "[6/6] Running idempotent NovaTech Solutions seed script..." -ForegroundColor Cyan
    Invoke-WpCli -WpArgs @("eval-file", "/var/www/html/wp-content/scripts/seed-novatech.php")

    Write-Host "`n[DONE] NovaTech Solutions WordPress environment is ready!" -ForegroundColor Green
    Write-Host "  Landing Page : $siteUrl/novatech-solutions/"
    Write-Host "  WP Admin     : $siteUrl/wp-admin/"
    Write-Host "  Mailpit UI   : http://127.0.0.1:8025"
} finally {
    Pop-Location
}
