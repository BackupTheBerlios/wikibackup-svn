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
$wgDBuser          = $argv[ 7  ];
$wgDBpassword      = $argv[ 8  ];
$wgDBname          = $argv[ 9  ];
$wgDBprefix        = $argv[ 10 ];
$wgBackupSleepTime = $argv[ 11 ];
$email             = $argv[ 12 ];
$canemail          = $argv[ 13 ];
$subject           = $argv[ 14 ];
$message           = $argv[ 15 ];
$AdminEmail        = $argv[ 16 ];

// Set defaults if variables not set.
if( !$wgBackupPath || $wgBackupPath == "" ) { $wgBackupPath = "backups"; }
if( !$wgBackupName || $wgBackupName == "" ) { $wgBackupName = "backup-"; }
if(  $wgBackupSleepTime              < 1  ) { $wgBackupSleepTime = 3;    }

// Connect to database and store backup info.
$db = mysql_connect( $wgDBserver, $wgDBuser, $wgDBpassword );
mysql_select_db( $wgDBname, $db );
mysql_query( "INSERT INTO '" . $wgDBprefix . "backups' (backup_jobid, status, timestamp, username) VALUES ('$jobid', 'STARTING', '$timestamp', '$user')" );
mysql_query( "UPDATE '" . $wgDBprefix . "user' SET lastbackup='$jobid-RUNNING' WHERE user_name='$user'" );

$timestamp = time();
$xmldump = "$IP/$wgBackupPath/$wgBackupName$timestamp" . ".xml.gz";

chdir( "$IP/maitenance" );
$descriptorspec = array( array( 'pipe', r ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) );
$backup = popen( "php dumpBackup.php --full --output=\"gzip:$xmldump\"", $descriptorspec,  );
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
mysql_query( "UPDATE '" . $wgDBprefix . "backups' SET status='DONE' WHERE backup_jobid='$jobid'" );
mysql_query( "UPDATE '" . $wgDBprefix . "user' SET lastbackup='$argv[ 3 ]-DONE' WHERE user_name='$user'" );
if( $canemail ) {
	mail( $email, $subject, $message, "From: $AdminEmail" );
}