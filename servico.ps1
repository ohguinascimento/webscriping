$Action = New-ScheduledTaskAction `
    -Execute "D:\xampp\php\php.exe" `
    -Argument "worker.php --interval=30" `
    -WorkingDirectory "D:\Projetos\webscriping"

$Trigger = New-ScheduledTaskTrigger -AtStartup

$Principal = New-ScheduledTaskPrincipal `
    -UserId "NT AUTHORITY\SYSTEM" `
    -LogonType ServiceAccount `
    -RunLevel Highest

$Settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit (New-TimeSpan -Days 0)

Register-ScheduledTask `
    -TaskName "PriceTrackerService" `
    -Action $Action `
    -Trigger $Trigger `
    -Principal $Principal `
    -Settings $Settings -Force

# Inicia imediatamente
Start-ScheduledTask -TaskName "PriceTrackerService"
