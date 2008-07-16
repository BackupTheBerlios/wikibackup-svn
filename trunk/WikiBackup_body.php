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

/**
 * The main class for the Backup Special page.
 * It interacts with the WikiBackup class as
 * well as the MediaWiki interface to show job
 * lists, initiate backups, etc.
 *
 * @author Tyler Romeo
 * @version 0.5
 */
class SpecialBackup extends SpecialPage {

	/**
	 * Constructor for the SpecialBackup class.
	 * Initializes the SpecialPage base class and
	 * loads the extension messages.
	 */
	public function SpecialBackup() {
		SpecialPage::SpecialPage( "Backup", 'mysql-backup' );
		wfLoadExtensionMessages( 'SpecialBackup' );
        }

	/**
	 * Function called when generating the special page.
	 * Overloads SpecialPage::execute, and uses the action
	 * POST/GET parameter to determine what to do. Actual
	 * HTML for Special page is in
	 * {@link SpecialBackup#mainBackupPage( $msg = false, $error = 'error' )
	 *  mainBackupPage function}.
	 *
	 * @param $par The virtual subpage of the Special page
	 *             requested by the user.
	 *
	 * @return Returns bool depending on what the action is.
	 */
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

	/**
	 * Uses the WikiBackup class to initate a backup.
	 * First runs some tests on POST variables supplied
	 * by the calling function before running the backup.
	 *
	 * @param $jobId The specific jobid to run.
	 * @param $test  The value of the submit button on
	 *               the original "New Backup" button. Used
	 *               for testing.
	 *
	 * @return Returns the return value of the mainBackupPage
	 *         function, which is most likely true;
	 */
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

	/**
	 * Uses the WikiBackup class to import an existent backup.
	 *
	 * @param $backupId The ID of the backup to import.
	 *
	 * @return Returns the return value of the mainBackupPage
	 *         function, which is most likely true.
	 */
	private function importBackup( $backupId ) {
		$WikiBackup = new WikiBackup( $backupId );
		$WikiBackup->import( $backupId );
		return $this->mainBackupPage( wfMsg( 'backup-imported', $WikiBackup->backupId ), 'mw-lag-normal' );
	}

	/**
	 * Uses the WikiBackup class to delete an existent backup.
	 *
	 * @param $backupId The ID of the backup to delete.
	 *
	 * @return Returns the return value of the mainBackupPage
	 *         function, which is most likely true.
	 */
	private function deleteBackup( $backupId ) {
		$WikiBackup = new WikiBackup( $backupId );
		$WikiBackup->delete( $backupId );
		return $this->mainBackupPage( wfMsg( 'backup-deleted', $WikiBackup->backupId ), 'mw-lag-warn-normal' );
	}

	/**
	 * The function to generate the actual HTML for the special page.
	 * First generates an error message at the top using the function's
	 * parameters. Then it generates the header and new backup form.
	 * It uses the WikiBackup class to generate a job list, and then put
	 * it into HTML form. Finally, it adds the footer HTML, and uses the
	 * clearHeaderMessage function to clear any backup completion messages.
	 *
	 * @param $msg   The error message to show at the top of the page. Does
	 *               not show if not set.
	 * @param $error The class for the error div that holds the error message
	 *               at the top of the page. The default it 'error'.
	 *
	 * @return Always returns true.
	 */ 
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

	/**
	 * Changes a key in the database to stop the completed
	 * backup message from showing when the user has visited
	 * this special page.
	 *
	 * @return Returns true if the database update was successful.
	 *         False, otherwise.
	 */
	private function clearHeaderMessage() {
		global $wgUser;
		$dbw =& wfGetDB( DB_MASTER );
		return $dbw->update( 'user', array( 'user_lastbackup' => '' ), array( 'user_id' => $wgUser->getId() ), "SpecialBackup::clearHeaderMessage" );
	}
}

/**
 * This class starts, deletes, and imports database backups
 * for the MediaWiki engine.
 *
 * @author Tyler Romeo
 * @version 0.5
 */
class WikiBackup {

	/**
	 * Universal constructor for the class. If a backup id is
	 * given through the functions only parameter, it sets it
	 * as a class variable. If not, it queries the database for
	 * the next unused backup id, and sets that.
	 *
	 * @param $backupId The backup id to set for the class.
	 */
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

