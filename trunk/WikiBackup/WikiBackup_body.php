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

/** @class SpecialBackup WikiBackup_body.php "WikiBackup/WikiBackup_body.php"
 * @brief    The class for the Special:Backup page.
 * @detailed The main class for the Backup Special page.
 *           It interacts with the WikiBackup class as
 *           to show job lists, initiate backups, etc.
 *
 * @author Tyler Romeo
 * @version 0.5
 * @ingroup Special Page
 *
 * @warning This class has not been officially tested, and should not be
 *          used until it has been fully debugged.
 */
class SpecialBackup extends SpecialPage {

	/** @fn void SpecialBackup()
	 * @public
	 * @brief Constructor for the SpecialBackup class.
	 * @detailed Initiates the SpecialBackup object, and
	 *           loads the extension messages.
	 */
	public function SpecialBackup() {
		SpecialPage::SpecialPage( "Backup", 'mysql-backup' );
		wfLoadExtensionMessages( 'SpecialBackup' );
        }

	/** @fn bool execute( $par )
	 * @brief    The main function called when Special:Backup is accessed.
	 * @detailed Function called when generating the special page.
	 *           Overloads SpecialPage::execute, and uses the "action"
	 *           POST/GET parameter to determine what to do. Actual
	 *           HTML for Special page is in
	 *           {@link SpecialBackup#mainBackupPage( $msg = false, $error = 'error' )
	 *           mainBackupPage function}.
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
		if( !$wgRequest->wasPosted() ) { return $this->mainBackupPage(); }
		if( $wgRequest->getText( 'action' ) == "backupsubmit" ) {
			return $this->executeBackup( $wgRequest->getInt( 'jobid' ), $wgRequest->getText( 'StartJob' ) );
		} elseif( $wgRequest->getText( 'action' ) == "backupdelete" ) {
			return $this->deleteBackup( $wgRequest->getInt( 'jobid' ) );
		} elseif( $wgRequest->getText( 'action' ) == "backupimport" ) {
			return $this->importBackup( $wgRequest->getInt( 'jobid' ) );
		} else {
			return $this->mainBackupPage( wfMsg( "backup-invalidaction", $wgRequest->getText( 'action' ) ) );
		}
	}

	/** @fn bool executeBackup( $backupId = false, $test = false )
	 * @private
	 * @brief    Begins the creation of an XML database dump.
	 * @detailed Uses the WikiBackup class to initate a backup.
	 *           First runs some tests on POST variables supplied
	 *           by the calling function before running the backup.
	 *
	 * @param $jobId int The specific jobid to run.
	 * @param $test  string The value of the submit button on
	 *               the original "New Backup" button. Used
	 *               for validity testing.
	 *
	 * @return bool Returns the return value of the mainBackupPage
	 *         function, which is most likely true;
	 */
	private function executeBackup( $backupId = false, $test = false ) {
		global $wgUser, $wgBackupWaitTime;
		if( $test !== "New Backup" || !$backupId ) return $this->mainBackupPage();
		$WikiBackup = new WikiBackup( $backupId, $wgUser );
		if( $WikiBackup === false ) return false;
		$html = $WikiBackup->execute();
		if( !is_bool( $html ) ) $wgOut->addHTML( $html );
		sleep( $wgBackupWaitTime );
		return $this->mainBackupPage( wfMsg( 'backup-submitted', $WikiBackup->backupId ), 'mw-lag-warn-normal', true );
	}

	/** @fn bool importBackup( $backupId )
	 * @private
	 * @brief    Uses the WikiBackup class to import an existent backup.
	 * @detailed Initiates a WikiBackup class, loading @a{$backupId} as
	 *           the backup id, then imports the backup into the database.
	 *
	 * @param $backupId int The ID of the backup to import.
	 *
	 * @return bool Returns the return value of the mainBackupPage
	 *         function, which is most likely true.
	 */
	private function importBackup( $backupId ) {
		global $wgUser;
		if( !is_integer( $backupId ) ) return false;
		$WikiBackup = new WikiBackup( $backupId, $wgUser );
		if( $WikiBackup === false ) return false;
		$html = $WikiBackup->import();
		if( !is_bool( $html ) ) $wgOut->addHTML( $html );
		return $this->mainBackupPage( wfMsg( 'backup-imported', $WikiBackup->backupId ), 'mw-lag-normal' );
	}

