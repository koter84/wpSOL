#!/bin/bash

# check for existence of /tmp/wpsol_tmp_svn
if [ -d /tmp/wpsol_tmp_svn ]
then
	echo "tijdelijke subversion repository bestaat nog..."
	exit
fi

# check for existence of /tmp/i18ntools
if [ -d /tmp/i18ntools ]
then
	rm -r /tmp/i18ntools
fi

# update vertalingen
# - .pot file genereren
svn checkout http://i18n.svn.wordpress.org/tools/trunk/ /tmp/i18ntools > /dev/null

# - fail als svn checkout niet goed is gegaan !
if [ ! -f /tmp/i18ntools/makepot.php ]
then
	echo "helaas i18ntools-checkout failed, stop."
	exit
fi
# - fix wat 'foutjes' in makepot.php
sed -i s/\'trunk\'/\'sources\'/ /tmp/i18ntools/makepot.php
sed -i s/\'Y-m-d\ H:i:s+00:00\'/\'Y-m-d\ 00:00:00+00:00\'/ /tmp/i18ntools/makepot.php
php /tmp/i18ntools/makepot.php wp-plugin sources/ sources/languages/wpsol.pot
# - stel taal in
lang="nl_NL"
# - .pot vergelijken met .po (fail bij incompleet)
lang_compare=`msgcmp sources/languages/wpsol-$lang.po sources/languages/wpsol.pot 2>&1`
if [ "$lang_compare" != "" ]
then
	echo "incomplete vertaling $lang"
	echo "$lang_compare"
	exit
fi
# - make .mo file
lang_make=`msgfmt -o sources/languages/wpsol-$lang.mo -v sources/languages/wpsol-$lang.po 2>&1`
echo "Update $lang: $lang_make"
# - verwijder i18ntools
rm -r /tmp/i18ntools/


# check that git is clean
git_clean=`git clean -n sources/; git status --porcelain sources/`
if [ "$git_clean" != "" ]
then
	git status
	echo
	echo "er zijn pending changes in de sources dir (use --force to override)"
	if [ "$1" == "--force" ]
	then
		echo "continue anyway..."
	else
		exit
	fi
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
if [ ! -f /tmp/wpsol_tmp_svn/trunk/wpsol.php ]
then
	echo "helaas plugin-checkout failed, stop."
	exit
fi

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
