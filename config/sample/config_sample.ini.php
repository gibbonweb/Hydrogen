<?php $ini = <<<'EOF'
;*************************************************************************
; Hydrogen Configuration
;*************************************************************************

[general]
app_url = "http://example.com/app/folder"

[cache]
engine = "Memcache"

[database]
engine = "MysqlPDO"
host = "localhost"
port = 3306
socket = 
database = "hydrogenapp"
username = "hydrogenapp"
password = "password"
table_prefix = "hydro_"

[recache]
unique_name = 'XYZ'

[semaphore]
engine = "Cache"

[errorhandler]
log_errors = 1

[log]
engine = TextFile
logdir = cache
fileprefix = "hydro_"
; 0 = No logging
; 1 = Log Errors
; 2 = Log Warnings & worse
; 3 = Log Notices & worse
; 4 = Log Info & worse
; 5 = Log Debug messages & worse
loglevel = 1

[deployment]
auto_deploy = false
app_revision = 0
instance_revision = 0

;*************************************************************************
EOF;
?>