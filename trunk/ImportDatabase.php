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

// Catch server arguments.
$jobid             = $argv[ 0 ];
$username          = $argv[ 1 ];
$user              = User::newFromName( $username );

// Set defaults if variables not set.
$wgBackupPath        = ( !isset( $wgBackupPath        ) ? "backups"     );
$wgBackupName        = ( !isset( $wgBackupName        ) ? "wikibackup-" );
$wgBackupSleepTime   = ( !isset( $wgBackupSleepTime   ) ? 3             );

$dbw =& wfGetDB( DB_MASTER );

// Only one read query to get info.
$timestamp = $dbw->fetchObject( $dbw->select( "backups", "timestamp", array( 'backup_jobid' => $jobid ) ) )->timestamp;

if( !is_writable( dirname( $wgReadOnlyFile ) ) ) {
	$dbw->update( 'backups', array( 'status' => 'IMPORTING-ERROR-NOTWRITABLE' ), array( 'backup_jobid' => $jobid ) );
	$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-IMPORTING-ERROR-NOTWRITABLE' ), array( 'user_name' => $username ) );
	exit( -1 );
}

$ReadOnlyFile = @fopen( $wgReadOnlyFile, 'w' );
if ( false === $ReadOnlyFile ) {
	$ReadOnlyFile = @fopen( $wgReadOnlyFile, 'a' );
	if ( false === $ReadOnlyFile ) {
		$dbw->update( 'backups', array( 'status' => 'IMPORTING-ERROR-NOTWRITABLE' ), array( 'backup_jobid' => $jobid ) );
		$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-IMPORTING-ERROR-NOTWRITABLE' ), array( 'user_name' => $username ) );
		exit( -1 );
	}
}
wfLoadExtensionMessages( 'WikiBackup' );
fwrite( $ReadOnlyFile, wfMsg( 'backup-dblock' );

// Store backup info.
$dbw->update( 'backups', array( 'status' => 'IMPORTING' ), array( 'backup_jobid' => $jobid ) );
$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-IMPORTING' ), array( 'user_name' => $username ) );

//Emptying page, revision, and text tables for import.
$dbw->query( "TRUNCATE TABLE '" . $wgDBprefix . "page'" );
$dbw->query( "TRUNCATE TABLE '" . $wgDBprefix . "revision'" );
$dbw->query( "TRUNCATE TABLE '" . $wgDBprefix . "text'" );

$xmldump = "$IP/$wgBackupPath/$wgBackupName$timestamp" . ".xml.gz";

$javadir = "$IP/extensions/WikiBackup/java";
$importcommand = "java -server -classpath mysql-connector.jar:mwdumper.jar org.mediawiki.dumper.Dumper --output=mysql://$wgDBserver:$wgDBport/$wgDBname?user=$wgDBuser\&password=$wgDBpassword --format=sql:1.5 $xmldump";
$descriptorspec = array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) );
$backup = proc_open( $importcommand, $descriptorspec, $javadir );
if( is_resource( $backup ) ) {
	fclose( $pipes[ 0 ] );
	while( $status = fgets( $pipes[ 2 ] ) && !feof( $pipes[ 2 ] ) ) {
		ereg_replace( "$wgDBname-$wgDBprefix ", "", $status );
		$dbw->update( 'backups', array( 'status' => $status ), array( 'backup_jobid' => $jobid ) );
		sleep( $wgBackupSleepTime );
	}
	fclose( $pipes[ 1 ] );
	fclose( $pipes[ 2 ] );
	proc_close( $backup );
}
$dbw->update( 'backups', array( 'status' => 'IMPORTED' ), array( 'backup_jobid' => $jobid ) );
$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-IMPORTED' ), array( 'user_name' => $username ) );
unlink( $wgReadOnlyFile );