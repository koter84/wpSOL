#!/bin/bash

# check for existence of /tmp/wpsol_tmp_svn
if [ -d /tmp/wpsol_tmp_svn ]
then
	echo "tijdelijke subversion repository bestaat nog..."
	exit
fi

# update vertalingen
lang_nl=`msgfmt -o sources/languages/wpsol-nl_NL.mo -v sources/languages/wpsol-nl_NL.po 2>&1`
if [ "$lang_nl" != ".? vertaalde berichten." ]
then
	echo "Update NL: $lang_nl"
	exit
fi

## ToDo - dit werkt niet helemaal goed
# check that git is clean
git_clean=`git clean -n sources/`
if [ "$git_clean" != "" ]
then
	git status
	echo
	echo "er zijn untracked changes in de sources dir"
	exit
fi

# check that git is on branch master
git_branch=`git rev-parse --abbrev-ref HEAD`
if [ "$git_branch" != "master" ]
then
	git status
	echo
	echo "git zit niet in de master-branch"
	exit
fi

# subversion checkout
svn checkout http://plugins.svn.wordpress.org/wpsol /tmp/wpsol_tmp_svn

# rsync git release to svn trunk
rsync --recursive --delete  sources/ /tmp/wpsol_tmp_svn/trunk/

# cp readme file to svn trunk
cp assets/readme.txt /tmp/wpsol_tmp_svn/trunk/readme.txt

# cp screenshots to svn assets
cp assets/screenshot* /tmp/wpsol_tmp_svn/assets/

# changedir, remember current dir
startdir=`pwd`
cd /tmp/wpsol_tmp_svn

# add new and delete removed files from subversion
files_to_add=`svn status | grep "^\?"`
if [ "$files_to_add" != "" ]
then
	svn status | grep "^\?" | sed 's/? *//' | xargs -d'\n' svn add
fi
files_to_rm=`svn status | grep "^\!"`
if [ "$files_to_rm" != "" ]
then
	svn status | grep "^\!" | sed 's/? *//' | xargs -d'\n' svn rm
fi

# check-in the changes
svn ci -m "auto-commit"

# go back to start dir
cd $startdir

# remove temporary svn repo
rm -r /tmp/wpsol_tmp_svn

echo "Klaar!"