	/** @fn bool deleteBackup( $backupId )
	 * @private
	 * @brief    Uses the WikiBackup class to delete an existent backup.
	 * @detailed It initiates a WikiBackup object, loading @a{$backupId}
	 *           as the backup id, then deletes that backup.
	 *
	 * @param $backupId int The ID of the backup to delete.
	 *
	 * @return bool Returns the return value of the mainBackupPage
	 *         function, which is most likely true.
	 */
	private function deleteBackup( $backupId ) {
		global $wgUser;
		$WikiBackup = new WikiBackup( $backupId, $wgUser );
		if( $WikiBackup === false ) return false;
		$html = $WikiBackup->delete();
		if( !is_bool( $html ) ) $wgOut->addHTML( $html );
		return $this->mainBackupPage( wfMsg( 'backup-deleted', $WikiBackup->backupId ), 'mw-lag-warn-normal' );
	}

	/** @fn bool mainBackupPage( $msg = false, $error = 'error', $disablenew = false )
	 * @private
	 * @brief    Displays the main page for Special:Backup.
	 * @detailed The function to generate the actual HTML for the special page.
	 *           First generates an error message at the top using the function's
	 *           parameters. Then it generates the header and new backup form.
	 *           It uses the WikiBackup class to generate a job list, and then put
	 *           it into HTML form. Finally, it adds the footer HTML, and uses the
	 *           clearHeaderMessage function to clear any backup completion messages.
	 *
	 * @param[in] $msg        string The error message to show at the top of the page. Does
	 *                        not show if not set.
	 * @param[in] $error      string The class for the error div that holds the error message
	 *                        at the top of the page. The default it 'error'.
	 * @param[in] $disablenew bool If true, the "New Backup" button will be disabled.
	 *
	 * @return bool Always returns true.
	 */ 
	private function mainBackupPage( $msg = false, $error = 'error', $disablenew = false ) {
		global $wgOut;
		$WikiBackup = new WikiBackup();
		if( $WikiBackup === false ) return false;
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
		if( !$this->clearHeaderMessage() ) return false;
		return true;
	}

	/** @fn bool clearHeaderMessage()
	 * @private
	 * @brief    Clears the complete backup notification
	 * @detailed Changes a key in the database to stop the completed
	 *           backup message from showing when the user has visited
	 *           this special page.
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

/** @class WikiBackup WikiBackup_body.php "WikiBackup/WikiBackup_body.php"
 * @brief    This class starts, deletes, and imports database backups
 *           for the MediaWiki engine.
 * @detailed This class runs background scripts to assist in the creation,
 *           importing, and deletion of XML database dumps for the
 *           MediaWiki software.
 *
 * @author Tyler Romeo
 * @version 0.5
 * @ingroup Dump
 *
 * @warning This class has not been officially tested, and should not be
 *          used until it has been fully debugged.
 */
class WikiBackup {

	/** @fn void __contruct( $backupId = false, $user = false )
	 * @public
	 * @brief    Quick constructor for the class. Stores member variables.
	 * @detailed Universal constructor for the class. If a backup id is
	 *           given through the functions only parameter, it sets it
	 *           as a class variable. If not, it queries the database for
	 *           the next unused backup id, and sets that.
	 *
	 * @param[in] $backupId int   The backup id to set for the class.
	 * @param[in] $user     mixed Either a username, user id, or User object.
	 */
	public function __construct( $backupId = false, $user = false ) {
		$dbr =& wfGetDB( DB_SLAVE );
		    if( is_integer( $backupId ) ) { /* Catch actual backup ids. No action necessary. */ }
		elseif( is_string(  $backupId ) ) { $backupId = (integer) $backupId; }
		else {
			$backupId = $dbr->selectField( "backups", "MAX(backup_jobid) AS backup_jobid", "", 'WikiBackup::__construct' );
			$backupId++;
			if( $backupId < 1 || $backupId === false ) { $backupId = 1; }
		}
		$this->backupId = $backupId;

		    if( is_integer( $user ) )  $user = User::newFromId( $user   );
		elseif( is_string(  $user ) )  $user = User::newFromName( $user );
		elseif( is_object(  $user ) && $user instanceof User ) { /* Catch actual user objects. No action necessary. */ }
		else {
			global $wgUser;
			$user = $wgUser;
		}
		$this->user = $user;
	}

	/** @fn void execute()
	 * @public
	 * @brief    Generates an XML database dump as a backup.
	 * @detailed Executes a background script to generate
	 *           an XML dump of the MediaWiki database.
	 *           Before running, the BeforeBackupCreation
	 *           hook is run.
	 *
	 * @return Returns false if hook stopped import, true otherwise.
	 */
	public function execute() {
		global $IP;
		$UserCanEmail = (bool) ( $this->user->isAllowed( 'mysql-backup' ) && $this->user->isEmailConfirmed() && $this->user->getOption( 'wpBackupEmail' ) );
		if( !wfRunHooks( 'BeforeBackupCreation', array( $this, &$UserCanEmail, &$html ) ) ) return $html;
		$params = array( "DumpDatabase.php", $this->backupId, $this->user->getName(), $UserCanEmail );
		$this->processStart( "$IP/extensions/WikiBackup/", "php", $params, false, $stdout = false, $stderr = false, NULL, true );
		$LogPage = new LogPage( 'backup' );
		$LogPage->addEntry( 'backup', Title::newFromText( "Special:Backup" ), "", array( $this->backupId ), $this->user );
		return true;
	}

