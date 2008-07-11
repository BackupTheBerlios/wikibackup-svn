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

class SpecialBackup extends SpecialPage {
	public function SpecialBackup() {
		SpecialPage::SpecialPage( "Backup", 'mysql-backup' );
		wfLoadExtensionMessages( 'SpecialBackup' );
        }

	public function execute( $par ) {
		$this->setHeaders();
		global $wgRequest, $wgUser, $wgOut;
		if( !$wgUser->isAllowed( 'mysql-backup' ) || $wgUser->isBlocked() ) {
			$wgOut->showErrorPage( "Permission Denied", "permissionserror" );
			return false;
		}
		$wgOut->setPageTitle( wfMsg( 'backup-title' ) );
		if( $wgRequest->getText( 'action' ) == "backupsubmit" && $wgRequest->wasPosted() ) {
			return $this->executeBackup( $wgRequest->getInt( 'jobid' ), $wgRequest->getText( 'StartJob' ) );
		} elseif( $wgRequest->getText( 'action' ) == "backupdelete" && $wgRequest->wasPosted() ) {
			return $this->deleteBackup( $wgRequest->getInt( 'jobid' ) );
		} elseif( $wgRequest->getText( 'action' ) == "backupimport" && $wgRequest->wasPosted() ) {
			return $this->importBackup( $wgRequest->getInt( 'jobid' ) );
		} else {
			return $this->mainBackupPage();
		}
	}

	private function executeBackup( $jobId = false, $test = false ) {
		global $wgUser;
		$wgUser->load();
		if( $test !== "New Backup" || !$jobId ) { return $this->mainBackupPage(); }
		$WikiBackup = new WikiBackup( $jobId );
		$WikiBackup->execute( $wgUser );
		global $wgBackupWaitTime;
		if( $wgBackupWaitTime < 1 ) {
			sleep( 3 );
		} else {
			sleep( $wgBackupWaitTime );
		}
		return $this->mainBackupPage( wfMsg( 'backup-submitted', $WikiBackup->backupId ), 'mw-lag-warn-normal' );
	}

	private function importBackup( $backupId ) {
		$WikiBackup = new WikiBackup( $backupId );
		$WikiBackup->import( $backupId );
		return $this->mainBackupPage( wfMsg( 'backup-imported', $WikiBackup->backupId ), 'mw-lag-normal' );
	}

	private function deleteBackup( $backupId ) {
		$WikiBackup = new WikiBackup( $backupId );
		$WikiBackup->delete( $backupId );
		return $this->mainBackupPage( wfMsg( 'backup-deleted', $WikiBackup->backupId ), 'mw-lag-warn-normal' );
	}

	private function mainBackupPage( $msg = false, $error = 'error' ) {
		global $wgOut;
		$WikiBackup = new WikiBackup();
		$retval = "";
		if( $msg ) {
			$wgOut->addHTML( "<div class='$error'><p>$msg</p></div>\n" );
		}
		$wgOut->addWikiText( wfMsg( "backup-header" ) . "\n" );
		$wgOut->addHTML( "<form action='index.php?title=Special:Backup&action=backupsubmit' method='POST'>" );
		$wgOut->addHTML( "<input type='hidden' name='jobid' value='" . $WikiBackup->backupId . "' /><input name='StartJob' value='New Backup' type='submit' /></form>\n\n" );
		$AllJobs = $WikiBackup->generateJobList();
		$wgOut->addHTML( "<ul class='special'>\n" );
		foreach( $AllJobs as $Job ) {
			if( $Job[ 'status' ] == "DONE" ) {
				global $wgBackupPath, $wgBackupName, $wgScriptPath;
				$DeleteButton = "<form action='index.php?title=Special:Backup&action=backupdelete' method='POST'><input type='hidden' name='jobid' value='" . $Job[ 'backup_jobid' ] . "' /><input type='submit' name='Delete' value='Delete' /></form>";
				$ImportButton = "<form action='index.php?title=Special:Backup&action=backupimport' method='POST'><input type='hidden' name='jobid' value='" . $Job[ 'backup_jobid' ] . "' /><input type='submit' name='Import' value='Import' /></form>";
				$wgOut->addWikiText( wfMsg( 'backup-job', date( "H:i, j F o", $Job[ 'timestamp' ] ), $Job[ 'username' ], $Job[ 'backup_jobid' ], $Job[ 'status' ], "$wgScriptPath/$wgBackupName " . $Job[ 'timestamp' ] ) );
				$wgOut->addHTML( $DeleteButton, $ImportButton );
				$wgOut->addHTML( "\n" );
			} else {
				$wgOut->addWikiText( wfMsg( 'backup-job', date( "H:i, j F o", $Job[ 'timestamp' ] ), $Job[ 'username' ], $Job[ 'backup_jobid' ], $Job[ 'status' ] ) );
				$wgOut->addHTML( "\n" );
			}
		}
		$wgOut->addHTML( "</ul>\n" );
		$wgOut->addWikiText( wfMsg( 'backup-footer' ) . "\n" );
		unset( $WikiBackup );
		$this->clearHeaderMessage();

		return true;
	}

