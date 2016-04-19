#!/bin/bash

# ToDo - --zip option to make a zip as test-version to send to someone for testing
# ToDo - add debug options to wpsol, which are removed when uploading to wordpress.org

# options
wuFORCE=0
wuUPLOAD=0
wuRELEASE=0
wuDRY=0
wuTEST=0

while [[ $# -ge 1 ]]
do
	case $1 in
		-f|--force)
			wuFORCE=1
		;;
		-u|--upload)
			wuUPLOAD=1
		;;
		-r|--release)
			wuRELEASE=1
		;;
		-d|--dry|--dry-run)
			wuDRY=1
		;;
		-t|--test)
			wuTEST=1
		;;
		-h|--help)
			echo ""
			echo "wordpress_upload.sh beta-release :-)"
			echo ""
			echo " -f|--force           force upload to wordpress, with pending git changes"
			echo " -u|--upload          upload the code to wordpress.org trunk"
			echo " -r|--release         upload a new release version to wordpress.org"
			echo " -d|--dry|--dry-run   run all checks, but stop before uploading to wordpress.org"
			echo " -t|--test            upload files to test-server"
			echo ""
			exit
		;;
		*)
			echo "unknown option \"$1\" use --help to list possible options"
			exit
		;;
	esac
	shift
done

# confirm that this is a production run
if [ $wuUPLOAD == 0 ] && [ $wuRELEASE == 0 ] && [ $wuDRY == 0 ] && [ $wuTEST == 0 ]
then
	read -p "do you want to upload to wordpress.org ? [y/N] " production_ok
	if [ "$production_ok" == "y" ] || [ "$production_ok" == "Y" ]
	then
		wuUPLOAD=1
		read -p "do you want to release a new version ? [y/N] " release_ok
		if [ "$release_ok" == "y" ] || [ "$release_ok" == "Y" ]
		then
			wuRELEASE=1
		fi
	else
		wuDRY=1

		read -p "do you want to upload to test server ? [y/N] " testupload_ok
		if [ "$testupload_ok" == "Y" ] || [ "$testupload_ok" == "y" ]
		then
			wuTEST=1
		fi
	fi
fi

# check for existence of /tmp/wpsol_tmp_svn
if [ -d /tmp/wpsol_tmp_svn ]
then
	echo "! the temporary subversion repository was not removed by a previous run...[ /tmp/wpsol_tmp_svn ]"
	exit
fi

# check for existence of /tmp/i18ntools
if [ ! -d /tmp/i18ntools/ ]
then
	echo "> checkout i18ntools"

	svn checkout http://i18n.svn.wordpress.org/tools/trunk/ /tmp/i18ntools > /dev/null
	# - fail when svn checkout failed !
	if [ ! -f /tmp/i18ntools/makepot.php ]
	then
		echo "! i18ntools-checkout failed, stop."
		rm -rf /tmp/i18ntools
		exit
	fi
	# - fix some 'errors' in makepot.php
	sed -i s/\'trunk\'/\'sources\'/ /tmp/i18ntools/makepot.php
	sed -i s/\'Y-m-d\ H:i:s+00:00\'/\'Y-m-d\ 00:00:00+00:00\'/ /tmp/i18ntools/makepot.php
fi

# generate .pot file
echo "> generate .pot file"
php /tmp/i18ntools/makepot.php wp-plugin sources/ sources/languages/wpsol.pot

