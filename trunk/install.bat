@echo off
echo You are going to go through a series of prompts before installing.
echo Please answer each with "yes" or "no". ("y" and"n" will also work.)

:prompts

echo The current directory you are in is now: %CD%

SET /P _rootcheck=Is this the root directory of your wiki? 

IF /I "%_rootcheck%"=="y" (
SET _dir="%CD%"
cd maintenance
goto :install
)

IF /I "%_rootcheck%"=="yes" (
SET _dir="%CD%"
cd maintenance
goto :install
)


SET /P _maintcheck=Is this the maintenance directory of your wiki? 

IF /I "%_maintcheck%"=="y" (
SET _dir="%CD%/.."
goto :install
)

IF /I "%_maintcheck%"=="yes" (
SET _dir="%CD%/.."
goto :install
)


SET /P _otherwikicheck=Is this another directory located in your wiki? 

IF /I "%_otherwikicheck%"=="y" (
echo We are changing to the immediate parent directory and restarting the prompts.
echo If you are deep within the sub-folders of your MediaWiki installation, you
echo might want to just run this script from your MediaWiki root directory instead.
cd ..
goto :prompts
)

IF /I "%_otherwikicheck%"=="yes" (
echo We are changing to the immediate parent directory and restarting the prompts.
echo If you are deep within the sub-folders of your MediaWiki installation, you
echo might want to just run this script from your MediaWiki root directory instead.
cd ..
goto :prompts
)


echo Your current working directory is not located anywhere in your MediaWiki installation.
SET /P _dir=Please enter the path to the root directory of your wiki: 
cd %_dir%
cd maintenance

:install
php installExtension.php --hotpatch --repository=http://svn.berlios.de/svnroot/repos/wikibackup/braches/0.5 WikiBackup
php patchSql.php "%_dir%/extensions/WikiBackup/wikibackup.sql"
RM --f "%_dir%/extensions/WikiBackup/wikibackup.sql"