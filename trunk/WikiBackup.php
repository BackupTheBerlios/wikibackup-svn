if ( !defined( 'MEDIAWIKI' ) ) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "$IP/extensions/MyExtension/MyExtension.php" );
EOT;
        exit( 1 );
}
 
$dir = dirname( __FILE__ ) . '/';
 
$wgAutoloadClasses[ 'WikiBackup' ] = $dir . 'WikiBackup.php';
$wgExtensionMessagesFiles[ 'WikiBackup' ] = $dir . 'WikiBackup.i18n.php';
$wgSpecialPages[ 'Backup' ] = 'SpecialBackup';
$wgHooks[ 'LanguageGetSpecialPageAliases' ][] = 'WikiBackupLocalizedPageName';
 
function WikiBackupLocalizedPageName( &$specialPageArray, $code ) {
  # The localized title of the special page is among the messages of the extension:
  wfLoadExtensionMessages( 'WikiBackup' );
  $text = wfMsg( "backup" );
 
  # Convert from title in text form to DBKey and put it into the alias array:
  $title = Title::newFromText( $text );
  $specialPageArray['Backup'][] = $title->getDBKey();
 
  return true;
}


class SpecialBackup extends SpecialPage {
	public function MyExtension() {
                SpecialPage::SpecialPage("MyExtension");
                wfLoadExtensionMessages('MyExtension');
        }

	public function execute() {
		global $wgRequest, $wgOut, $wgUser;
		if( !$wgUser->isAllowed( 'mysql-backup' ) || $wgUser->isBlocked() ) {
			$wgOut->showErrorPage( "Permission Denied", "permissionserror" );
			return false;
		}
		if( $wgRequest->getText( 'action' ) == "backupsubmit" ) {
			return executeBackup();
		} elseif( $wgRequest->getText( 'action' ) == "backupdelete" ) {
			return deleteBackup( $wgRequest->getText( 'jobid' ) );
		} else {
			return mainBackupPage();
		}
	}

	private function executeBackup() {
		global $wgUser;
		$WikiBackup = new WikiBackup();
		$WikiBackup->execute( $wgUser->getName() );
		global $wgBackupWaitTime;
		if( $wgBackupWaitTime < 1 ) {
			sleep( 3 );
		} else {
			sleep( $wgBackupWaitTime );
		}
		return mainBackupPage( wfMsg( 'backup-submitted', $WikiBackup->backupId ), 'mw-lag-warn-normal' );
	}

	private function deleteBackup( $backupId ) {
		$WikiBackup = new WikiBackup( $backupId );
		$WikiBackup->delete( $backupId );
		return mainBackupPage( wfMsg( 'backup-deleted', $WikiBackup->backupId ), 'mw-lag-warn-normal' );
	}

	private function mainBackupPage( $msg = false, $error = 'error' ) {
		$WikiBackup = new WikiBackup();
		$retval = "";
		if( !$msg ) {
			$retval .= "<div class='$error'><p>$message</p></div>\n";
		}
		$retval .= wfMsg( "backup-header" ) . "\n";
		$retval .= "<form action='index.php?title=Special:Backup&action=backupsubmit' method='POST'>";
		$retval .= "<input name='StartJob' value='New Backup' type='submit' /></form>\n\n";
		$AllJobs = $WikiBackup->generateJobList();
		$retval .= "<ul class='special'>\n";
		foreach( $AllJobs as $Job ) {
			if( $Job[ 'status' ] == "DONE" ) {
				global $wgBackupPath, $wgBackupName;
				$DeleteButton = "<form action='index.php?title=Special:Backup&action=backupdelete' method='POST'><input type='hidden' name='jobid' value='" . $Job[ 'backup_jobid' ] . "' /><input type='submit' name='Delete' value='Delete' /></form>";
				$retval .= wfMsg( 'backup-job', date( "H:i, j F o" $Job[ 'timestamp' ] ), $Job[ 'username' ], $Job[ 'backup_jobid' ], $Job[ 'status' ], "$wgBackupPath/$wgBackupName " . $Job[ 'timestamp' ], $DeleteButton );
				$retval .= "\n";
			} else {
				$retval .= wfMsg( 'backup-job', date( "H:i, j F o" $Job[ 'timestamp' ] ), $Job[ 'username' ], $Job[ 'backup_jobid' ], $Job[ 'status' ] );
				$retval .= "\n";
			}
		}
		$retval .= "</ul>\n";
		$retval .= wfMsg( 'backup-footer' ) . "\n";
		$wgOut->addHTML( $retval );
		unset( $WikiBackup );
		return true;
	}

class WikiBackup {
	public function __construct( $backupId = false ) {
		if( !$backupId ) {
			$dbr =& wfGetDB( DB_SLAVE );
			$res = $dbr->select( "backups", "backup_jobid", "", 'WikiBackup::checkJob', array( "GROUP BY" => "backup_jobid" );
			while( $row = $dbr->fetchObject( $res ) ) {
				$rowcache = $row;
			}
			$backupId = ++$rowcache;
		}
		$this->backupId = $backupId;
	}

	public function execute( $user ) {
		global $wgBackupPath, $wgBackupName, $IP;
		execInBackground( "$IP/extensions/WikiBackup", 'DumpDatabase.php', "$wgBackupPath $wgBackupName $this->backupId $user" );
	}

	public function delete( $backupId = $this->backupId ) {
		global $wgBackupPath, $wgBackupName, $IP;
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( "backups", "timestamp", array( "backup_jobid" => $backupId ), 'WikiBackup::checkJob' );
		$timestamp = $dbr->fetchObject( $res );
		unlink( "$wgBackupPath/$wgBackupName$timestamp.sql.gz" );
		unlink( "$wgBackupPath/$wgBackupName$timestamp.xml.gz" );
		$dbr->delete( "backups", array( "backup_jobid" => $backupId ), "WikiBackup::delete" );
	}

	public function checkJob( $backupId = $this->backupId ) {
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
			if ( substr( php_uname(), 0, 7 ) == "Windows" ){
				pclose( popen( "start \"bla\" \"" . escapeshellcmd( $exe ) . "\" " . escapeshellarg( $args ), "r" ) );
			} else {
				exec( "./" . escapeshellcmd( $exe ) . " " . escapeshellarg( $args ) . " > /dev/null &" );   
			}
		}
	}

	public $backupId = false;
}