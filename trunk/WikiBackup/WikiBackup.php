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

if ( !defined( 'MEDIAWIKI' ) ) {
        echo "WikiBackup extension";
        exit( 1 );
}

// Credits
$wgExtensionCredits[ 'specialpage' ][] = array(
        'name'           => "WikiBackup",
        'description'    => "Makes complete backups of the MediaWiki database.",
        'descriptionmsg' => "backup-desc",
        'version'        => 0.5,
        'author'         => "Tyler Romeo",
        'url'            => "http://www.mediawiki.org/wiki/Extension:WikiBackup"
);

// Sets variable defaults if not already set
$wgBackupPath        = !isset( $wgBackupPath        ) ? "backups"     : $wgBackupPath;
$wgBackupName        = !isset( $wgBackupName        ) ? "wikibackup-" : $wgBackupName;
$wgBackupSleepTime   = !isset( $wgBackupSleepTime   ) ? 3             : $wgBackupSleepTime;
$wgEnotifBackups     = !isset( $wgEnotifBackups     ) ? true          : $wgEnotifBackups;
$wgEnableBackupMagic = !isset( $wgEnableBackupMagic ) ? true          : $wgEnableBackupMagic;

// Setting up Log types.
$wgLogType[]                     = 'backup';
$wgLogNames[   'backup'        ] = 'backup-log-create';
$wgLogHeaders[ 'backup'        ] = 'backup-log-create-text';
$wgLogActions[ 'backup'        ] = 'backup-log-create-entry';

$wgLogType[]                     = 'backup-delete';
$wgLogNames[   'backup-delete' ] = 'backup-log-delete';
$wgLogHeaders[ 'backup-delete' ] = 'backup-log-delete-text';
$wgLogActions[ 'backup-delete' ] = 'backup-log-delete-entry';

$wgLogType[]                     = 'backup-import';
$wgLogNames[   'backup-import' ] = 'backup-log-import';
$wgLogHeaders[ 'backup-import' ] = 'backup-log-import-text';
$wgLogActions[ 'backup-import' ] = 'backup-log-import-entry';

/*****************************************************
                     SET HOOKS
 *****************************************************/

$dir = dirname(__FILE__) . '/';


$wgSpecialPages['Backup'] = 'SpecialBackup';

// Displays message at logon
$wgHooks['ArticleViewHeader'][] = 'fnArticleViewHeader';

// Adds notification preferences
if( $wgEnotifBackups === true ) {
	$wgHooks['InitPreferencesForm'][] = 'BackupInitPreferencesForm';
	$wgHooks['PreferencesUserInformationPanel'][] = "BackupRenderPreferencesForm";
	$wgHooks['ResetPreferences'][] = 'BackupResetPreferences';
	$wgHooks['SavePreferences'][] = 'BackupSavePreferences';
}

// Adds backup parser functions
if( $wgEnableBackupMagic === true ) {
	$wgExtensionFunctions[] = 'BackupParserSetup';
	$wgHooks['LanguageGetMagic'][] = 'BackupParserMagic';
}







/**
 * Function that checks whether a user
 * has the mysql-backup permission, and
 * has a valid e-mail address. Uses $wgUser
 * as the user.
 *
 * @return Returns true if user has valid
 *         email address and mysql-backup
 *         permission. Returns false otherwise.
 */
function canUserEmail() {
	global $wgUser;
	$wgUser->load();
	if( !$wgUser->isAllowed( 'mysql-backup' ) || !$wgUser->isEmailConfirmed() ) { return false; }
	return true;
}


/**********************************************
	     NOTIFICATION FUNCTIONS
 **********************************************/

/**
 * Checks if the user has scheduled a backup,
 * and if that backup has completed. If those
 * criteria are met, a notification box is put
 * in the output buffer.
 *
 * @return Always returns true so other hooks
 *         can run.
 */
function fnArticleViewHeader() {
	global $wgArticlePath, $wgOut, $wgUser;
	$dbr =& wfGetDB( DB_SLAVE );

	// Check status against regex seeing if "DONE" is in status.
	$lastbackup = $dbr->fetchObject( $dbr->select( 'user', 'user_lastbackup', array( 'user_id' => $wgUser->getID() ) ) );
	if( ereg( "DONE", $lastbackup->user_lastbackup ) ) {
		$wgOut->addHtml( "<div class=\"usermessage plainlinks\">" . wfMsg( 'backup-notify', $wgArticlePath ) . "</div>" );
	}
	return true;
}

/**
 * Runs when initializing the preferences form.
 * Function sets initial value for the checkbox
 * on the preferences page that allows the server
 * to email users whose backups have completed.
 *
 * @param &$prefs   The class for the Preferences
 *                  form passed by the hook.
 * @param &$request The class for the WebRequest
 *                  submitted by the user. Contains
 *                  GET and POST variables.
 *
 * @return Always returns true so other hooks can run.
 */
