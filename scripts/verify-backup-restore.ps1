[CmdletBinding()]
param(
    [string]$BackupDirectory = ''
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$restoreId = [guid]::NewGuid().ToString('N')
$restoreDatabase = "theobroma_restore_$restoreId"
$restoreTemp = "/tmp/theobroma-restore-$restoreId.sql"
$rootPassword = ''

function Invoke-Docker {
    param([Parameter(Mandatory)][string[]]$Arguments)
    & docker @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker command failed with exit code ${LASTEXITCODE}: docker $($Arguments -join ' ')"
    }
}

if ($BackupDirectory -eq '') {
    $backupOutput = & (Join-Path $PSScriptRoot 'backup-site.ps1')
    if ($LASTEXITCODE -ne 0 -or !$backupOutput) {
        throw 'Backup creation failed.'
    }
    $BackupDirectory = if ($backupOutput -is [array]) { [string]$backupOutput[-1] } else { [string]$backupOutput }
}

$backupPath = [System.IO.Path]::GetFullPath($BackupDirectory)
$manifestPath = Join-Path $backupPath 'manifest.json'
$databasePath = Join-Path $backupPath 'database.sql'
$uploadsPath = Join-Path $backupPath 'uploads.tar.gz'
foreach ($required in @($manifestPath, $databasePath, $uploadsPath)) {
    if (!(Test-Path -LiteralPath $required -PathType Leaf)) {
        throw "Backup file is missing: $required"
    }
}

$manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
$databaseHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $databasePath).Hash.ToLowerInvariant()
$uploadsHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $uploadsPath).Hash.ToLowerInvariant()
if ($databaseHash -ne $manifest.database.sha256 -or $uploadsHash -ne $manifest.uploads.sha256) {
    throw 'Backup checksum verification failed.'
}
if ($manifest.table_prefix -notmatch '^[A-Za-z0-9_]+$' -or $restoreDatabase -notmatch '^theobroma_restore_[a-f0-9]{32}$') {
    throw 'Unsafe restore identifier or table prefix.'
}

Push-Location $repoRoot
try {
    $rootPassword = (& docker compose exec -T db printenv MYSQL_ROOT_PASSWORD).Trim()
    if ($LASTEXITCODE -ne 0 -or $rootPassword -eq '') {
        throw 'Unable to obtain the database restore credential from the container environment.'
    }
    Invoke-Docker @('compose', 'cp', $databasePath, "db:$restoreTemp")
    $createSql = 'CREATE DATABASE {0} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' -f $restoreDatabase
    & docker compose exec -T db mysql -uroot "-p$rootPassword" -e $createSql
    if ($LASTEXITCODE -ne 0) { throw 'Unable to create the isolated restore database.' }
    $restoreCommand = 'mysql -u"root" -p"$MYSQL_ROOT_PASSWORD" "{0}" < "{1}"' -f $restoreDatabase, $restoreTemp
    Invoke-Docker @('compose', 'exec', '-T', 'db', 'sh', '-c', $restoreCommand)

    $prefix = [string]$manifest.table_prefix
    $countsSql = 'SELECT (SELECT COUNT(*) FROM {0}posts WHERE post_type=''product'' AND post_status=''publish''), (SELECT COUNT(*) FROM {0}posts WHERE post_type=''post'' AND post_status=''publish''), (SELECT COUNT(*) FROM {0}posts WHERE post_type=''page'' AND post_status=''publish''), (SELECT COUNT(*) FROM {0}users);' -f $prefix
    $restored = (& docker compose exec -T db mysql -N -B -uroot "-p$rootPassword" $restoreDatabase -e $countsSql).Trim().Split("`t")
    if ($LASTEXITCODE -ne 0 -or $restored.Count -ne 4) {
        throw 'Unable to query the restored database.'
    }
    $expected = @(
        [int]$manifest.database.published_products,
        [int]$manifest.database.published_posts,
        [int]$manifest.database.published_pages,
        [int]$manifest.database.users
    )
    $actual = @([int]$restored[0], [int]$restored[1], [int]$restored[2], [int]$restored[3])
    if (($expected -join ',') -ne ($actual -join ',')) {
        throw "Restored counts differ: expected $($expected -join ','), got $($actual -join ',')"
    }

    Invoke-Docker @('compose', 'cp', $uploadsPath, "wordpress:/tmp/theobroma-uploads-verify-$restoreId.tar.gz")
    $archiveList = & docker compose exec -T wordpress tar -tzf "/tmp/theobroma-uploads-verify-$restoreId.tar.gz"
    if ($LASTEXITCODE -ne 0 -or !($archiveList | Select-String -SimpleMatch 'uploads/')) {
        throw 'Uploads archive integrity verification failed.'
    }

    Write-Output "Backup restore verified: products=$($actual[0]), posts=$($actual[1]), pages=$($actual[2]), users=$($actual[3]), uploads archive readable."
} finally {
    if ($restoreDatabase -match '^theobroma_restore_[a-f0-9]{32}$') {
        if ($rootPassword -eq '') {
            $rootPassword = (& docker compose exec -T db printenv MYSQL_ROOT_PASSWORD).Trim()
        }
        if ($rootPassword -ne '') {
            $dropSql = 'DROP DATABASE IF EXISTS {0}' -f $restoreDatabase
            & docker compose exec -T db mysql -uroot "-p$rootPassword" -e $dropSql | Out-Null
        }
    }
    & docker compose exec -T db rm -f -- $restoreTemp | Out-Null
    & docker compose exec -T wordpress rm -f -- "/tmp/theobroma-uploads-verify-$restoreId.tar.gz" | Out-Null
    Pop-Location
}
