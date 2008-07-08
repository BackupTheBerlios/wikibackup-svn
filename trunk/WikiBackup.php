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

$dir = dirname(__FILE__) . '/';
$wgAutoloadClasses['SpecialBackup'] = $dir . 'WikiBackup_body.php';
$wgExtensionMessagesFiles['SpecialBackup'] = $dir . 'WikiBackup.i18n.php';
$wgSpecialPages['Backup'] = 'SpecialBackup';

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
if( !isset( $wgBackupPath ) ) {
	$wgBackupPath = 'backups';
}
if( !isset( $wgBackupName ) ) {
	$wgBackupName = 'wikibackup-';
}
if( !isset( $wgEnotifBackups ) ) {
	$wgEnotifBackups = true;
}

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
	$wgHooks['LanugageGetMagic'][] = 'BackupParserMagic';
}

// Checks if user can receive emails and has a valid email address.
function canUserEmail() {
	global $wgUser;
	$wgUser->load();
	if( !$wgUser->isAllowed( 'mysql-backup' ) || !$wgUser->isEmailConfirmed() ) { return false; }
	return true;
}


/**********************************************
	     NOTIFICATION FUNCTIONS
 **********************************************/

// Adds backup notice if backup is complete.
function fnArticleViewHeader( &$article ) {
	global $wgArticlePath, $wgOut;
	$dbr =& wfGetDB( DB_SLAVE );

	// Check status against regex seeing if "DONE" is in status.
	$lastbackup = $dbr->fetcjObject( $dbr->select( 'user', 'lastbackup', array( 'user_id' => $user->getID() ) ) );
	if( ereg( "DONE", $lastbackup ) ) {
		$wgOut->addHtml( "<div class=\"usermessage plainlinks\">" . wfMsg( 'backup-notify', $wgArticlePath ) . "</div>" );
	}
	return true;
}

// Hook for PreferencesForm consructor
// Initiates the checkbox.
function BackupInitPreferencesForm( &$prefs, &$request ) {
	if( !canUserEmail() ) { return true; }
	$prefs->mToggles['backup-email'] = $request->getVal( 'wpBackupEmail' );
	return true;
}

// Adds checkbox for email notifications of backup completion
// Actually adds the checkbox.
// FIXME: Move the checkbox to the email fieldset. There is currently no hook.
function BackupRenderPreferencesForm( &$form, &$html ) {
	if( !canUserEmail() ) { return true; }
	wfLoadExtensionMessages( 'SpecialBackup' );
	$html .= $form->tableRow( wfMsg( 'backup-email-desc' ), Xml::checkLabel( wfMsgExt( 'backup-email-label', array( 'escapenoentities' ) ), 'wpBackupEmail', 'wpBackupEmail', $prefsForm->mToggles['backup-email'] ) );
	return true;
}

// Hook for ResetPrefs button
function BackupResetPreferences( &$prefs, &$user ) {
	if( !canUserEmail() ) { return true; }
	$prefs->mToggles['backup-email'] = $user->getOption( 'wpBackupEmail' );
	return true;
}

// Sets option when saving preferences.
function BackupSavePreferences( $form, $user ) {
	$user->setOption( 'wpBackupEmail', $form->mToggles['backup-email'] );
	return true;
}

/************************************
	MAGIC WORD FUNCTIONS
 ************************************/

// Setting up parser function.
function BackupParserSetup() {
	global $wgParser;
	$wgParser->setFunctionHook( 'backup', 'BackupParserRender' );
	return true;
}

// Setting up parser function aliases.
function BackupParserMagic( &$magicWords, $langCode ) {
	$magicWords[ 'backup' ] = array( 0, strtolower( wfMsg( 'backup' ) ) );
	return true;
}

// Render parser function by retrieving info from DB.
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





