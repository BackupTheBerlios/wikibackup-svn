<?php

/*****************************************************************************

    This file is part of the WikiBackup MediaWiki Extension.

    The WikiBackup MediaWiki Extension is free software: you can redistribute
    it and/or modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    Foobar is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this software.  If not, see <http://www.gnu.org/licenses/>.

****************************************************************************/

$IP = ( getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : realpath( dirname( __FILE__ ) . '/../..' ) );
require( "$IP/maintenance/commandLine.inc" );

$jobid             = $argv[ 1 ];
$username          = $argv[ 2 ];
$canemail          = $argv[ 3 ];
$user              = User::newFromName( $username );

if( !$wgBackupPath || $wgBackupPath == ""   ) { $wgBackupPath = "backups"; }
if( !$wgBackupName || $wgBackupName == ""   ) { $wgBackupName = "backup-"; }
if(  $wgBackupSleepTime             == NULL ) { $wgBackupSleepTime = 3;    }

$dbw =& wfGetDB( DB_MASTER );
$dbw->insert( "backups", array( 'backup_jobid' => $jobid, 'status' => 'STARTING', 'timestamp' => $timestamp, 'username' => $username ) );
$dbw->update( "user", array( 'user_lastbackup' => $jobid . '-RUNNING' ), array( 'user_name' => $username ) );

$timestamp = time();
$xmldump = "$IP/$wgBackupPath/$wgBackupName$timestamp" . ".xml.gz";

chdir( "$IP/maitenance" );
$descriptorspec = array( array( 'pipe', r ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) );
$backup = popen( "php dumpBackup.php --full --output=\"gzip:$xmldump\"", $descriptorspec );
if( is_resource( $backup ) ) {
	fclose( $pipes[ 0 ] );
	while( $status = fgets( $pipes[ 2 ] ) ) {
		ereg_replace( "$wgDBname-$wgDBprefix ", "", $status );
		mysql_query( "UPDATE '" . $wgDBprefix . "backups' SET status='$status'" );
		sleep( $wgBackupSleepTime );
	}
	fclose( $pipes[ 1 ] );
	fclose( $pipes[ 2 ] );
	proc_close( $backup );
}
$dbw->update( 'backups', array( 'status' => 'DONE' ), array( 'backup_jobid' => $jobid ) );
$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-DONE' ), array( 'user_name' => $username ) );

if( $canemail ) {
	$to = new MailAddress( $user );
	$from = new MailAddress( $wgEmergencyContact );
	UserMailer::send( $to, $from, wfMsg( 'backup-email-subject' ), wfMsg( 'backup-email-message' ) );
}