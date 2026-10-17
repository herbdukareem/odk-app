param(
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path $PSScriptRoot).Path
$locationPushed = $false
$previousNodeEnv = $env:NODE_ENV

try {
    if (-not $SkipBuild) {
        $env:NODE_ENV = 'production'
        Push-Location (Join-Path $projectRoot 'android')
        $locationPushed = $true

        & .\gradlew.bat clean assembleRelease -PreactNativeArchitectures=arm64-v8a --max-workers=2 --no-daemon
        if ($LASTEXITCODE -ne 0) {
            throw "Android release build failed with exit code $LASTEXITCODE."
        }
    }
} finally {
    if ($locationPushed) {
        Pop-Location
    }

    if ($null -eq $previousNodeEnv) {
        Remove-Item Env:NODE_ENV -ErrorAction SilentlyContinue
    } else {
        $env:NODE_ENV = $previousNodeEnv
    }
}

$apk = Join-Path $projectRoot 'android\app\build\outputs\apk\release\app-release.apk'
$downloadDirectory = Join-Path $projectRoot '..\backend\public\downloads'
$download = Join-Path $downloadDirectory 'datakit-qa.apk'
$manifest = Join-Path $downloadDirectory 'datakit-qa-update.json'

if (-not (Test-Path -LiteralPath $apk)) {
    throw "Release APK was not found at $apk."
}

$appConfig = Get-Content -LiteralPath (Join-Path $projectRoot 'app.json') -Raw | ConvertFrom-Json
$expoConfig = $appConfig.expo
$extra = $expoConfig.extra
$downloadUrl = 'https://datakit.pro/downloads/datakit-qa.apk'
$mandatory = $false
$releaseNotes = ''

if ($null -ne $extra.appUpdateDownloadUrl -and [string]::IsNullOrWhiteSpace([string] $extra.appUpdateDownloadUrl) -eq $false) {
    $downloadUrl = [string] $extra.appUpdateDownloadUrl
}

if ($null -ne $extra.appUpdateMandatory) {
    $mandatory = [bool] $extra.appUpdateMandatory
}

if ($null -ne $extra.appUpdateReleaseNotes) {
    $releaseNotes = [string] $extra.appUpdateReleaseNotes
}

$apkHash = (Get-FileHash -LiteralPath $apk -Algorithm SHA256).Hash.ToLowerInvariant()
$manifestPayload = [ordered]@{
    app_id = [string] $expoConfig.android.package
    version = [string] $expoConfig.version
    version_code = [int] $expoConfig.android.versionCode
    download_url = $downloadUrl
    mandatory = $mandatory
    release_notes = $releaseNotes
    checksum_sha256 = $apkHash
}

New-Item -ItemType Directory -Force $downloadDirectory | Out-Null
Copy-Item -LiteralPath $apk -Destination $download -Force
$manifestJson = $manifestPayload | ConvertTo-Json -Depth 3
[System.IO.File]::WriteAllText($manifest, $manifestJson, (New-Object System.Text.UTF8Encoding($false)))

Get-Item -LiteralPath $apk, $download, $manifest | Select-Object FullName, Length, LastWriteTime