# get Project-Id-Version from .pot file
project_id_version=$(grep 'Project-Id-Version' sources/languages/wpsol.pot|cut -d\" -f2|sed s/"\\\n"//)

# set languages
languages=('nl_NL')
for lang in ${languages[@]}
do
	# update .po file from .pot
	echo "> update $lang.po file from .pot"
	msgmerge --update sources/languages/wpsol-$lang.po sources/languages/wpsol.pot
	# compare .pot to .po (fail when incomplete)
	echo "> compare .pot to $lang.po"
	lang_compare=`msgcmp sources/languages/wpsol-$lang.po sources/languages/wpsol.pot 2>&1`
	if [ "$lang_compare" != "" ]
	then
		echo "! incomplete translation $lang"
		echo "! $lang_compare"
		if [ $wuFORCE == 1 ]
		then
			echo ">> FORCED CONTINUE..."
		else
			# ToDo - check all languages before exit
			exit
		fi
	fi
	# update Project-Id-Version in .po from .pot
	sed -i s/'.*Project-Id-Version.*'/"\"$project_id_version\\\n\""/ sources/languages/wpsol-nl_NL.po
	# make .mo file
	echo "> make $lang.mo file"
	lang_make=`msgfmt -o sources/languages/wpsol-$lang.mo -v sources/languages/wpsol-$lang.po 2>&1`
	echo "> update $lang: $lang_make"
done

# convert wordpress-readme to github-readme
echo "> convert wordpress-readme to github-readme"
if [ ! -f /tmp/wp2md ]
then
	wget -q --show-progress http://code.sunchaser.info/wp2md/downloads/wp2md.phar -O /tmp/wp2md
	chmod +x /tmp/wp2md
fi
/tmp/wp2md convert < assets/readme.txt > README.md
index="## Index ##\n\n"
grep '^## ' README.md | sed s/"## "/""/ | sed s/" ##"/""/ | sed s/" "/"-"/g > /tmp/wpsol_readme
while read line
do
	line_lower=`echo "$line" | tr [:upper:] [:lower:]`
	index="$index* [$line](#$line_lower)\n"
done < /tmp/wpsol_readme

sed -i s/"# wpSOL #"/"# wpSOL #\n[![Wordpress-Active-Installs](https:\/\/img.shields.io\/wordpress\/plugin\/ai\/wpsol.svg)](https:\/\/wordpress.org\/plugins\/wpsol\/)\n"/ README.md
sed -i s/"# wpSOL #"/"# wpSOL #\n[![Wordpress-Downloads](https:\/\/img.shields.io\/wordpress\/plugin\/dt\/wpsol.svg)](https:\/\/wordpress.org\/plugins\/wpsol\/)"/ README.md
sed -i s/"# wpSOL #"/"# wpSOL #\n[![Wordpress-Version](https:\/\/img.shields.io\/wordpress\/plugin\/v\/wpsol.svg)](https:\/\/wordpress.org\/plugins\/wpsol\/)"/ README.md
sed -i s/"# wpSOL #"/"# wpSOL #\n[![Wordpress-Supported](https:\/\/img.shields.io\/wordpress\/v\/wpsol.svg)](https:\/\/wordpress.org\/plugins\/wpsol\/)"/ README.md

sed -i s/"## Description ##"/"${index}\n## Description ##"/ README.md
imgcache=$(date +%Y%m%d)
sed -i s/".png"/".png?rev=$imgcache"/ README.md

if [ $wuTEST == 1 ]
then
	echo "> test changes on wpsol test system"
	if [ ! -f ./wordpress_upload.conf ]
	then
		echo "! wptest_user=\"user\""
		echo "! wptest_host=\"example.org\""
		echo "! wptest_dir=\"/var/www/wordpress\""
		echo "! wptest_chown_user=\"www\""
		echo "! wptest_chown_group=\"www\""
		echo "! no wordpress_upload.conf found"
		exit
	else
		. ./wordpress_upload.conf
		if [ "$wptest_user" == "" ] || [ "$wptest_host" == "" ] || [ "$wptest_dir" == "" ] || [ "$wptest_chown_user" == "" ] || [ "$wptest_chown_group" == "" ]
		then
			echo "! wptest_user=\"www\" [$wptest_user]"
			echo "! wptest_host=\"example.org\" [$wptest_host]"
			echo "! wptest_dir=\"/var/www/wordpress\" [$wptest_dir]"
			echo "! wptest_chown_user=\"www\" [$wptest_chown_user]"
			echo "! wptest_chown_group=\"www\" [$wptest_chown_group]"
			echo "! wordpress_upload.conf does not contain neccesary config options"
			exit
		fi
	fi

	echo "> rsync"
	rsync --recursive --info=progress2 --delete ./sources/ $wptest_user@$wptest_host:$wptest_dir/wp-content/plugins/wpsol/

	echo "> chown wordpress www-dir"
	ssh $wptest_user@$wptest_host chown -R $wptest_chown_user:$wptest_chown_group $wptest_dir/

	echo "> Done!"
	exit
fi

# check that git is clean
echo "> check that git is clean"
git_clean=`git clean -n; git status --porcelain`
if [ "$git_clean" != "" ]
then
	git status
	echo
	echo "! there are pending changes in the sources dir (use --force to override)"
	if [ $wuFORCE == 1 ]
	then
		echo ">> FORCED CONTINUE..."
	elif [ $wuDRY == 1 ] && [ $wuTEST == 0 ]
	then
		echo ">> just a dry-run, continue..."
	elif [ $wuRELEASE == 0 ] && [ $wuDRY == 0 ]
	then
		read -p "continue ? [Y/n] " git_pending_continue
		if [ "$git_pending_continue" == "n" ] || [ "$git_pending_continue" == "N" ]
		then
			exit
		fi
	else
		exit
	fi
fi

# check that git is on branch master
echo "> check that git is on branch master"
git_branch=`git rev-parse --abbrev-ref HEAD`
if [ "$git_branch" != "master" ]
then
	git status
	echo
	echo "! git is not on the master-branch"
	exit
fi

if [ $wuRELEASE == 1 ]
then
	# check version numbers
	echo "> check version numbers"
	# get current version number from wordpress...
	echo "> get current version number from wordpress..."
	cv_plugin=`curl -s http://plugins.svn.wordpress.org/wpsol/trunk/wpsol.php | grep Version | cut -d" " -f2`
	# get version number from local files
	echo "> get version number from local files..."
	cv_wpsol=`grep Version sources/wpsol.php | cut -d" " -f2`
	cv_stable=`grep 'Stable tag:' assets/readme.txt | cut -d" " -f3`
	cv_lang=`grep Project-Id-Version sources/languages/wpsol.pot | cut -d" " -f3 | cut -d"\\\\" -f1`
	cv_langNL=`grep Project-Id-Version sources/languages/wpsol-nl_NL.po | cut -d" " -f3 | cut -d"\\\\" -f1`

	# check matching version numbers in local files
	echo "> check matching version numbers in local files"
	if [ "$cv_wpsol" != "$cv_stable" ] || [ "$cv_wpsol" != "$cv_lang" ] || [ "$cv_wpsol" != "$cv_langNL" ]
	then
		echo "> php:      $cv_wpsol"
		echo "> stable:   $cv_stable"
		echo "> pot:      $cv_lang"
		echo "> nl_NL.po: $cv_langNL"
		echo "! Plugin versions do not match..."
		if [ $wuFORCE == 1 ]
		then
			echo ">> FORCED CONTINUE..."
		else
			exit
		fi
	fi

	# check changelog for current version
	echo "> check changelog for current version"
	cv_changelog=`grep "= $cv_wpsol =" assets/readme.txt`
	if [ "$cv_changelog" == "" ]
	then
		echo ">! No Changelog for version $cv_wpsol"
		exit
	fi

	# check for version number increase
	echo "> check for version number increase"
	if [ "$cv_plugin" == "$cv_wpsol" ] || [ "$cv_plugin" == "$cv_stable" ] || [ "$cv_plugin" == "$cv_lang" ] || [ "$cv_plugin" == "$cv_langNL" ]
	then
		echo "> WP.org:   $cv_plugin"
		echo "> php:      $cv_wpsol"
		echo "> stable:   $cv_stable"
		echo "> pot:      $cv_lang"
		echo "> nl_NL.po: $cv_langNL"
		echo "! Plugin version not updated..."
		if [ $wuFORCE == 1 ]
		then
			echo ">> FORCED CONTINUE..."
		else
			exit
		fi
	fi

	# confirm version number increase
	read -p "Increase version number from $cv_plugin to $cv_wpsol ? [y/N] " increase_ok
	if [ "$increase_ok" != "y" ] && [ "$increase_ok" != "Y" ]
	then
		echo "stop!"
		exit
	fi
fi

if [ $wuDRY == 1 ]
then
	echo "! dry-run, no upload"
	exit
fi

# subversion checkout
svn checkout http://plugins.svn.wordpress.org/wpsol /tmp/wpsol_tmp_svn
if [ ! -f /tmp/wpsol_tmp_svn/trunk/wpsol.php ]
then
	echo "! plugin-checkout failed"
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

# copy the code to a tags/VERSION directory
if [ $wuRELEASE == 1 ]
then
	echo "> make a tags/$cv_wpsol directory on the wp.org SVN-server"
	cp -r trunk tags/$cv_wpsol
	svn add tags/$cv_wpsol
fi

# check-in the changes
if [ $wuRELEASE == 1 ]
then
	commit_msg="auto-commit [] stable: $cv_plugin -> $cv_wpsol"
else
	read -p "please enter a commit message: " trunk_commit_msg
	if [ "$trunk_commit_msg" == "" ]
	then
		echo "! you need to specify a commit message"
		# remove temporary svn repo
		rm -rf /tmp/wpsol_tmp_svn
		exit
	fi

	commit_msg="auto-commit [] trunk: $trunk_commit_msg"
fi
echo "> commit svn with msg: \"$commit_msg\""
svn ci -m "$commit_msg"

# go back to start dir
cd $startdir

# remove temporary svn repo
rm -rf /tmp/wpsol_tmp_svn

echo "> Done!"

echo "> you should:"
if [ $wuRELEASE == 1 ]
then
	echo "> git push && git tag $cv_wpsol && git push origin $cv_wpsol"
else
	echo "> git push"
fi
