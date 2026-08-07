[CmdletBinding()]
param(
    [string]$OutputRoot = (Join-Path (Split-Path -Parent $PSScriptRoot) 'backups')
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$destination = Join-Path ([System.IO.Path]::GetFullPath($OutputRoot)) "theobroma-$stamp"
$dbTemp = "/tmp/theobroma-$stamp.sql"
$uploadsTemp = "/tmp/theobroma-uploads-$stamp.tar.gz"

function Invoke-Docker {
    param([Parameter(Mandatory)][string[]]$Arguments)
    & docker @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker command failed with exit code ${LASTEXITCODE}: docker $($Arguments -join ' ')"
    }
}

New-Item -ItemType Directory -Path $destination -Force | Out-Null
Push-Location $repoRoot
try {
    $dumpCommand = 'exec mysqldump --no-tablespaces --single-transaction --quick --routines --triggers -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" > "{0}"' -f $dbTemp
    Invoke-Docker @('compose', 'exec', '-T', 'db', 'sh', '-c', $dumpCommand)
    Invoke-Docker @('compose', 'cp', "db:$dbTemp", (Join-Path $destination 'database.sql'))

    $archiveCommand = 'tar -czf "{0}" -C /var/www/html/wp-content uploads' -f $uploadsTemp
    Invoke-Docker @('compose', 'exec', '-T', 'wordpress', 'sh', '-c', $archiveCommand)
    Invoke-Docker @('compose', 'cp', "wordpress:$uploadsTemp", (Join-Path $destination 'uploads.tar.gz'))

    $prefix = 'wp_'
    $countsSql = 'SELECT (SELECT COUNT(*) FROM {0}posts WHERE post_type=''product'' AND post_status=''publish''), (SELECT COUNT(*) FROM {0}posts WHERE post_type=''post'' AND post_status=''publish''), (SELECT COUNT(*) FROM {0}posts WHERE post_type=''page'' AND post_status=''publish''), (SELECT COUNT(*) FROM {0}users);' -f $prefix
    $dbUser = (& docker compose exec -T db printenv MYSQL_USER).Trim()
    $dbPassword = (& docker compose exec -T db printenv MYSQL_PASSWORD).Trim()
    $dbName = (& docker compose exec -T db printenv MYSQL_DATABASE).Trim()
    $counts = (& docker compose exec -T db mysql -N -B "-u$dbUser" "-p$dbPassword" $dbName -e $countsSql).Trim().Split("`t")
    if ($LASTEXITCODE -ne 0 -or $counts.Count -ne 4) {
        throw 'Unable to read database counts for the backup manifest.'
    }

    $databaseFile = Join-Path $destination 'database.sql'
    $uploadsFile = Join-Path $destination 'uploads.tar.gz'
    $manifest = [ordered]@{
        created_at = (Get-Date).ToString('o')
        table_prefix = $prefix
        database = [ordered]@{
            file = 'database.sql'
            sha256 = (Get-FileHash -Algorithm SHA256 -LiteralPath $databaseFile).Hash.ToLowerInvariant()
            published_products = [int]$counts[0]
            published_posts = [int]$counts[1]
            published_pages = [int]$counts[2]
            users = [int]$counts[3]
        }
        uploads = [ordered]@{
            file = 'uploads.tar.gz'
            sha256 = (Get-FileHash -Algorithm SHA256 -LiteralPath $uploadsFile).Hash.ToLowerInvariant()
            bytes = (Get-Item -LiteralPath $uploadsFile).Length
        }
    }
    $manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $destination 'manifest.json') -Encoding UTF8
} finally {
    & docker compose exec -T db rm -f -- $dbTemp | Out-Null
    & docker compose exec -T wordpress rm -f -- $uploadsTemp | Out-Null
    Pop-Location
}

Write-Output $destination