function BackupInitPreferencesForm( &$prefs, &$request ) {
	if( !canUserEmail() ) { return true; }
	$prefs->mToggles['backup-email'] = $request->getVal( 'wpBackupEmail' );
	return true;
}

// FIXME: Move the checkbox to the email fieldset. There is currently no hook.
/**
 * Runs when rendering preferences form. Checks 
 * {@link #canUserEmail() if user can email}, and creates
 * a checkbox in the preferences form if true. The checkbox
 * allows users to decide if they want emails sent when
 * backups they scheduled have finished.
 *
 * @param &$form Class for Preferences Form passed by hook.
 * @param &$html The HTML to add to the preferences form.
 *
 * @return Always returns true so other hooks can run.
 */
function BackupRenderPreferencesForm( &$form, &$html ) {
	if( !canUserEmail() ) { return true; }
	wfLoadExtensionMessages( 'SpecialBackup' );
	$html .= $form->tableRow( wfMsg( 'backup-email-desc' ), Xml::checkLabel( wfMsgExt( 'backup-email-label', array( 'escapenoentities' ) ), 'wpBackupEmail', 'wpBackupEmail', $prefsForm->mToggles['backup-email'] ) );
	return true;
}

/**
 * Gets current user option value for the backup-email checkbox
 * for resetting the preferences form.
 *
 * @param &$form Class for Preferences Form passed by hook.
 * @param &$user Class for the current user making the request.
 *
 * @return Always returns true so other hooks can run.
 */
function BackupResetPreferences( &$prefs, &$user ) {
	if( !canUserEmail() ) { return true; }
	$prefs->mToggles['backup-email'] = $user->getOption( 'wpBackupEmail' );
	return true;
}

/**
 * Sets user option for backup-email checkbox when saving
 * the Preferences Form.
 *
 * @return Always returns true so other hooks can run.
 */
function BackupSavePreferences( $form, $user ) {
	$user->setOption( 'wpBackupEmail', $form->mToggles['backup-email'] );
	return true;
}








/************************************
	MAGIC WORD FUNCTIONS
 ************************************/

/**
 * Sets up parser function by setting a function
 * hook on the global parser element.
 *
 * @return Always returns true so other hooks can run.
 */
function BackupParserSetup() {
	global $wgParser;
	$wgParser->setFunctionHook( 'backup', 'BackupParserRender' );
	return true;
}

/**
 * Sets aliases for the backup parser function
 * depending on the language code given. The alias
 * is obtained from the system message associated
 * with the parser function.
 *
 * @param &$magicWords An array of magic words aliases
 *                     for the parser.
 * @param $langCode    The language code the alias is
 *                     being requested in.
 *
 * @return Always returns true so other hooks can run.
 */
function BackupParserMagic( &$magicWords, $langCode ) {
	$magicWords[ 'backup' ] = array( 0, strtolower( wfMsg( 'backup' ) ) );
	return true;
}

/**
 * Renders the backup parser function. Function first checks if
 * a job id is set. If not, it defaults it to the last created
 * job id in the database. The function then queries the database
 * for information on the job id, and generates a URL for the file
 * location of the backup associated with the job id. Finally, the
 * function checks if display text is given, and defaults it to
 * the URL. The function returns the finished link in wikitext.
 *
 * @param &$parser      The class for the parser passed by the hook.
 * @param  $jobid       The job id for the backup the user is requesting.
 * @param  $displaytext The text to be displayed in the link for the
 *                      file if the user does not want just the URL.
 *
 * @return Returns the rendered link to the backup file in wikitext.
 */
function BackupParserRender( &$parser, $jobid = '', $displaytext = '' ) {
	global $wgBackupPath, $wgBackupName, $wgServer, $wgScriptPath;
	$dbr =& wfGetDB( DB_SLAVE );

	// If jobid is not set, run through database and find the last one.
	// There is probably a more efficient way of going about this.
	if( $jobid == '' || !$jobid ) {
		$res = $dbr->select( "backups", "backup_jobid", "", 'BackupParserRender', array( "GROUP BY" => "backup_jobid" ) );
		while( $row = $dbr->fetchObject( $res ) ) {
			$rowcache = $row;
		}
		$jobid = $rowcache;
	}

	// Fetch timestamp for jobid.
	$timestamp = $dbr->fetchObject( $dbr->select( 'backups', 'timestamp', array( 'backup_jobid' => $jobid ) ) );

	// If display text is not set, set it to the URL.
	if( $displaytext == '' ) {
		$displaytext = $wgServer . $wgScriptPath . "/" . $wgBackupPath . "/" . $wgBackupName . $timestamp . ".xml.gz";
	}

	// Finally, return external link in wikitext.
	return "[" . $wgServer . $wgScriptPath . "/" . $wgBackupPath . "/" . $wgBackupName . $timestamp . ".xml.gz " . htmlspecialchars( $displayText ) . "]";
}
