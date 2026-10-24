param(
    [ValidatePattern('^http://(127\.0\.0\.1|localhost)(:[0-9]+)?$')]
    [string]$Origin = 'http://127.0.0.1:8000',
    [ValidateRange(2, 30)][int]$Requests = 5,
    [string[]]$Paths = @('/', '/movies', '/cinemas', '/login')
)
$ErrorActionPreference = 'Stop'
foreach ($path in $Paths) {
    if (-not $path.StartsWith('/') -or $path.StartsWith('//')) { throw 'Paths must be local absolute paths.' }
    $samples = @()
    $cold = $null
    for ($attempt = 0; $attempt -le $Requests; $attempt++) {
        $raw = & curl.exe -s -o NUL --max-time 30 -w '%{http_code} %{time_starttransfer}' ($Origin + $path)
        if ($LASTEXITCODE -ne 0) { throw "Request failed: $path" }
        $fields = $raw -split ' '
        if ($fields[0] -ne '200') { throw "HTTP $($fields[0]): $path" }
        $elapsed = 1000 * [double]::Parse($fields[1], [Globalization.CultureInfo]::InvariantCulture)
        if ($attempt -eq 0) { $cold = $elapsed } else { $samples += $elapsed }
    }
    $sorted = @($samples | Sort-Object)
    [pscustomobject]@{
        Path = $path
        FirstMs = [math]::Round($cold, 1)
        MedianMs = [math]::Round(($sorted[[math]::Floor(($Requests - 1) / 2)] + $sorted[[math]::Floor($Requests / 2)]) / 2, 1)
        P95Ms = [math]::Round($sorted[[math]::Ceiling($Requests * 0.95) - 1], 1)
        Requests = $Requests
    }
}
