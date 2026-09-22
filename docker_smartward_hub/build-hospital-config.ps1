<#
    Builds the Docker config for one hospital from the SmartWard hub compose file.

    Run it through "build hospital config.bat" (double-click it). It asks for the
    hospital's name, the address users open and the web ports, then writes

        hospital config\<Hospital name>\docker-compose.yml   ready to run on that hospital's server
        hospital config\<Hospital name>\hospital.env         the settings it was built from
        hospital config\<Hospital name>\README.txt           how to run it there

    docker-compose.yml is the hub template (docker-compose.yml next to this script)
    with this hospital's values filled in, so the hospital's server needs nothing
    else. Run the .bat again at any time: the answers in hospital.env are offered as
    the defaults, other values changed in hospital.env are kept, and the compose file
    is rebuilt from the current template. The template itself is never changed.
#>
param(
    [string]$Name,
    [string]$Url,
    [string]$HttpPort,
    [string]$HttpsPort
)

$ErrorActionPreference = 'Stop'

$hubDir = $PSScriptRoot
$templatePath = Join-Path $hubDir 'docker-compose.yml'
$configRoot = Join-Path $hubDir 'hospital config'

# The template's only hard-coded hospital detail: the domain in its hosts entry
$templateDomain = 'phklsmartward.ppl.ihh.com'

function New-Setting([string]$Group, [string]$Key, [string]$Default, [string]$Note) {
    [pscustomobject]@{ Group = $Group; Key = $Key; Default = $Default; Note = $Note }
}

# Settings kept per hospital in hospital.env, in file order. Defaults match the
# template's own. The database settings are left out on purpose: the database
# inside the image is created with the template's values, so they must not change.
$schema = @(
    New-Setting 'Hospital' 'HOSPITAL_NAME' '' 'Shown in the generated files'
    New-Setting 'Hospital' 'PROJECT_NAME' 'docker_smartward_hub' 'Docker project name. On a server that already runs SmartWard from a docker_smartward_hub folder, keep docker_smartward_hub so it keeps its database'

    New-Setting 'Address users open in the browser' 'APP_URL' 'http://localhost:18080' ''
    New-Setting 'Address users open in the browser' 'ASSET_URL' 'http://localhost:18080' 'Normally the same as APP_URL'
    New-Setting 'Address users open in the browser' 'APP_ENV' 'local' 'Set from APP_URL: production for https:// (all links are forced to https://), local for plain http://'
    New-Setting 'Address users open in the browser' 'APP_DEBUG' 'false' 'true shows error details on screen; keep false on a hospital server'
    New-Setting 'Address users open in the browser' 'APP_NAME' 'SmartWard' ''
    New-Setting 'Address users open in the browser' 'PHKL_RESOLVE_IP' '127.0.0.1' 'What the hospital domain points to inside the app container; 127.0.0.1 is the container itself'

    New-Setting 'Image' 'SMARTWARD_IMAGE' 'kelvinric/smartward4:latest' 'Use a fixed tag instead of latest to pin this hospital to a version'

    New-Setting 'Ports on the hospital server' 'HTTP_PORT' '18080' 'Nginx http'
    New-Setting 'Ports on the hospital server' 'HTTPS_PORT' '18443' 'Nginx https'
    New-Setting 'Ports on the hospital server' 'APP_PORT' '8888' 'The app directly (Octane)'
    New-Setting 'Ports on the hospital server' 'DB_PORT' '3307' 'MySQL'
    New-Setting 'Ports on the hospital server' 'REDIS_PORT' '6380' 'Redis'
    New-Setting 'Ports on the hospital server' 'HL7_PORT' '3000' 'ADT HL7 listener'
    New-Setting 'Ports on the hospital server' 'ECG_PORT' '3051' 'ECG upload server'
    New-Setting 'Ports on the hospital server' 'BBRAUN_PORT' '5001' 'B.Braun infusion listener'

    New-Setting 'Time zone' 'TZ' 'Asia/Kuala_Lumpur' ''
    New-Setting 'Time zone' 'APP_TIMEZONE' 'Asia/Kuala_Lumpur' ''

    New-Setting 'Performance' 'OCTANE_WORKERS' '16' ''
    New-Setting 'Performance' 'OCTANE_TASK_WORKERS' '16' ''
    New-Setting 'Performance' 'OCTANE_MAX_REQUESTS' '500' ''
    New-Setting 'Performance' 'DOCKER_CPU_LIMIT' '2' 'CPUs the app container may use'
    New-Setting 'Performance' 'DOCKER_MEMORY_LIMIT' '4G' 'Memory the app container may use'
    New-Setting 'Performance' 'LOG_LEVEL' 'warning' ''

    New-Setting 'Integrations' 'ECG_USERNAME' 'admin' 'ECG upload server login'
    New-Setting 'Integrations' 'ECG_PASSWORD' 'admin123' ''
    New-Setting 'Integrations' 'LARAVEL_API_KEY' '' 'Key the ADT listener sends to the app'
    New-Setting 'Integrations' 'BBRAUN_DEV_MODE' 'false' ''
    New-Setting 'Integrations' 'BBRAUN_DEV_ASSIGN_MRN' '' ''
)

