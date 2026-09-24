#requires -Version 5.1
[CmdletBinding()]
param([switch]$NoPause)

Set-StrictMode -Version 2.0
$ErrorActionPreference='Stop'
$ProjectRoot=$PSScriptRoot
$EnvPath=Join-Path $ProjectRoot '.env'
$SchemaPath=Join-Path $ProjectRoot 'database\schema.sql'
$BackupDir=Join-Path $ProjectRoot 'database\backups'
$ExpectedDatabase='pasteleria_db'

function Read-Env([string]$Path){
 $values=@{}
 foreach($line in [IO.File]::ReadAllLines($Path,[Text.Encoding]::UTF8)){
  if($line.Trim() -match '^(?!#)([^=]+)=(.*)$'){$values[$matches[1].Trim()]=$matches[2].Trim().Trim('"').Trim("'")}
 }
 return $values
}
function Find-MariaTool([string]$Name){
 foreach($version in @('12.3','11.8')){$path=Join-Path $env:ProgramFiles "MariaDB $version\bin\$Name";if(Test-Path -LiteralPath $path){return $path}}
 $command=Get-Command $Name -ErrorAction SilentlyContinue
 if($command){return $command.Source}
 throw "No se encontró $Name en MariaDB 12.3 ni 11.8."
}
function Run-Tool([string]$File,[string[]]$Arguments,[string]$InputFile='',[string]$OutputFile=''){
 $argumentLine=($Arguments|ForEach-Object{'"'+($_.Replace('"','\"'))+'"'}) -join ' '
 $params=@{FilePath=$File;ArgumentList=$argumentLine;Wait=$true;PassThru=$true;NoNewWindow=$true}
 if($InputFile){$params.RedirectStandardInput=$InputFile}
 if($OutputFile){$params.RedirectStandardOutput=$OutputFile}
 $process=Start-Process @params
 if($process.ExitCode -ne 0){throw "$([IO.Path]::GetFileName($File)) terminó con código $($process.ExitCode)."}
}

try{
 Write-Host '=== RESETEO AUTOMÁTICO DE PASTELERIA_DB ===' -ForegroundColor Yellow
 if(!(Test-Path -LiteralPath $EnvPath)){throw 'No existe .env.'}
 if(!(Test-Path -LiteralPath $SchemaPath)){throw 'No existe database\schema.sql.'}
 $config=Read-Env $EnvPath
 $database=[string]$config['DB_NAME']
 if($database -cne $ExpectedDatabase){throw "Protección activada: DB_NAME debe ser exactamente '$ExpectedDatabase', pero es '$database'."}
 $hostName=if($config['DB_HOST']){[string]$config['DB_HOST']}else{'127.0.0.1'}
 $port=if($config['DB_PORT']){[string]$config['DB_PORT']}else{'3306'}
 $user=if($config['DB_USER']){[string]$config['DB_USER']}else{'root'}
 $password=[string]$config['DB_PASSWORD']
 $client=Find-MariaTool 'mariadb.exe'
 $dump=Find-MariaTool 'mariadb-dump.exe'
 $common=@('--protocol=TCP',"--host=$hostName","--port=$port","--user=$user")
 $previousPassword=$env:MYSQL_PWD
 $env:MYSQL_PWD=$password

 New-Item -ItemType Directory -Path $BackupDir -Force|Out-Null
 $backup=Join-Path $BackupDir ("pasteleria_db_antes_reset_{0}.sql" -f (Get-Date -Format 'yyyyMMdd_HHmmss'))
 Write-Host "[1/4] Creando respaldo: $backup" -ForegroundColor Cyan
 Run-Tool $dump ($common+@('--single-transaction','--routines','--triggers',$ExpectedDatabase)) '' $backup

 Write-Host '[2/4] Eliminando y recreando exclusivamente pasteleria_db...' -ForegroundColor Cyan
 Run-Tool $client ($common+@('-e',"DROP DATABASE IF EXISTS ``$ExpectedDatabase``; CREATE DATABASE ``$ExpectedDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"))

 Write-Host '[3/4] Importando el esquema limpio...' -ForegroundColor Cyan
 Run-Tool $client $common $SchemaPath

 Write-Host '[4/4] Generando una nueva clave de instalación ADMIN...' -ForegroundColor Cyan
 $installKey=Join-Path $ProjectRoot 'storage\install.key'
 Remove-Item -LiteralPath $installKey -Force -ErrorAction SilentlyContinue
 $php=(Get-Command php.exe -ErrorAction SilentlyContinue)
 if(!$php){$php=Get-Command php -ErrorAction Stop}
 & $php.Source (Join-Path $ProjectRoot 'bin\generate-install-key.php')
 if($LASTEXITCODE -ne 0){throw 'No se pudo generar la clave de instalación.'}

 Write-Host ''
 Write-Host '[OK] pasteleria_db quedó vacía y reconstruida.' -ForegroundColor Green
 Write-Host "[OK] Respaldo recuperable: $backup" -ForegroundColor Green
}
catch{Write-Host "[ERROR] $($_.Exception.Message)" -ForegroundColor Red;exit 1}
finally{
 if($null -eq $previousPassword){Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue}else{$env:MYSQL_PWD=$previousPassword}
 if(!$NoPause){[void](Read-Host 'Presiona ENTER para cerrar')}
}
