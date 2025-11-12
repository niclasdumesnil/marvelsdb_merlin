$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest 'http://127.0.0.1:8000/login' -WebSession $session -UseBasicParsing
$token = ([regex]::Match($r.Content,'name="_csrf_token" value="([^"]+)"')).Groups[1].Value
Write-Output "TOKEN: $token"
$post = Invoke-WebRequest 'http://127.0.0.1:8000/login_check' -Method POST -WebSession $session -UseBasicParsing -Body @{_username='testuser';_password='testpass';_csrf_token=$token} -ErrorAction SilentlyContinue
if ($post -ne $null) { Write-Output "POST status: $($post.StatusCode)" } else { Write-Output 'no response obj' }
$resp = Invoke-WebRequest 'http://127.0.0.1:8000/team/new' -WebSession $session -UseBasicParsing
$resp.Content | Out-File response_team_new_auth.html -Encoding utf8
Write-Output 'Saved response_team_new_auth.html'
