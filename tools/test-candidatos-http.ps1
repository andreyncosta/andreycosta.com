param([string]$BaseUrl = 'http://127.0.0.1:8097/candidatos/')
$ErrorActionPreference = 'Stop'
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
function Get-Page([string]$url = '') {
    return Invoke-WebRequest -Uri ($BaseUrl + $url) -WebSession $session -UseBasicParsing
}
function Get-Csrf($response) {
    return [regex]::Match($response.Content, 'name="csrf" value="([a-f0-9]+)"').Groups[1].Value
}
function Post-Form($form) {
    return Invoke-WebRequest -Uri $BaseUrl -Method Post -Body $form -WebSession $session -UseBasicParsing
}
function Assert($condition, $message) { if (!$condition) { throw $message }; Write-Output "OK: $message" }
$login = Get-Page
Assert ($login.Content -match 'Entrar') 'Login renderiza'
$csrf = Get-Csrf $login
$invalid = Post-Form @{ action='login'; csrf='invalid'; email='user0@example.test'; password='a-strong-password' }
Assert ($invalid.Content -match 'expirado') 'CSRF rejeitado'
$admin = Post-Form @{ action='login'; csrf=$csrf; email='user0@example.test'; password='a-strong-password' }
Assert ($admin.Content -match 'Configura') 'Login administrador'
foreach ($route in @('?view=users','?view=distribution','?view=settings','?view=history','?view=candidate_history&id=1')) {
    $response = Get-Page $route
    Assert ($response.StatusCode -eq 200 -and $response.Content -notmatch 'Operação indisponível') "Painel $route"
}
$response = Get-Page '?view=export'
Assert ($response.Headers['Content-Type'] -match 'text/csv') 'Exportação CSV'
$page = Get-Page
$preview = Post-Form @{ action='preview'; csrf=(Get-Csrf $page); kind='assign'; year='2026'; office='DEPUTADO FEDERAL'; uf='SP'; quantity='2'; mode='fixed'; 'users[]'='3' }
Assert ($preview.Content -match 'Confirmar esta') 'Prévia HTTP'
$operation = [regex]::Match($preview.Content, 'name="operation" value="([a-f0-9]+)"').Groups[1].Value
$done = Post-Form @{ action='execute'; csrf=(Get-Csrf $preview); operation=$operation }
Assert ($done.Content -match 'Lote \d') 'Confirmação HTTP'
$null = Post-Form @{action='logout'; csrf=(Get-Csrf $done)}
$login = Get-Page
$reviewer = Post-Form @{ action='login'; csrf=(Get-Csrf $login); email='user2@example.test'; password='a-strong-password' }
Assert ($reviewer.Content -match 'Minhas candidaturas') 'Login revisor'
Assert ($reviewer.Content -match 'name="ideological"' -and $reviewer.Content -match 'name="profile"') 'Seletores renderizados'
$assignment=[regex]::Match($reviewer.Content,'name="assignment" value="(\d+)"').Groups[1].Value
$version=[regex]::Match($reviewer.Content,'name="version" value="(\d+)"').Groups[1].Value
$saved = Post-Form @{ action='save'; csrf=(Get-Csrf $reviewer); assignment=$assignment; version=$version; ideological='0'; profile='0'; status='done' }
Assert ($saved.Content -match 'salva\.') 'Conclusão via formulário'
$stale = Post-Form @{ action='save'; csrf=(Get-Csrf $saved); assignment=$assignment; version=$version; ideological='1'; profile='2'; status='done' }
Assert ($stale.Content -match 'outra tela') 'Conflito de versão via HTTP'
try { $null = Get-Page '?view=users'; throw 'Área administrativa liberada ao revisor' }
catch { Assert ($_.Exception.Response.StatusCode.value__ -eq 403) 'Área administrativa negada ao revisor' }
