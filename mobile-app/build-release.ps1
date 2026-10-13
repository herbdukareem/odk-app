$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path $PSScriptRoot).Path
$locationPushed = $false
$previousNodeEnv = $env:NODE_ENV

try {
    $env:NODE_ENV = 'production'
    Push-Location (Join-Path $projectRoot 'android')
    $locationPushed = $true

    & .\gradlew.bat clean assembleRelease -PreactNativeArchitectures=arm64-v8a --max-workers=2 --no-daemon
    if ($LASTEXITCODE -ne 0) {
        throw "Android release build failed with exit code $LASTEXITCODE."
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

if (-not (Test-Path -LiteralPath $apk)) {
    throw "Release APK was not found at $apk."
}

New-Item -ItemType Directory -Force $downloadDirectory | Out-Null
Copy-Item -LiteralPath $apk -Destination $download -Force

Get-Item -LiteralPath $apk, $download | Select-Object FullName, Length, LastWriteTime