	/** @fn void import()
	 * @public
	 * @import   Imports a database dump into the database.
	 * @detailed Imports a specific backup into the database. Before generating
	 *           a parameter list for the background script, the BeforeBackupImport
	 *           hook is run.
	 *
	 * @return Returns false if hook stopped import, true otherwise.
	 */
	public function import() {
		if( !wfRunHooks( 'BeforeBackupImport', array( $this, &$html ) ) ) return $html;
		$params = array( "ImportDatabase.php", $this->backupId, $this->user->getName() );
		$this->processStart( "$IP/extensions/WikiBackup/", "php", $params, false, $stdout = false, $stderr = false, NULL, true );
		$LogPage = new LogPage( 'backup-import' );
		$LogPage->addEntry( 'backup-import', Title::newFromText( "Special:Backup" ), "", array( $this->backupId ), $this->user );
		return true;
	}

	/** @fn bool delete()
	 * @public
	 * @brief    Deletes a certain database backup.
	 * @detailed Deletes the file and database entry associated with a backup.
	 *           Before deleting the backup, it runs the BeforeBackupDelete
	 *           hook, which can stop the deletion if it returns false.
	 *
	 * @return Returns true if successful, false otherwise.
	 */
	public function delete() {
		global $wgBackupPath, $wgBackupName, $IP;
		$dbr =& wfGetDB( DB_SLAVE );
		$timestamp = $dbr->selectField( "backups", "timestamp", array( "backup_jobid" => $this->backupId ), 'WikiBackup::delete' );
		if( !$timestamp ) return false;
		if( !wfRunHooks( 'BeforeBackupDeletion', array( $this, "$wgBackupPath/$wgBackupName$timestamp.xml.gz", &$html ) ) ) return $html;
		if( unlink( "$IP/$wgBackupPath/$wgBackupName$timestamp.xml.7z" ) === false ) { return false; }
		if( !$dbr->delete( "backups", array( "backup_jobid" => $this->backupId ), "WikiBackup::delete" ) ) return false;
		$LogPage = new LogPage( 'backup-delete' );
		$LogPage->addEntry( 'backup-delete', Title::newFromText( "Special:Backup" ), "", array( $this->backupId ), $this->user );
		return true;
	}

	/** @fn string checkJob( $backupId )
	 * @public
	 * @deprecated
	 * @brief    Checks the status of a certain backup job and returns it.
	 * @detailed Queries the database for the status of a specific backup,
	 *           and returns the string directly.
	 *
	 * @param[in] $backupId int The backup id to check if different from the one
	 *                  stored within the class.
	 *
	 * @return string Returns the status of the backup job.
	 */
	public static function checkJob( $backupId ) {
		if( !is_integer( $backupId ) || $backupId < 1 ) { return false; }
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( "backups", "status", array( "backup_jobid" => $backupId ), 'WikiBackup::checkJob' );
		$status = $dbr->fetchObject( $res );
		return $status;
	}

