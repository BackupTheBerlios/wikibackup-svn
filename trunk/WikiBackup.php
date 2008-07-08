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

$wgExtensionCredits[ 'specialpage' ][] = array(
        'name'           => "WikiBackup",
        'description'    => "Makes complete backups of the MediaWiki database.",
        'descriptionmsg' => "backup-desc",
        'version'        => 0.5,
        'author'         => "Tyler Romeo",
        'url'            => "http://www.mediawiki.org/wiki/Extension:WikiBackup"
 );

$wgEnotifBackups = true;

// Displays message at logon
$wgHooks['UserLoginComplete'][] = 'fnBackupNotify';
// Adds notification preferences
global $wgEnotifBackups;
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
	EMAIL NOTIFICATION FUNCTIONS
 **********************************************/

// Adds backup notice if backup is complete.
function fnBackupNotify( &$user, &$output ) {
	global $wgArticlePath;
	$dbr =& wfGetDB( DB_SLAVE );
	$lastbackup = $dbr->fetcjObject( $dbr->select( 'user', 'lastbackup', array( 'user_id' => $user->getID() ) ) );
	if( ereg( "DONE", $lastbackup ) ) {
		$output .= "<div class=\"usermessage plainlinks\">" . wfMsg( 'backup-notify', $wgArticlePath ) . "</div>";
	}
	return true;
}

// Hook for PreferencesForm consructor
function BackupInitPreferencesForm( &$prefs, &$request ) {
	if( !canUserEmail() ) { return true; }
	$prefs->mToggles['backup-email'] = $request->getVal( 'wpBackupEmail' );
	return true;
}

// Adds checkbox for email notifications of backup completion
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

function BackupSavePreferences( $form, $user ) {
	$user->setOption( 'wpBackupEmail', $form->mToggles['backup-email'] );
	return true;
}

/************************************
	MAGIC WORD FUNCTIONS
 ************************************/

function BackupParserSetup() {
	global $wgParser;
	$wgParser->setFunctionHook( 'backup', 'BackupParserRender' );
	return true;
}

function BackupParserMagic( &$magicWords, $langCode ) {
	$magicWords[ 'backup' ] = array( 0, strtolower( wfMsg( 'backup' ) ) );
	return true;
}

function BackupParserRender( &$parser, $jobid = '', $displaytext = '' ) {
	global $wgBackupPath, $wgBackupName;
	$dbr =& wfGetDB( DB_SLAVE );
	if( $jobid == '' || !$jobid ) {
		$res = $dbr->select( "backups", "backup_jobid", "", 'BackupParserRender', array( "GROUP BY" => "backup_jobid" ) );
		while( $row = $dbr->fetchObject( $res ) ) {
			$rowcache = $row;
		}
		$jobid = $rowcache;
	}
	$timestamp = $dbr->fetchObject( $dbr->select( 'backups', 'timestamp', array( 'backup_jobid' => $jobid ) ) );
	if( $displaytext != '' ) { $displayText = " " . $displayText; }
		return "[" . $wgBackupPath . "/" . $wgBackupName . $timestamp . ".xml.gz" . htmlspecialchars( $displayText ) . "]";
}