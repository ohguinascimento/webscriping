@echo off
REM Script auxiliar para executar o coletor de precos
cd /d "D:\Projetos\webscriping"
echo [%date% %time%] Executando coletor de precos... >> "data\cron.log"
"D:\xampp\php\php.exe" collector.php >> "data\cron.log" 2>&1
echo [%date% %time%] Ciclo concluido. >> "data\cron.log"
