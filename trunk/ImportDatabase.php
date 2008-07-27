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

// Catch server arguments.
$jobid              = $argv[ 1 ];
$username           = $argv[ 2 ];

$IP = ( getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : realpath( dirname( __FILE__ ) . '/../..' ) );
unset( $_SERVER );
require( "$IP/maintenance/commandLine.inc" );

$user               = User::newFromName( $username );

// Sets variable defaults if not already set
$wgBackupPath        = !isset( $wgBackupPath        ) ? "backups"     : $wgBackupPath;
$wgBackupName        = !isset( $wgBackupName        ) ? "wikibackup-" : $wgBackupName;
$wgBackupSleepTime   = !isset( $wgBackupSleepTime   ) ? 3             : $wgBackupSleepTime;
$wgEnotifBackups     = !isset( $wgEnotifBackups     ) ? true          : $wgEnotifBackups;
$wgEnableBackupMagic = !isset( $wgEnableBackupMagic ) ? true          : $wgEnableBackupMagic;

$dbw =& wfGetDB( DB_MASTER );

// Only one read query to get info.
$timestamp = $dbw->selectField( "backups", "timestamp", array( 'backup_jobid' => $jobid ) );

// Store backup info.
$dbw->update( 'backups', array( 'status' => 'IMPORTING' ), array( 'backup_jobid' => $jobid ) );
$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-IMPORTING' ), array( 'user_name' => $username ) );

//Emptying page, revision, and text tables for import.
extract( $dbw->tableNames( "page", "revision", "text" ) );
$dbw->safeQuery( "TRUNCATE TABLE $page"     );
$dbw->safeQuery( "TRUNCATE TABLE $revision" );
$dbw->safeQuery( "TRUNCATE TABLE $text"     );

$xmldump = realpath( "$IP/$wgBackupPath/$wgBackupName$timestamp" . ".xml.7z" );
$decompresscommand = "7za e -so -y \"$xmldump\"";
$descriptorspec = array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) );
$decompress = proc_open( $decompresscommand, $descriptorspec, $pipes );
$data = "";
if( is_resource( $decompress ) ) {
	fclose( $pipes[ 0 ] );
	while( !feof( $pipes[ 1 ] ) ) {
		$data .= fgets( $pipes[ 1 ], 1024 );
	}
	fclose( $pipes[ 1 ] );
	fclose( $pipes[ 2 ] );
	if( $ret = proc_close( $decompress ) != 0 || $data == "" ) { die( "Compression problem." ); echo "$data-$ret"; }
}

$maintdir = "$IP/maintenance";
$importcommand = "php importDump.php --quiet=yes";
$descriptorspec = array( array( 'pipe', 'r' ) );
$backup = proc_open( $importcommand, $descriptorspec, $pipes, $maintdir );
if( is_resource( $backup ) ) {
	fwrite( $pipes[ 0 ], $data );
	fclose( $pipes[ 0 ] );
	proc_close( $backup );
}
$dbw->update( 'backups', array( 'status' => 'IMPORTED' ), array( 'backup_jobid' => $jobid ) );
$dbw->update( 'user', array( 'user_lastbackup' => $jobid . '-IMPORTED' ), array( 'user_name' => $username ) );
