<?php

require_once( "$IP/includes/DefaultSettings.php" );
require_once( "$IP/LocalSettings.php" );
$db = mysql_connect( $wgDBserver, $wgDBuser, $wgDBpassword );
mysql_select_db( $wgDBname, $db );

mysql_query( "INSERT INTO '" . $wgDBprefix . "backups' ( backup_jobid, status, timestamp, username ) VALUES ( '$argv[ 3 ]', 'NOTDONE', '$timestamp', '$argv[ 4 ]' )" );

$timestamp = time();
$dbdump = $argv[ 1 ] . "/db/" . $argv[ 2 ] . $timestamp . ".sql.gz";
$xmldump = $argv[ 1 ] . "/xml/" . $argv[ 2 ] . $timestamp . ".xml.gz";

exec( "mysqldump --default-character-set=latin1 --user=\"$wgDBuser\" --password=\"$wgDBpassword\" \"$wgDBname\" | gzip > $dbdump" );
chdir( "$IP/maitenance" );
exec( "php -d error_reporting=E_ERROR dumpBackup.php --full | gzip > \"$xmldump\"" );

mysql_query( "UPDATE '" . $wgDBprefix . "backups' SET status = 'DONE' WHERE backup_jobid = '$argv[ 3 ]'" );

?>