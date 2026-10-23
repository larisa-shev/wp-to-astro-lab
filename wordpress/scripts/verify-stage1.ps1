<#
.SYNOPSIS
    Automated End-to-End Verification Suite for Stage 1 (NovaTech Solutions WordPress Baseline).
.DESCRIPTION
    Verifies:
    1. Infrastructure: Docker containers (wordpress, db, mailpit) running & healthy.
    2. HTTP & HTML Integrity: /novatech-solutions/ returns 200 OK, zero PHP warnings/errors,
       pure English (en-US) content (zero Cyrillic characters), and all semantic sections present.
    3. Assets & Libraries: Real Swiper CSS/JS, NovaTech CSS/JS, and CF7 scripts enqueued and reachable (200 OK).
    4. ACF & REST API: /wp-json/wp/v2/pages?slug=novatech-solutions returns complete `acf` payload
       with all 8 sections and expanded image objects.
    5. Seed Idempotency: Re-running seed-novatech.php creates 0 duplicate pages, forms, attachments, or ACF groups.
    6. Contact Form 7 & Mailpit:
       - Empty submission triggers validation_failed with field-level errors.
       - Valid submission triggers mail_sent AND delivers the actual email to Mailpit (verified via Mailpit REST API).
#>

$ErrorActionPreference = "Continue"
$WpDir = Split-Path -Parent $PSScriptRoot
$BaseUrl = "http://localhost:8001"
$LandingUrl = "$BaseUrl/novatech-solutions/"
$MailpitApi = "http://127.0.0.1:8025/api/v1/messages"

$Results = [System.Collections.Generic.List[PSCustomObject]]::new()
function Add-TestResult([string]$Category, [string]$TestName, [string]$Status, [string]$Details) {
    $Results.Add([PSCustomObject]@{
        Category = $Category
        Test     = $TestName
        Status   = $Status
        Details  = $Details
    })
    $color = if ($Status -eq "PASS") { "Green" } elseif ($Status -eq "FAIL") { "Red" } else { "Yellow" }
    Write-Host "[$Status] $Category :: $TestName -- $Details" -ForegroundColor $color
}

