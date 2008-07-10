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
$wgBackupPath      = $argv[ 1  ];
$wgBackupName      = $argv[ 2  ];
$jobid             = $argv[ 3  ];
$user              = $argv[ 4  ];
$IP                = $argv[ 5  ];
$wgDBserver        = $argv[ 6  ];
$wgDBport          = $argv[ 9  ];
$wgDBuser          = $argv[ 8  ];
$wgDBpassword      = $argv[ 9  ];
$wgDBname          = $argv[ 10 ];
$wgDBprefix        = $argv[ 11 ];
$wgBackupSleepTime = $argv[ 12 ];
$timestamp         = $argv[ 13 ];
$wgReadOnlyFile    = $argv[ 14 ];

// Set defaults if variables not set.
if( !$wgBackupPath || $wgBackupPath == "" ) { $wgBackupPath = "backups"; }
if( !$wgBackupName || $wgBackupName == "" ) { $wgBackupName = "backup-"; }
if(  $wgBackupSleepTime              < 1  ) { $wgBackupSleepTime = 3;    }

if( !is_writable( dirname( $wgReadOnlyFile ) ) ) {
	mysql_query( "UPDATE '" . $wgDBprefix . "backups' SET status='IMPORTING-ERROR-NOTWRITABLE' WHERE backup_jobid='$jobid'" );
	mysql_query( "UPDATE '" . $wgDBprefix . "user' SET lastbackup='$jobid-IMPORTING-ERROR-NOTWRITABLE' WHERE user_name='$user'" );
	exit( -1 );
}

$ReadOnlyFile = @fopen( $wgReadOnlyFile, 'w' );
if ( false === $ReadOnlyFile ) {
	$ReadOnlyFile = @fopen( $wgReadOnlyFile, 'a' );
	if ( false === $ReadOnlyFile ) {
		mysql_query( "UPDATE '" . $wgDBprefix . "backups' SET status='IMPORTING-ERROR-NOTWRITABLE' WHERE backup_jobid='$jobid'" );
		mysql_query( "UPDATE '" . $wgDBprefix . "user' SET lastbackup='$jobid-IMPORTING-ERROR-NOTWRITABLE' WHERE user_name='$user'" );
		exit( -1 );
	}
}
fwrite( $ReadOnlyFile, $Message );

// Connect to database and store backup info.
$db = mysql_connect( $wgDBserver, $wgDBuser, $wgDBpassword );
mysql_select_db( $wgDBname, $db );
mysql_query( "UPDATE '" . $wgDBprefix . "backups' SET status='IMPORTING' WHERE backup_jobid='$jobid'" );
mysql_query( "UPDATE '" . $wgDBprefix . "user' SET lastbackup='$jobid-IMPORTING' WHERE user_name='$user'" );

//Emptying page, revision, and text tables for import.
mysql_query( "TRUNCATE TABLE '" . $wgDBprefix . "page'" );
mysql_query( "TRUNCATE TABLE '" . $wgDBprefix . "revision'" );
mysql_query( "TRUNCATE TABLE '" . $wgDBprefix . "text'" );
$xmldump = "$IP/$wgBackupPath/$wgBackupName$timestamp" . ".xml.gz";

chdir( "$IP/extensions/WikiBackup/java" );
$importcommand = "java -server -classpath mysql-connector.jar:mwdumper.jar org.mediawiki.dumper.Dumper --output=mysql://$wgDBserver:$wgDBport/$wgDBname?user=$wgDBuser\&password=$wgDBpassword --format=sql:1.5 $xmldump";
$descriptorspec = array( array( 'pipe', r ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) );
$backup = popen( $importcommand, $descriptorspec );
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
mysql_query( "UPDATE '" . $wgDBprefix . "backups' SET status='IMPORTED' WHERE backup_jobid='$jobid'" );
mysql_query( "UPDATE '" . $wgDBprefix . "user' SET lastbackup='$jobid-IMPORTED' WHERE user_name='$user'" );
unlink( $wgReadOnlyFile );