	private function clearHeaderMessage() {
		global $wgUser;
		$dbw =& wfGetDB( DB_MASTER );
		return $dbw->update( 'user', array( 'lastbackup' => '' ), array( 'user_id' => $wgUser->getId() ), "SpecialBackup::clearHeaderMessage" );
	}
}

class WikiBackup {
	public function __construct( $backupId = false ) {
		$dbr =& wfGetDB( DB_SLAVE );
		if( !$backupId ) {
			$res = $dbr->select( "backups", "backup_jobid", "", 'WikiBackup::checkJob', array( "GROUP BY" => "backup_jobid" ) );
			while( $row = $dbr->fetchObject( $res ) ) {
				$rowcache = $row;
			}
			$backupId = ++$rowcache;
			if( $backupId < 1 ) { $backupId = 1; }
		}
		$this->backupId = $backupId;
	}

	public function execute( $user ) {
		global $wgBackupPath, $wgBackupName, $IP, $wgDBserver, $wgDBport, $wgDBuser, $wgDBpassword, $wgDBname, $wgDBprefix, $wgBackupSleepTime, $wgEmergencyContact;
		$user->load(); $UserCanEmail = ( $user->isAllowed( 'mysql-backup' ) && $user->isEmailConfirmed() && $user->getOption( 'wpBackupEmail' ) );
		$params = "\"$wgBackupPath\" \"$wgBackupName\" \"" . $this->backupId . "\" \"" . $user->getName() . "\" \"$IP\" \"$wgDBserver\" \"$wgDBport\"  \"$wgDBuser\" \"$wgDBpassword\" \"$wgDBname\" \"$wgDBprefix\" \"$wgBackupSleepTime\" \"" . $user->getEmail() . "\" \"$UserCanEmail\"";
		$params .= " \"" . wfMsg( 'backup-email-subject' ) . "\" \"" . wfMsg( 'backup-email-message' ) . "\" \"$wgEmergencyContact\"";
		$this->execInBackground( "$IP/extensions/WikiBackup/", 'DumpDatabase.php', $params );
		$LogPage = new LogPage( 'backup' );
		$LogPage->addEntry( 'backup', Title::newFromText( "Special:Backup" ), "", array( $this->backupId ) );
	}

	public function import( $backupId = false ) {
		if( !$backupId ) { $backupId = $this->backupId(); }
		global $wgBackupPath, $wgBackupName, $IP, $wgDBserver, $wgDBport, $wgDBuser, $wgDBpassword, $wgDBname, $wgDBprefix, $wgBackupSleepTime, $wgReadOnlyFile;
		$params = "\"$wgBackupPath\" \"$wgBackupName\" \"$IP\" \"$wgDBserver\" \"$wgDBport\" \"$wgDBuser\" \"$wgDBpassword\" \"$wgDBname\" \"$wgDBprefix\" \"$wgBackupSleepTime\" \"$wgReadOnlyFile\" \"" . wfMsg( 'backup-dblock' ) . "\"";
		$this->execInBackground( "$IP/extensions/WikiBackup/", 'ImportDatabase.php', $params );
		$LogPage = new LogPage( 'backup-import' );
		$LogPage->addEntry( 'backup-import', Title::newFromText( "Special:Backup" ), "", array( $this->backupId ) );
	}

	public function delete( $backupId = false ) {
		if( !$backupId ) { $backupId = $this->backupId(); }
		global $wgBackupPath, $wgBackupName, $IP;
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( "backups", "timestamp", array( "backup_jobid" => $backupId ), 'WikiBackup::checkJob' );
		$timestamp = $dbr->fetchObject( $res );
		unlink( "$wgBackupPath/$wgBackupName$timestamp.sql.gz" );
		unlink( "$wgBackupPath/$wgBackupName$timestamp.xml.gz" );
		$dbr->delete( "backups", array( "backup_jobid" => $backupId ), "WikiBackup::delete" );
		$LogPage = new LogPage( 'backup-delete' );
		$LogPage->addEntry( 'backup-delete', Title::newFromText( "Special:Backup" ), "", array( $backupId ) );
	}

	public function checkJob( $backupId = false ) {
		if( !$backupId ) { $backupId = $this->backupId(); }
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( "backups", "status", array( "backup_jobid" => $backupId ), 'WikiBackup::checkJob' );
		$status = $dbr->fetchObject( $res );
		return $status;
	}

	public function generateJobList() {
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow( "backups", "*", "", "WikiBackup::generateJobList", array( "GROUP BY" => "timestamp" ) );
		$jobs = array();
		while( $row = $dbr->fetchObject( $res ) ) {
			$jobs[] = $row;
		}
		return $jobs;
	}

	private function execInBackground( $path, $exe, $args = "" ) {
		global $conf;
		if( file_exists( $path . $exe ) ) {
			chdir( $path );
			if ( wfIsWindows() ){
				pclose( popen( "start \"MediaWiki\" \"" . escapeshellcmd( $exe ) . "\" " . escapeshellarg( $args ), "r" ) );
			} else {
				exec( "./" . escapeshellcmd( $exe ) . " " . escapeshellarg( $args ) . " > /dev/null &" );   
			}
		} else {
			die( "File for WikiBackup extension not found." );
		}
	}

	public $backupId = false;
}