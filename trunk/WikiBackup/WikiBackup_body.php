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
 * @ingroup Special Page
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
	 * @param $par string The virtual subpage of the Special page
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
	 * @param $jobId int The specific jobid to run.
	 * @param $test  string The value of the submit button on
	 *               the original "New Backup" button. Used
	 *               for testing.
	 *
	 * @return bool Returns the return value of the mainBackupPage
	 *         function, which is most likely true;
	 */
	private function executeBackup( $backupId = false, $test = false ) {
		global $wgUser;
		if( $test !== "New Backup" || !$backupId ) { return $this->mainBackupPage(); }
		$WikiBackup = new WikiBackup( $backupId, $wgUser );
		$WikiBackup->execute();
		global $wgBackupWaitTime;
		sleep( $wgBackupWaitTime );
		return $this->mainBackupPage( wfMsg( 'backup-submitted', $WikiBackup->backupId ), 'mw-lag-warn-normal', true );
	}

	/**
	 * Uses the WikiBackup class to import an existent backup.
	 *
	 * @param $backupId int The ID of the backup to import.
	 *
	 * @return bool Returns the return value of the mainBackupPage
	 *         function, which is most likely true.
	 */
	private function importBackup( $backupId ) {
		global $wgUser;
		if( !is_integer( $backupId ) ) { return false; }
		$WikiBackup = new WikiBackup( $backupId, $wgUser );
		$WikiBackup->import();
		return $this->mainBackupPage( wfMsg( 'backup-imported', $WikiBackup->backupId ), 'mw-lag-normal' );
	}

	/**
	 * Uses the WikiBackup class to delete an existent backup.
	 *
	 * @param $backupId int The ID of the backup to delete.
	 *
	 * @return bool Returns the return value of the mainBackupPage
	 *         function, which is most likely true.
	 */
	private function deleteBackup( $backupId ) {
		global $wgUser;
		$WikiBackup = new WikiBackup( $backupId, $wgUser );
		$WikiBackup->delete();
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
	 * @param $msg   string The error message to show at the top of the page. Does
	 *               not show if not set.
	 * @param $error string The class for the error div that holds the error message
	 *               at the top of the page. The default it 'error'.
	 *
	 * @return bool Always returns true.
	 */ 
	private function mainBackupPage( $msg = false, $error = 'error', $disablenew = false ) {
		global $wgOut;
		$WikiBackup = new WikiBackup();
		$retval = "";
		if( $msg ) {
			$wgOut->addHTML( "<div class='$error'><p>$msg</p></div>\n" );
		}
		$wgOut->addWikiText( wfMsg( "backup-header" ) . "\n" );
		$wgOut->addHTML( "<form action='index.php?title=Special:Backup&action=backupsubmit' method='POST'>" );
		$inputHTML = "<input type='hidden' name='jobid' value='" . $WikiBackup->backupId . "' /><input name='StartJob' value='New Backup' type='submit'";
		if( $disablenew ) $inputHTML .= " disabled='disabled'";
		$inputHTML .= " /></form>\n\n";
		$wgOut->addHTML( $inputHTML );
		$AllJobs = WikiBackup::generateJobList();
		$wgOut->addHTML( "<ul class='special'>\n" );
		foreach( $AllJobs as $Job ) {
			$JobUser = User::newFromId( $Job[ 'userid' ] );
			$Job[ 'username' ] = $JobUser->getName();
			if( $Job[ 'status' ] == "DONE" || "IMPORTED" ) {
				global $wgBackupPath, $wgBackupName, $wgScriptPath, $wgServer;
				$DeleteButton = "<form action='index.php?title=Special:Backup&action=backupdelete' method='POST'><input type='hidden' name='jobid' value='" . $Job[ 'backup_jobid' ] . "' /><input type='submit' name='Delete' value='Delete' /></form>";
				$ImportButton = "<form action='index.php?title=Special:Backup&action=backupimport' method='POST'><input type='hidden' name='jobid' value='" . $Job[ 'backup_jobid' ] . "' /><input type='submit' name='Import' value='Import' /></form>";
				$wgOut->addWikiText( wfMsg( 'backup-job', date( "H:i, j F o", $Job[ 'timestamp' ] ), $Job[ 'username' ], $Job[ 'backup_jobid' ], $Job[ 'status' ], "$wgServer$wgScriptPath/$wgBackupPath/$wgBackupName" . $Job[ 'timestamp' ] . ".xml.7z" ), false );
				$wgOut->addHTML( $DeleteButton . $ImportButton );
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
	 * @return bool Returns true if the database update was successful.
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
 * @ingroup Dump
 */
class WikiBackup {

	/**
	 * Universal constructor for the class. If a backup id is
	 * given through the functions only parameter, it sets it
	 * as a class variable. If not, it queries the database for
	 * the next unused backup id, and sets that.
	 *
	 * @param $backupId int The backup id to set for the class.
	 */
	public function __construct( $backupId = false, $user = false ) {
		$dbr =& wfGetDB( DB_SLAVE );
		    if( is_integer( $backupId ) ) { /* Catch actual backup ids. No action necessary. */ }
		elseif( is_string(  $backupId ) ) { settype( "integer", $backupId ); }
		else {
			$backupId = $dbr->selectField( "backups", "MAX(backup_jobid) AS backup_jobid", "", 'WikiBackup::__construct' );
			$backupId++;
			if( $backupId < 1 || $backupId === false ) { $backupId = 1; }
		}
		$this->backupId = $backupId;

		    if( is_integer( $user ) ) { $user = User::newFromId( $user   ); }
		elseif( is_string(  $user ) ) { $user = User::newFromName( $user );
		elseif( is_object(  $user ) ) && $user instanceof User ) { /* Catch actual user objects. No action necessary. */ }
		else {
			global $wgUser;
			$user = $wgUser;
		}
		$this->user = $user;
	}

	/**
	 * Generates a list of parameters and executes a backup
	 * using the {@link WikiBackup#execInBackground( $path, $exe, $args = "" ) 
	 * execInBackground function} to execute the backup script.
	 * After starting the backup, it adds an entry to the log
	 * noting so. Before generating the parameter list, it runs
	 * the BeforeBackupCreation hook.
	 */
	public function execute() {
		global $IP;
		$UserCanEmail = ( $this->user->isAllowed( 'mysql-backup' ) && $this->user->isEmailConfirmed() && $this->user->getOption( 'wpBackupEmail' ) );
		if( !wfRunHooks( 'BeforeBackupCreation', array( $this, &$UserCanEmail, $this->user ) ) ) { return false; }
		$params = "\"DumpDatabase.php\" \"" . $this->backupId . "\" \"" . $this->user->getName() . "\" \"$UserCanEmail\"";
		$this->execInBackground( "$IP/extensions/WikiBackup/", "php", $params );
		$LogPage = new LogPage( 'backup' );
		$LogPage->addEntry( 'backup', Title::newFromText( "Special:Backup" ), "", array( $this->backupId ), $this->user );
	}

	/**
	 * Imports a specific backup into the database. Before generating
	 * a parameter list for the background script, the BeforeBackupImport
	 * hook is run.
	 */
	public function import() {
		if( !wfRunHooks( 'BeforeBackupImport', array( $this ) ) ) { return false; }
		$params = "\"ImportDatabase.php\" \"" . $this->backupId . "\" \"" . $this->user->getName() . "\"";
		$this->execInBackground( "$IP/extensions/WikiBackup/", "php", $params );
		$LogPage = new LogPage( 'backup-import' );
		$LogPage->addEntry( 'backup-import', Title::newFromText( "Special:Backup" ), "", array( $this->backupId ), $this->user );
	}

	/**
	 * Deletes the file and database entry associated with a backup.
	 * Before deleting the backup, it runs the BeforeBackupDelete
	 * hook, which can stop the deletion if it returns false.
	 */
	public function delete() {
		global $wgBackupPath, $wgBackupName, $IP;
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( "backups", "timestamp", array( "backup_jobid" => $this->backupId ), 'WikiBackup::delete' );
		$timestamp = $dbr->fetchObject( $res )->timestamp;
		if( !wfRunHooks( 'BeforeBackupDeletion', array( $this, "$wgBackupPath/$wgBackupName$timestamp.xml.gz" ) ) ) { return false; }
		if( unlink( "$IP/$wgBackupPath/$wgBackupName$timestamp.xml.7z" ) === false ) { return false; }
		$dbr->delete( "backups", array( "backup_jobid" => $this->backupId ), "WikiBackup::delete" );
		$LogPage = new LogPage( 'backup-delete' );
		$LogPage->addEntry( 'backup-delete', Title::newFromText( "Special:Backup" ), "", array( $this->backupId ), $this->user );
	}

	/**
	 * Checks the status of a certain backup job and returns it.
	 *
	 * @param $backupId int The backup id to check if different from the one
	 *                  stored within the class.
	 *
	 * @return string Returns the status of the backup job.
	 */
	public static function checkJob( $backupId ) {
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( "backups", "status", array( "backup_jobid" => $backupId ), 'WikiBackup::checkJob' );
		$status = $dbr->fetchObject( $res );
		return $status;
	}

	/**
	 * Generates a zero-point array of all open and completed backups.
	 * Each key has another array containing the backup information.
	 *
	 * @return array Returns an array of backup information.
	 */
	public static function generateJobList() {
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( "backups", "*", "", "WikiBackup::generateJobList", array( "ORDER BY" => "timestamp" ) );
		$jobs = array();
		while( $row = $dbr->fetchRow( $res ) ) {
			$jobs[] = $row;
		}
		return $jobs;
	}

	/**
	 * Runs a certain process in the background.
	 *
	 * @param $path string The physical path of the program to run.
	 * @param $exe  string The name of the program to run.
	 * @param $args mixed  The arguments to pass to the child process.
	 */
	private function execInBackground( $path, $exe, $args = "" ) {
		global $conf, $IP;
		chdir( realpath( $path ) );
		if( is_array( $args ) ) { explode( " ", $args ); }
		if ( wfIsWindows() ){
			$command = "start \"MediaWiki\"" . escapeshellcmd( $exe ) . " $args > " . wfGetNull();
		} else {
			$command = "./" . escapeshellcmd( $exe ) . " " . escapeshellcmd( $args ) . " > " . wfGetNull() . " &";
		}
		pclose( popen( $command, "r" ) );
	}

	public  $backupId = false;
	private $user     = false;
}
