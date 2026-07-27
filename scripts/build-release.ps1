param(
    [string]$OutputDirectory = "dist"
)

$ErrorActionPreference = "Stop"
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$destinationRoot = Join-Path $projectRoot $OutputDirectory
$stage = Join-Path $destinationRoot "gymflow-release"
$archive = Join-Path $destinationRoot "gymflow-release.zip"

if (Test-Path -LiteralPath $stage) {
    Remove-Item -LiteralPath $stage -Recurse -Force
}
New-Item -ItemType Directory -Path $stage -Force | Out-Null

$excluded = @(
    ".git", ".github", ".tools", "node_modules", "tests", "dist",
    ".env", "database.sqlite", "storage\logs", "storage\framework"
)

Get-ChildItem -LiteralPath $projectRoot -Force | Where-Object {
    $_.Name -notin ($excluded | ForEach-Object { ($_ -split '\\')[0] })
} | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $stage -Recurse -Force
}

$copiedSqlite = Join-Path $stage "database\database.sqlite"
if (Test-Path -LiteralPath $copiedSqlite) {
    Remove-Item -LiteralPath $copiedSqlite -Force
}

foreach ($runtimeDirectory in @(
    "storage\app\public",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\views",
    "storage\logs"
)) {
    New-Item -ItemType Directory -Path (Join-Path $stage $runtimeDirectory) -Force | Out-Null
}

if (Test-Path -LiteralPath $archive) {
    Remove-Item -LiteralPath $archive -Force
}
Compress-Archive -Path (Join-Path $stage "*") -DestinationPath $archive -CompressionLevel Optimal
Write-Host "Release artifact: $archive"