	/** @fn array generateJobList()
	 * @public
	 * @brief    Generates an array of all open and completed backups.
	 * @detailed Queries the database for information on all open and
	 *           completed backups. The information for each job is
	 *           stored in an array, and each job array is stored in
	 *           a zero-point based parent array.
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

	/** @fn int processStart( $path = getcwd(), $cmd = false, $args = NULL, $stdin = false, &$stdout = false, &$stderr = false, $environment = NULL, 	$background = false )
	 * @private
	 * @brief    Runs a given command using proc_open(). If no command is given, it can also be
	 *           used as an alias for chdir().
	 * @detailed Runs the given command @a{$cmd} located in path @a{$path}, and passes @a{$stdin}
	 *           to the command's STDIN and @a{$environment} as its environment variables. It then
	 *           stores the command's STDOUT in @a{&$stdout} and STDERR in @a{&$stderr}. If
	 *           @a{$background} is set to true, the process is run as a daemon, as to not interrupt
	 *           the main PHP script.
	 * @todo     Insert support for nonstandard pipes.
	 *
	 * @param[in]   $path        string A path to the desired command. Can be absolute or relative.
	 * @param[in]   $cmd         string The command to run in shell.
	 * @param[in]   $args        mixed  Either a string or array of arguments to feed to the child process.
	 * @param[in]   $stdin       string Any data to feed to the child process's STDIN.
	 * @param[out] &$stdout      mixed  A variable to store any data given by the child process's STDOUT.
	 * @param[out] &$stderr      mixed  A variable to store any data given by the child process's STDERR.
	 * @param[in]   $environment array  An array of environment variables to feed to the child process.
	 * @param[in]   $background  bool   Whether or not to run the child process as a daemon.
	 *
	 * @throw  MWException An error with any of the parameters.
	 *
	 * @return int Returns the return code of the child process.
	 */
	private function processStart( $path = false, $cmd = false, $args = NULL, $stdin = false, &$stdout = false, &$stderr = false, $environment = NULL, $background = false ) {
		try {
			$olddir = getcwd();
			if( !$path ) $path = $olddir;

			// BEGIN VARIABLE PROCESSING

			// $path: Check and remove ending slash; change to absolute path.
			//	  Then check if directory exists, etc.
			if( !is_string( $path ) ) { return false; }
			if( substr( $path, -1 ) == "/" || "\\" ) {
				$path = substr( $path, 0, -1 );
			}
			$path = realpath( $path );

			if( !is_dir( $path ) || !chdir( $path ) ) { throw new MWException( "Bad directory" ); }



			// $cmd: Check for array (to execute multiple commands); escape all.
			// No command just leaves changed directory and exits.
			if( $cmd === false ) { return true; }
			if( !is_string( $cmd ) ) {
				chdir( $olddir );
				throw new MWException( "Bad command." );
			}
			$cmd = escapeshellcmd( $cmd );



			// $args: Process different variable types and escape all arguments.
			//        If non-array, check for quotations, then explode.
			if( is_string( $args ) ) {
				if( ereg( "[\"\']", $args ) === false ) {
					$args = explode( " ", $args );
				} else {
					$args = preg_split( "/[\"\']/ /[\"\']/", $args );
				}
			}

			// Means that $args is an int, etc., which is not acceptable.
			if( !is_array( $args ) && !is_null( $args ) ) {
				chdir( $olddir );
				throw new MWException( "Bad arguments." );
			}
			foreach( $args as &$arg ) { $arg = escapeshellarg( $arg ); }



			// $stdin: Must be a scalar
			if( !is_scalar( $stdin ) && $stdin !== false ) { return false; }

			// $stdout, $stderr: Must be changed to blank strings.
			if( $stdout !== false ) { $stdout = ""; }
			if( $stderr !== false ) { $stderr = ""; }



			// $environment: Must be array of environment variables.
			if( !is_array( $environment ) && !is_null( $environment ) ) { 
				throw new MWException( "Bad environment variables array." );
			}





			// BEGIN ACTUAL COMMAND PROCESSING
			if( $background ) {
				if( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
					$cmd = "start \"background process\" $cmd " . implode( " ", $args );
				} else {
					$cmd = "$cmd " . implode( " ", $args ) . " > /dev/null &";
				}
			} else {
				$cmd = "$cmd " . implode( " ", $args );
			}

			$pipes = array();
			$descriptorspec = array();
			$piperef = array();
			if( $stdin  !== false ) {
				$descriptorspec[ 0 ] = array( "pipe", "r" );
				$piperef[] = "stdin";
			}
			if( $stdout !== false ) {
				$descriptorspec[ 1 ] = array( "pipe", "w" );
				$piperef[] = "stdout";
			}
			if( $stderr !== false ) {
				$descriptorspec[ 2 ] = array( "pipe", "w" );
				$piperef[] = "stderr";
			}

			$command = proc_open( $cmd, $descriptorspec, $pipes, $path, $environment );
			if( is_resource( $command ) ) {
				foreach( $piperef as $key => $streamsource ) {
					switch( $streamsource ) {
						case "stdin":
							fwrite( $pipes[ $key ], $stdin );
							break;
						case "stdout":
							while( !feof( $pipes[ $key ] ) ) {
								$stdout .= fgets( $pipes[ $key ] );
							}
							break;
						case "stderr":
							while( !feof( $pipes[ $key ] ) ) {
								$stderr .= fgets( $pipes[ $key ] );
							}
							break;
						default:
							break;
					}
					fclose( $pipes[ $key ] );
				}
				$retval = proc_close( $command );
			} else { throw new MWException( "Bad process." ); }
	
			chdir( $olddir );
			return $retval;
		} catch( Exception $error ) {
			$error->reportHTML();
		}
	}

	public  $backupId = false;
	private $user     = false;
}

?>