	/**
	 * Generates a list of parameters and executes a backup
	 * using the {@link WikiBackup#execInBackground( $path, $exe, $args = "" ) 
	 * execInBackground function} to execute the backup script.
	 * After starting the backup, it adds an entry to the log
	 * noting so. Before generating the parameter list, it runs
	 * the BeforeBackupCreation hook.
	 *
	 * @param $user The User object for the user starting the backup.
	 */
	public function execute( $user ) {
		global $wgBackupPath, $wgBackupName, $IP, $wgDBserver, $wgDBport, $wgDBuser, $wgDBpassword, $wgDBname, $wgDBprefix, $wgBackupSleepTime, $wgEmergencyContact;
		$user->load(); $UserCanEmail = ( $user->isAllowed( 'mysql-backup' ) && $user->isEmailConfirmed() && $user->getOption( 'wpBackupEmail' ) );
		if( !wfRunHooks( 'BeforeBackupCreation', array( $this, &$UserCanEmail, $user ) ) ) { return false; }
		$params = "\"" . $this->backupId . "\" \"" . $user->getName() . "\" \"$UserCanEmail\"";
		$this->execInBackground( "$IP/extensions/WikiBackup/", 'DumpDatabase.php', $params );
		$LogPage = new LogPage( 'backup' );
		$LogPage->addEntry( 'backup', Title::newFromText( "Special:Backup" ), "", array( $this->backupId ) );
	}

	/**
	 * Imports a specific backup into the database. Before generating
	 * a parameter list for the background script, the BeforeBackupImport
	 * hook is run.
	 *
	 * @param $backupId The backup id to import if different from the one
	 *                  stored within the class.
	 */
	public function import( $backupId = false ) {
		if( !$backupId ) { $backupId = $this->backupId(); }
		global $wgBackupPath, $wgBackupName, $IP, $wgDBserver, $wgDBport, $wgDBuser, $wgDBpassword, $wgDBname, $wgDBprefix, $wgBackupSleepTime, $wgReadOnlyFile;
		if( !wfRunHooks( 'BeforeBackupImport', array( $this ) ) ) { return false; }
		$params = "\"$wgBackupPath\" \"$wgBackupName\" \"$IP\" \"$wgDBserver\" \"$wgDBport\" \"$wgDBuser\" \"$wgDBpassword\" \"$wgDBname\" \"$wgDBprefix\" \"$wgBackupSleepTime\" \"$wgReadOnlyFile\" \"" . wfMsg( 'backup-dblock' ) . "\"";
		$this->execInBackground( "$IP/extensions/WikiBackup/", 'ImportDatabase.php', $params );
		$LogPage = new LogPage( 'backup-import' );
		$LogPage->addEntry( 'backup-import', Title::newFromText( "Special:Backup" ), "", array( $this->backupId ) );
	}

	/**
	 * Deletes the file and database entry associated with a backup.
	 * Before deleting the backup, it runs the BeforeBackupDelete
	 * hook, which can stop the deletion if it returns false.
	 *
	 * @param $backupId The backup id to delete if different from the one
	 *                  stored within the class.
	 */
	public function delete( $backupId = false ) {
		if( !$backupId ) { $backupId = $this->backupId(); }
		global $wgBackupPath, $wgBackupName, $IP;
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( "backups", "timestamp", array( "backup_jobid" => $backupId ), 'WikiBackup::checkJob' );
		$timestamp = $dbr->fetchObject( $res );
		if( !wfRunHooks( 'BeforeBackupDeletion', array( $this, "$wgBackupPath/$wgBackupName$timestamp.xml.gz" ) ) ) { return false; }
		unlink( "$wgBackupPath/$wgBackupName$timestamp.xml.gz" );
		$dbr->delete( "backups", array( "backup_jobid" => $backupId ), "WikiBackup::delete" );
		$LogPage = new LogPage( 'backup-delete' );
		$LogPage->addEntry( 'backup-delete', Title::newFromText( "Special:Backup" ), "", array( $backupId ) );
	}

	/**
	 * Checks the status of a certain backup job and returns it.
	 *
	 * @param $backupId The backup id to check if different from the one
	 *                  stored within the class.
	 *
	 * @return Returns the status of the backup job.
	 */
	public function checkJob( $backupId = false ) {
		if( !$backupId ) { $backupId = $this->backupId(); }
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( "backups", "status", array( "backup_jobid" => $backupId ), 'WikiBackup::checkJob' );
		$status = $dbr->fetchObject( $res );
		return $status;
	}

	/**
	 * Generates a zero-point array of all open and completed backups.
	 * Each key has another array containing the backup information.
	 *
	 * @return Returns an array of backup information.
	 */
	public function generateJobList() {
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->selectRow( "backups", "*", "", "WikiBackup::generateJobList", array( "GROUP BY" => "timestamp" ) );
		$jobs = array();
		while( $row = $dbr->fetchObject( $res ) ) {
			$jobs[] = $row;
		}
		return $jobs;
	}

	/**
	 * Runs a certain process in the background.
	 *
	 * @param $path The physical path of the program to run.
	 * @param $exe  The name of the program to run.
	 * @param $args The arguments to feed to the program.
	 */
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