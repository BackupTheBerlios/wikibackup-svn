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

$messages = array(
	'en' => array(
		'backup'                  => 'Backup',
		'backup-desc'             => 'Makes complete backups of the MediaWiki database.',
		'backup-title'            => 'Backup the database',
		'backup-submitted'        => 'Backup $1 has been submitted. Refresh the page occasionally to check its status.',
		'backup-taken'            => 'Backup $1 is already running.',
		'backup-imported'         => 'Backup $1 is being imported into the database.',
		'backup-dblock'           => 'The database has been locked because the database is being restored from one of its backups. This might take a while, so please be patient.',
		'backup-deleted'          => 'Backup $1 has been deleted.',
		'backup-header'           => 'Depending on the size of the wiki, making a backup can take a while.<br />\'\'\'Once deleted, backups cannot be recovered.\'\'\'',
		'backup-job'              => '$1 [[User:$2|$2]] ([[User talk:$2|talk]] | [[Special:Contributions/$2|contribs]]) created Backup $3. Current Status: [$5$4] $6',
		'backup-footer'           => '',
		'backup-notify'           => 'A <a href="$1/Special:Backup">backup</a> you scheduld has completed.',
		'backup-email-desc'       => 'Backup Emails:',
		'backup-email-label'      => 'Allow emails to you about completed backups you started.',
		'backup-log-create'       => 'Backup creation log',
		'backup-log-create-text'  => 'A log of all new backups.',
		'backup-log-create-entry' => 'created backup $1',
		'backup-log-delete'       => 'Backup deletion log',
		'backup-log-delete-text'  => 'A log of all backup deletions.',
		'backup-log-delete-entry' => 'deleted backup $1',
		'backup-log-import'       => 'Backup import log',
		'backup-log-import-text'  => 'A log of each time an XML dump was imported into the database.',
		'backup-log-import-entry' => 'imported backup $1'
	)
);