$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path $PSScriptRoot).Path
$shortRoot = "C:\datakit-mobile-build-$PID"
$locationPushed = $false
$nativeBuildDirectory = Join-Path $projectRoot 'android\app\.cxx'

if (Test-Path -LiteralPath $shortRoot) {
    throw "$shortRoot already exists. Remove the temporary junction or choose another build path."
}

New-Item -ItemType Junction -Path $shortRoot -Target $projectRoot | Out-Null

try {
    # Remove native metadata generated through an older temporary path.
    if (Test-Path -LiteralPath $nativeBuildDirectory) {
        cmd /c "rmdir /s /q `"$nativeBuildDirectory`"" | Out-Null
    }

    $env:NODE_ENV = 'production'
    Push-Location (Join-Path $shortRoot 'android')
    $locationPushed = $true

    & .\gradlew.bat clean assembleRelease -PreactNativeArchitectures=arm64-v8a --max-workers=2 --no-daemon
    if ($LASTEXITCODE -ne 0) {
        throw "Android release build failed with exit code $LASTEXITCODE."
    }
} finally {
    if ($locationPushed) {
        Pop-Location
    }

    if (Test-Path -LiteralPath $shortRoot) {
        cmd /c "rmdir $shortRoot" | Out-Null
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
