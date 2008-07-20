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

$jobid             = $argv[ 1 ];
$username          = $argv[ 2 ];
$canemail          = $argv[ 3 ];
$IP = ( getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : realpath( dirname( __FILE__ ) . '/../..' ) );
unset( $_SERVER );
require( "$IP/maintenance/commandLine.inc" );

$user              = User::newFromName( $username );

// Sets variable defaults if not already set
$wgBackupPath        = !isset( $wgBackupPath        ) ? "backups"     : $wgBackupPath;
$wgBackupName        = !isset( $wgBackupName        ) ? "wikibackup-" : $wgBackupName;
$wgBackupSleepTime   = !isset( $wgBackupSleepTime   ) ? 3             : $wgBackupSleepTime;
$wgEnotifBackups     = !isset( $wgEnotifBackups     ) ? true          : $wgEnotifBackups;
$wgEnableBackupMagic = !isset( $wgEnableBackupMagic ) ? true          : $wgEnableBackupMagic;

$timestamp = time();

$dbw =& wfGetDB( DB_MASTER );
$dbw->insert( "backups", array( 'backup_jobid' => $jobid, 'status' => 'STARTING', 'timestamp' => $timestamp, 'userid' => $user->getId() ) );
$dbw->update( "user", array( 'user_lastbackup' => $jobid . '-RUNNING' ), array( 'user_id' => $user->getId() ) );

$xmldump = "$IP/$wgBackupPath/$wgBackupName$timestamp" . ".xml.gz";

$maintdir = "$IP/maintenance";
$descriptorspec = array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) );
$backup = proc_open( "php dumpBackup.php --full", $descriptorspec, $pipes, $maintdir );
if( is_resource( $backup ) ) {
	fclose( $pipes[ 0 ] );
	$data = "";
	while( !feof( $pipes[ 1 ] ) ) { $data .= fgets( $pipes[ 1 ], 1024 ); }
	fclose( $pipes[ 1 ] );
	fclose( $pipes[ 2 ] );
	proc_close( $backup );
}

unset( $descriptorspec );
$descriptorspec = array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) );
$command = "7za a -mx=9 -scsUTF-8 -si$wgBackupName$timestamp.xml -ssw -y $IP/$wgBackupPath/$wgBackupName$timestamp.xml.7z";
echo $command;
$compression = proc_open( $command, $descriptorspec, $pipes, realpath( dirname( __FILE__ ) ) );
if( is_resource( $compression ) ) {
	fwrite( $pipes[ 0 ], $data );
	fclose( $pipes[ 0 ] );
	fclose( $pipes[ 1 ] );
	fclose( $pipes[ 2 ] );
	$retval = proc_close( $compression );
}

switch( $retval ) {
	// Indicates completed compression of backup.
	case 0:
		$dbw->update( 'backups', array( 'status' => 'DONE' ), array( 'backup_jobid' => $jobid ) );
		$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-DONE' ), array( 'user_id' => $user->getId() ) );
		break;

	// Indicates backup completed with error, i.e. certain files not compressed for some reason. Since there is only one file, this is still bad.
	case 1:
		$dbw->update( 'backups', array( 'status' => 'ERROR-NONFATAL' ), array( 'backup_jobid' => $jobid ) );
		$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-ERROR-NONFATAL' ), array( 'user_id' => $user->getId ) );
		break;

	// Indicates a fatal error for some reason or another.
	case 2:
		$dbw->update( 'backups', array( 'status' => 'ERROR-FATAL' ), array( 'backup_jobid' => $jobid ) );
		$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-ERROR-FATAL' ), array( 'user_id' => $user->getId ) );
		break;

	// Indicates a command-line error, meaning there is something wrong in the code.
	case 7:
		$dbw->update( 'backups', array( 'status' => 'ERROR-CODE' ), array( 'backup_jobid' => $jobid ) );
		$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-ERROR-CODE' ), array( 'user_id' => $user->getId ) );
		break;

	// Indicates there is not enough memory for the operation.
	case 8:
		$dbw->update( 'backups', array( 'status' => 'ERROR-MEMORY' ), array( 'backup_jobid' => $jobid ) );
		$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-ERROR-MEMORY' ), array( 'user_id' => $user->getId ) );
		break;

	// Indicates the process was stopped by the user for some reason or another.
	case 255:
		$dbw->update( 'backups', array( 'status' => 'ERROR-USER' ), array( 'backup_jobid' => $jobid ) );
		$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-ERROR-USER' ), array( 'user_id' => $user->getId ) );
		break;

	// Catch-all for unknown error numbers.
	default:
		$dbw->update( 'backups', array( 'status' => 'ERROR-UNKNOWN' ), array( 'backup_jobid' => $jobid ) );
		$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-ERROR-UNKNOWN' ), array( 'user_id' => $user->getId ) );
		break;
}

if( $canemail ) {
	$to = new MailAddress( $user );
	$from = new MailAddress( $wgEmergencyContact );
	UserMailer::send( $to, $from, wfMsg( 'backup-email-subject' ), wfMsg( 'backup-email-message' ) );
}