function Read-EnvFile([string]$Path) {
    $found = [ordered]@{}
    if (-not (Test-Path -LiteralPath $Path)) {
        return $found
    }
    foreach ($line in [IO.File]::ReadAllLines($Path)) {
        if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$') {
            $key = $Matches[1]
            $value = $Matches[2]
            if ($value.Length -ge 2 -and $value.StartsWith("'") -and $value.EndsWith("'")) {
                $value = $value.Substring(1, $value.Length - 2)
            } elseif ($value.Length -ge 2 -and $value.StartsWith('"') -and $value.EndsWith('"')) {
                $value = $value.Substring(1, $value.Length - 2).Replace('\"', '"').Replace('\\', '\')
            }
            $found[$key] = $value
        }
    }
    return $found
}

function Format-EnvValue([string]$Value) {
    if ($Value -match '^[A-Za-z0-9_./:@+-]*$') {
        return $Value
    }
    if ($Value -notmatch "'") {
        return "'" + $Value + "'"
    }
    return '"' + $Value.Replace('\', '\\').Replace('"', '\"') + '"'
}

function Ask([string]$Question, [string]$Default) {
    $prompt = $Question
    if ($Default) {
        $prompt = "$Question [$Default]"
    }
    $answer = Read-Host $prompt
    if ([string]::IsNullOrWhiteSpace($answer)) {
        return $Default
    }
    return $answer.Trim()
}

function Ask-Port([string]$Question, [string]$Default, [string]$Given) {
    while ($true) {
        $answer = $Given
        if (-not $answer) {
            $answer = Ask $Question $Default
        }
        $Given = $null
        $number = 0
        if ([int]::TryParse("$answer", [ref]$number) -and $number -ge 1 -and $number -le 65535) {
            return "$number"
        }
        Write-Host 'Enter a port number between 1 and 65535.' -ForegroundColor Yellow
    }
}

if (-not (Test-Path -LiteralPath $templatePath)) {
    throw "Template not found: $templatePath"
}

Write-Host ''
Write-Host 'SmartWard hospital config builder' -ForegroundColor Cyan
Write-Host "Template: $templatePath"
Write-Host "Output:   $configRoot\<Hospital name>\"
Write-Host ''

$existing = @()
if (Test-Path -LiteralPath $configRoot) {
    $existing = @(Get-ChildItem -LiteralPath $configRoot -Directory | Sort-Object Name | ForEach-Object { $_.Name })
}
if ($existing.Count -gt 0) {
    Write-Host 'Existing hospitals (type the number to update one):'
    for ($i = 0; $i -lt $existing.Count; $i++) {
        Write-Host ('  {0}. {1}' -f ($i + 1), $existing[$i])
    }
    Write-Host ''
}

# --- Hospital -----------------------------------------------------------------
$invalidChars = [IO.Path]::GetInvalidFileNameChars()
$pickedExisting = $false
while ($true) {
    $answer = $Name
    if (-not $answer) {
        $answer = Read-Host 'Hospital name (e.g. Pantai Hospital Kuala Lumpur), or a number from the list'
    }
    $Name = $null
    $answer = "$answer".Trim()

    $number = 0
    if ($answer -match '^\d+$' -and [int]::TryParse($answer, [ref]$number) -and $number -ge 1 -and $number -le $existing.Count) {
        $answer = $existing[$number - 1]
        $pickedExisting = $true
    }

    $folderName = -join ($answer.ToCharArray() | Where-Object { $invalidChars -notcontains $_ })
    $folderName = ($folderName -replace '\s+', ' ').Trim().TrimEnd('.')
    if ($folderName) {
        break
    }
    Write-Host 'Enter a hospital name.' -ForegroundColor Yellow
}

$hospitalDir = Join-Path $configRoot $folderName
$settingsPath = Join-Path $hospitalDir 'hospital.env'
$saved = Read-EnvFile $settingsPath

if ($saved.Count -gt 0) {
    Write-Host "Updating '$folderName'. Press Enter to keep each saved value." -ForegroundColor Green
} else {
    Write-Host "New hospital '$folderName'." -ForegroundColor Green
}
Write-Host ''

$values = [ordered]@{}
foreach ($setting in $schema) {
    if ($saved.Contains($setting.Key)) {
        $values[$setting.Key] = $saved[$setting.Key]
    } else {
        $values[$setting.Key] = $setting.Default
    }
}
# Anything else added to hospital.env by hand is kept, and fills the template too
foreach ($key in $saved.Keys) {
    if (-not $values.Contains($key)) {
        $values[$key] = $saved[$key]
    }
}

if (-not $pickedExisting -or -not $values['HOSPITAL_NAME']) {
    $values['HOSPITAL_NAME'] = ($answer -replace '\s+', ' ').Trim()
}

# --- Address ------------------------------------------------------------------
$assetFollowsApp = (-not $saved.Contains('ASSET_URL')) -or ($saved['ASSET_URL'] -eq $saved['APP_URL'])
while ($true) {
    $answer = $Url
    if (-not $answer) {
        $answer = Ask 'Address users open (e.g. https://phklsmartward.ppl.ihh.com or http://10.0.0.5:18080)' $values['APP_URL']
    }
    $Url = $null
    $answer = "$answer".Trim().TrimEnd('/')
    $uri = $null
    if ([Uri]::TryCreate($answer, [UriKind]::Absolute, [ref]$uri) -and
        ($uri.Scheme -eq 'http' -or $uri.Scheme -eq 'https') -and
        $uri.AbsolutePath -eq '/' -and -not $uri.Query) {
        break
    }
    Write-Host 'Enter a full address starting with http:// or https://, without a path.' -ForegroundColor Yellow
}
$values['APP_URL'] = $answer
if ($assetFollowsApp) {
    $values['ASSET_URL'] = $answer
}
if ($uri.Scheme -eq 'https') {
    $values['APP_ENV'] = 'production'
} else {
    $values['APP_ENV'] = 'local'
}

# --- Ports --------------------------------------------------------------------
$values['HTTP_PORT'] = Ask-Port 'HTTP port on the server' $values['HTTP_PORT'] $HttpPort
$values['HTTPS_PORT'] = Ask-Port 'HTTPS port on the server' $values['HTTPS_PORT'] $HttpsPort

$portOwners = @{ '5000' = 'the LDAP service (fixed in the template)' }
foreach ($key in 'HTTP_PORT', 'HTTPS_PORT', 'APP_PORT', 'DB_PORT', 'REDIS_PORT', 'HL7_PORT', 'ECG_PORT', 'BBRAUN_PORT') {
    $port = "$($values[$key])"
    if ($portOwners.ContainsKey($port)) {
        Write-Warning "$key ($port) is also used by $($portOwners[$port]). Change one of them in hospital.env and run this again."
    } else {
        $portOwners[$port] = $key
    }
}
if (-not $uri.IsDefaultPort) {
    $urlPort = "$($uri.Port)"
    if (@("$($values['HTTP_PORT'])", "$($values['HTTPS_PORT'])", "$($values['APP_PORT'])") -notcontains $urlPort) {
        Write-Warning ("The address uses port $urlPort, but this hospital publishes http on $($values['HTTP_PORT']), " +
            "https on $($values['HTTPS_PORT']) and the app on $($values['APP_PORT']).")
    }
}

# A Docker project name must be lowercase letters, digits, - and _
$project = ("$($values['PROJECT_NAME'])".ToLowerInvariant() -replace '[^a-z0-9_-]', '_').TrimStart('_', '-')
if (-not $project) {
    $project = 'docker_smartward_hub'
}
if ($project -ne $values['PROJECT_NAME']) {
    Write-Warning "PROJECT_NAME '$($values['PROJECT_NAME'])' is not a valid Docker project name, so '$project' is used."
    $values['PROJECT_NAME'] = $project
}

# --- docker-compose.yml -------------------------------------------------------
$template = [IO.File]::ReadAllText($templatePath)

# Fill every ${KEY:-default} with this hospital's value, or the template default.
# Values land inside the template's quoted YAML strings, and compose reads a
# lone $ as a variable, so \ " and $ are escaped.
$fill = [Text.RegularExpressions.MatchEvaluator] {
    param($match)
    $key = $match.Groups[1].Value
    if ($values.Contains($key)) {
        $value = "$($values[$key])"
    } elseif ($match.Groups[2].Success) {
        $value = $match.Groups[3].Value
    } else {
        return $match.Value
    }
    return $value.Replace('\', '\\').Replace('"', '\"').Replace('$', '$$')
}
$body = [regex]::Replace($template, '\$\{([A-Z0-9_]+)(:-([^}]*))?\}', $fill)

$domain = $uri.Host
if ($uri.HostNameType -eq [UriHostNameType]::Dns -and $domain -ne 'localhost') {
    $needle = '"' + $templateDomain + ':'
    if ($body.Contains($needle)) {
        $body = $body.Replace($needle, '"' + $domain + ':')
    } else {
        Write-Warning "The template's hosts entry for $templateDomain was not found, so it was left as it is."
    }
}

$stamp = Get-Date -Format 'yyyy-MM-dd HH:mm'
$header = @(
    '# ============================================================================='
    "# SmartWard 4.0 - $($values['HOSPITAL_NAME'])"
    "# Built $stamp by ""build hospital config.bat"" from docker_smartward_hub\docker-compose.yml"
    '# with the settings in hospital.env. Running the .bat again rebuilds this file, so'
    '# change hospital.env (or the template) rather than editing this file.'
    '# ============================================================================='
    ''
    "name: $($values['PROJECT_NAME'])"
    ''
    ''
) -join "`n"

# --- hospital.env ---------------------------------------------------------------
$envLines = New-Object System.Collections.Generic.List[string]
$envLines.Add('# =============================================================================')
$envLines.Add("# SmartWard 4.0 - settings for $($values['HOSPITAL_NAME'])")
$envLines.Add('# "build hospital config.bat" builds docker-compose.yml in this folder from these.')
$envLines.Add('# To change one: edit it here, run the .bat again (press Enter at each question),')
$envLines.Add('# then copy the new docker-compose.yml to the hospital''s server.')
$envLines.Add('# The database settings are not here on purpose: the database inside the image')
$envLines.Add('# is created with the template''s values, so they must stay as they are.')
$envLines.Add('# =============================================================================')
$group = $null
foreach ($setting in $schema) {
    if ($setting.Group -ne $group) {
        $envLines.Add('')
        $envLines.Add("# --- $($setting.Group)")
        $group = $setting.Group
    }
    if ($setting.Note) {
        $envLines.Add("# $($setting.Note)")
    }
    $envLines.Add("$($setting.Key)=$(Format-EnvValue "$($values[$setting.Key])")")
}
$extraKeys = @($values.Keys | Where-Object { $key = $_; -not ($schema | Where-Object { $_.Key -eq $key }) })
if ($extraKeys.Count -gt 0) {
    $envLines.Add('')
    $envLines.Add('# --- Added by hand')
    foreach ($key in $extraKeys) {
        $envLines.Add("$key=$(Format-EnvValue "$($values[$key])")")
    }
}

# --- README.txt -----------------------------------------------------------------
$readme = @(
    "SmartWard 4.0 - $($values['HOSPITAL_NAME'])"
    "Built $stamp by ""build hospital config.bat"" (docker_smartward_hub)."
    ''
    'Run it on the hospital''s server (Docker installed):'
    '  1. Copy this whole folder to the server.'
    '  2. Open a command prompt in the folder and run:'
    '       docker login            (the images are private on Docker Hub)'
    '       docker compose pull'
    '       docker compose up -d'
    "  3. Open $($values['APP_URL'])"
    ''
    'Stop:            docker compose down'
    'Update images:   docker compose pull, then docker compose up -d'
    ''
    "Ports on the server: http $($values['HTTP_PORT']), https $($values['HTTPS_PORT']), app $($values['APP_PORT']),"
    "MySQL $($values['DB_PORT']), Redis $($values['REDIS_PORT']), HL7 $($values['HL7_PORT']), ECG $($values['ECG_PORT']),"
    "B.Braun $($values['BBRAUN_PORT']), LDAP 5000."
    ''
    'To change a setting: edit hospital.env, run "build hospital config.bat" again on the'
    'build PC (it offers these values as the defaults), copy the new docker-compose.yml'
    'to the server, and run "docker compose up -d" there.'
) -join "`r`n"

# --- Write ------------------------------------------------------------------------
New-Item -ItemType Directory -Force -Path $hospitalDir | Out-Null
$utf8 = New-Object System.Text.UTF8Encoding($false)
$composePath = Join-Path $hospitalDir 'docker-compose.yml'
[IO.File]::WriteAllText($composePath, ($header + $body).Replace("`r`n", "`n"), $utf8)
[IO.File]::WriteAllText($settingsPath, (($envLines -join "`n") + "`n"), $utf8)
[IO.File]::WriteAllText((Join-Path $hospitalDir 'README.txt'), ($readme + "`r`n"), $utf8)

# Let Docker read it back, when Docker is installed here
if (Get-Command docker -ErrorAction SilentlyContinue) {
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $check = & docker compose -f $composePath config --quiet 2>&1
    $checkFailed = $LASTEXITCODE -ne 0
    $ErrorActionPreference = $previous
    if ($checkFailed) {
        Write-Warning 'Docker could not read the generated docker-compose.yml:'
        $check | ForEach-Object { Write-Host "  $_" -ForegroundColor Yellow }
    } else {
        Write-Host 'Checked with Docker: the compose file is valid.' -ForegroundColor Green
    }
}

Write-Host ''
Write-Host "Done: $hospitalDir" -ForegroundColor Cyan
Write-Host '  docker-compose.yml   run this on the hospital''s server'
Write-Host '  hospital.env         the settings; edit, then run this again'
Write-Host '  README.txt           how to run it there'
Write-Host ''
Write-Host "Address: $($values['APP_URL'])   (APP_ENV=$($values['APP_ENV']), http $($values['HTTP_PORT']), https $($values['HTTPS_PORT']))"