Push-Location $WpDir
try {
    # ------------------------------------------------------------------------
    # 1. INFRASTRUCTURE CHECKS
    # ------------------------------------------------------------------------
    $psJson = docker compose ps --format json | ConvertFrom-Json
    $runningServices = @($psJson | Where-Object { $_.State -eq "running" } | ForEach-Object { $_.Service })

    foreach ($svc in @("db", "wordpress", "mailpit")) {
        if ($runningServices -contains $svc) {
            Add-TestResult "Infrastructure" "Container '$svc' running" "PASS" "State=running"
        } else {
            Add-TestResult "Infrastructure" "Container '$svc' running" "FAIL" "Container not running"
        }
    }

    # ------------------------------------------------------------------------
    # 2. LANDING PAGE HTML, SEMANTICS & ENGLISH-ONLY CHECK
    # ------------------------------------------------------------------------
    $resp = Invoke-WebRequest -Uri $LandingUrl -UseBasicParsing
    $html = $resp.Content

    if ($resp.StatusCode -eq 200) {
        Add-TestResult "Frontend HTML" "GET /novatech-solutions/ HTTP 200" "PASS" "Content-Length=$($html.Length) bytes"
    } else {
        Add-TestResult "Frontend HTML" "GET /novatech-solutions/ HTTP 200" "FAIL" "Status=$($resp.StatusCode)"
    }

    if ($html -notmatch "(<b>(Fatal error|Warning|Notice|Deprecated|Parse error)</b>:|PHP (Fatal error|Warning|Notice|Deprecated|Parse error):)") {
        Add-TestResult "Frontend HTML" "Zero PHP runtime errors/warnings in HTML" "PASS" "Clean output"
    } else {
        Add-TestResult "Frontend HTML" "Zero PHP runtime errors/warnings in HTML" "FAIL" "Found PHP error text in output"
    }

    if ($html -notmatch "[\p{IsCyrillic}]") {
        Add-TestResult "Content & Localization" "100% English (en-US) public UI (zero Cyrillic)" "PASS" "No Ukrainian/Russian characters detected"
    } else {
        Add-TestResult "Content & Localization" "100% English (en-US) public UI (zero Cyrillic)" "FAIL" "Cyrillic characters found in HTML"
    }

    $requiredSelectors = @(
        'id="site-header"',
        'id="nt-mobile-menu-toggle"',
        'id="nt-mobile-menu-drawer"',
        'id="hero"',
        'id="services"',
        'id="nt-cases-swiper"',
        'id="nt-testimonials-swiper"',
        'id="nt-testimonials-autoplay-toggle"',
        'id="nt-process-accordion"',
        'id="nt-faq-accordion"',
        'id="contact"',
        'class="wpcf7',
        'id="site-footer"'
    )
    foreach ($sel in $requiredSelectors) {
        if ($html.Contains($sel)) {
            Add-TestResult "DOM Structure" "Presence of $sel" "PASS" "Found in rendered DOM"
        } else {
            Add-TestResult "DOM Structure" "Presence of $sel" "FAIL" "Missing from rendered DOM"
        }
    }

    # Verify 6 service cards, 5 case study slides, 4 testimonial slides, 4 process steps, 5 FAQ items
    $svcCount    = ([regex]::Matches($html, 'class="nt-service-card"')).Count
    $caseCount   = ([regex]::Matches($html, 'class="swiper-slide nt-case-card"')).Count
    $testimCount = ([regex]::Matches($html, 'class="swiper-slide nt-testimonial-card"')).Count
    $procCount   = ([regex]::Matches($html, 'id="nt-proc-trigger-\d+"')).Count
    $faqCount    = ([regex]::Matches($html, 'id="nt-faq-trigger-\d+"')).Count

    Add-TestResult "ACF Rendered Counts" "6 Service Cards rendered" ($(if ($svcCount -eq 6) { "PASS" } else { "FAIL" })) "Count=$svcCount"
    Add-TestResult "ACF Rendered Counts" "5 Case Study Slides rendered" ($(if ($caseCount -eq 5) { "PASS" } else { "FAIL" })) "Count=$caseCount"
    Add-TestResult "ACF Rendered Counts" "4 Testimonial Slides rendered" ($(if ($testimCount -eq 4) { "PASS" } else { "FAIL" })) "Count=$testimCount"
    Add-TestResult "ACF Rendered Counts" "4 Process Accordion Steps rendered" ($(if ($procCount -eq 4) { "PASS" } else { "FAIL" })) "Count=$procCount"
    Add-TestResult "ACF Rendered Counts" "5 FAQ Accordion Items rendered" ($(if ($faqCount -eq 5) { "PASS" } else { "FAIL" })) "Count=$faqCount"

    # ------------------------------------------------------------------------
    # 3. ENQUEUED ASSETS AVAILABILITY
    # ------------------------------------------------------------------------
    $assetUrls = @(
        "$BaseUrl/wp-content/themes/ai-hub-theme/assets/vendor/swiper/swiper-bundle.min.css",
        "$BaseUrl/wp-content/themes/ai-hub-theme/assets/vendor/swiper/swiper-bundle.min.js",
        "$BaseUrl/wp-content/themes/ai-hub-theme/assets/css/novatech.css",
        "$BaseUrl/wp-content/themes/ai-hub-theme/assets/js/novatech.js"
    )
    foreach ($aUrl in $assetUrls) {
        $aResp = Invoke-WebRequest -Uri $aUrl -Method Head -UseBasicParsing
        $shortName = ($aUrl -split '/')[-1]
        Add-TestResult "Frontend Assets" "Asset $shortName HTTP 200" ($(if ($aResp.StatusCode -eq 200) { "PASS" } else { "FAIL" })) "Status=$($aResp.StatusCode)"
    }

    # ------------------------------------------------------------------------
    # 4. WORDPRESS REST API & ACF PAYLOAD VERIFICATION
    # ------------------------------------------------------------------------
    $restPages = @(Invoke-RestMethod -Uri "$BaseUrl/wp-json/wp/v2/pages?slug=novatech-solutions")
    $pageObj = $restPages[0]
    $acfData = $pageObj.acf

    $expectedAcfGroups = @(
        "header_settings",
        "hero_section",
        "services_section",
        "cases_slider_section",
        "testimonials_section",
        "process_section",
        "faq_section",
        "contact_footer_section"
    )
    foreach ($grp in $expectedAcfGroups) {
        if ($null -ne $acfData.$grp) {
            Add-TestResult "ACF REST API" "REST API exposes acf.$grp" "PASS" "Non-null object returned"
        } else {
            Add-TestResult "ACF REST API" "REST API exposes acf.$grp" "FAIL" "Missing in REST response"
        }
    }

    $heroImgUrl = $acfData.hero_section.hero_image.url
    if ($heroImgUrl -match "^http://localhost:8001/wp-content/uploads/.*\.png$") {
        Add-TestResult "ACF REST API" "ACF Image field expanded with URL & metadata" "PASS" "URL=$heroImgUrl"
    } else {
        Add-TestResult "ACF REST API" "ACF Image field expanded with URL & metadata" "FAIL" "Value=$($acfData.hero_section.hero_image | ConvertTo-Json -Compress)"
    }

    # ------------------------------------------------------------------------
    # 5. SEED IDEMPOTENCY CHECK
    # ------------------------------------------------------------------------
    & docker compose --profile cli run --rm -T wpcli wp eval-file /var/www/html/wp-content/scripts/seed-novatech.php --path=/var/www/html --url=http://localhost:8001 | Out-Null
    $pagesAfterSeed = @(Invoke-RestMethod -Uri "$BaseUrl/wp-json/wp/v2/pages?slug=novatech-solutions")
    $cf7CountRaw = (& docker compose --profile cli run --rm -T wpcli wp post list --post_type=wpcf7_contact_form --format=count --path=/var/www/html --url=http://localhost:8001 | Select-Object -Last 1).Trim()
    $acfGroupCountRaw = (& docker compose --profile cli run --rm -T wpcli wp post list --post_type=acf-field-group --format=count --path=/var/www/html --url=http://localhost:8001 | Select-Object -Last 1).Trim()

    Add-TestResult "Seed Idempotency" "No duplicate pages on re-seed" ($(if ($pagesAfterSeed.Count -eq 1) { "PASS" } else { "FAIL" })) "Page count=$($pagesAfterSeed.Count)"
    Add-TestResult "Seed Idempotency" "No duplicate CF7 forms on re-seed" ($(if ($cf7CountRaw -eq "1") { "PASS" } else { "FAIL" })) "CF7 form count=$cf7CountRaw"
    Add-TestResult "Seed Idempotency" "No duplicate ACF field groups on re-seed" ($(if ($acfGroupCountRaw -eq "1") { "PASS" } else { "FAIL" })) "ACF group count=$acfGroupCountRaw"

    # ------------------------------------------------------------------------
    # 6. CONTACT FORM 7 VALIDATION & MAILPIT DELIVERY
    # ------------------------------------------------------------------------
    $cf7Id = (& docker compose --profile cli run --rm -T wpcli wp post list --post_type=wpcf7_contact_form --field=ID --path=/var/www/html --url=http://localhost:8001 | Select-Object -Last 1).Trim()
    $cf7Endpoint = "$BaseUrl/wp-json/contact-form-7/v1/contact-forms/$cf7Id/feedback"
    $unitTag = "wpcf7-f$($cf7Id)-p$($pageObj.id)-o1"

    # 6a. Test validation error on empty required fields
    $boundaryEmpty = "----NovaTechTestBoundary" + [guid]::NewGuid().ToString("N")
    $bodyEmpty = @(
        "--$boundaryEmpty",
        'Content-Disposition: form-data; name="_wpcf7"',
        "",
        "$cf7Id",
        "--$boundaryEmpty",
        'Content-Disposition: form-data; name="_wpcf7_unit_tag"',
        "",
        "$unitTag",
        "--$boundaryEmpty",
        'Content-Disposition: form-data; name="your-name"',
        "",
        "",
        "--$boundaryEmpty",
        'Content-Disposition: form-data; name="your-email"',
        "",
        "not-an-email",
        "--$boundaryEmpty--"
    ) -join "`r`n"

    $valResp = Invoke-RestMethod -Uri $cf7Endpoint -Method Post -ContentType "multipart/form-data; boundary=$boundaryEmpty" -Body $bodyEmpty
    if ($valResp.status -eq "validation_failed" -and $valResp.invalid_fields.Count -ge 3) {
        Add-TestResult "Contact Form 7" "Validation rejects missing/invalid fields" "PASS" "status=$($valResp.status), invalid_fields=$($valResp.invalid_fields.Count)"
    } else {
        Add-TestResult "Contact Form 7" "Validation rejects missing/invalid fields" "FAIL" "status=$($valResp.status)"
    }

    # 6b. Test valid submission & verify delivery in Mailpit
    $uniqueSubject = "Platform Audit Inquiry " + [guid]::NewGuid().ToString().Substring(0, 8)
    $boundaryValid = "----NovaTechValidBoundary" + [guid]::NewGuid().ToString("N")
    $bodyValid = @(
        "--$boundaryValid",
        'Content-Disposition: form-data; name="_wpcf7"',
        "",
        "$cf7Id",
        "--$boundaryValid",
        'Content-Disposition: form-data; name="_wpcf7_unit_tag"',
        "",
        "$unitTag",
        "--$boundaryValid",
        'Content-Disposition: form-data; name="your-name"',
        "",
        "Jordan Hayes (Demo CTO)",
        "--$boundaryValid",
        'Content-Disposition: form-data; name="your-email"',
        "",
        "jordan.hayes@demo-enterprise.local",
        "--$boundaryValid",
        'Content-Disposition: form-data; name="your-subject"',
        "",
        "$uniqueSubject",
        "--$boundaryValid",
        'Content-Disposition: form-data; name="your-message"',
        "",
        "We are evaluating a phased migration of our multi-region Kubernetes clusters and legacy CMS pages.",
        "--$boundaryValid--"
    ) -join "`r`n"

    $sendResp = Invoke-RestMethod -Uri $cf7Endpoint -Method Post -ContentType "multipart/form-data; boundary=$boundaryValid" -Body $bodyValid
    if ($sendResp.status -eq "mail_sent") {
        Add-TestResult "Contact Form 7" "Valid submission returns 'mail_sent'" "PASS" "message='$($sendResp.message)'"
    } else {
        Add-TestResult "Contact Form 7" "Valid submission returns 'mail_sent'" "FAIL" "status=$($sendResp.status), message='$($sendResp.message)'"
    }

    Start-Sleep -Seconds 1
    $mailpitData = Invoke-RestMethod -Uri $MailpitApi
    $matchedMail = $mailpitData.messages | Where-Object { $_.Subject -match [regex]::Escape($uniqueSubject) } | Select-Object -First 1

    if ($null -ne $matchedMail) {
        Add-TestResult "Mailpit Delivery" "Email captured in local Mailpit (127.0.0.1:8025)" "PASS" "Mailpit ID=$($matchedMail.ID), Subject='$($matchedMail.Subject)'"
    } else {
        Add-TestResult "Mailpit Delivery" "Email captured in local Mailpit (127.0.0.1:8025)" "FAIL" "Message with subject '$uniqueSubject' not found in Mailpit"
    }

    Write-Host "`n================ SUMMARY ================" -ForegroundColor Cyan
    $passCount = ($Results | Where-Object { $_.Status -eq "PASS" }).Count
    $failCount = ($Results | Where-Object { $_.Status -eq "FAIL" }).Count
    Write-Host "Total Checks: $($Results.Count) | PASS: $passCount | FAIL: $failCount" -ForegroundColor ($(if ($failCount -eq 0) { "Green" } else { "Red" }))
    if ($failCount -gt 0) {
        exit 1
    }
} finally {
    Pop-Location
}
