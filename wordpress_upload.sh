#!/bin/bash

# ToDo - add debug options to plugin, which are removed when uploading to wordpress.org

# options
wuFORCE=0
wuUPLOAD=0
wuRELEASE=0
wuDRY=0
wuTEST=0
wuZIP=0

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
		-z|--zip)
			wuZIP=1
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
			echo " -z|--zip             make a zip-file to install in a wordpress for testing"
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

# ToDo - load from .conf file
plugin_name="wpsol"
plugin_name_disp="wpSOL"

# make a zip file
if [ $wuZIP == 1 ]
then
	# make a temp dir
	temp_zip_dir=$(mktemp -dt ${plugin_name}-zip-XXXXXX)

	# copy files to temp dir
	mkdir "$temp_zip_dir/${plugin_name}"
	cp ./assets/readme.txt "$temp_zip_dir/${plugin_name}"
	cp -R ./sources/* "$temp_zip_dir/${plugin_name}"

	# generate zip filename
	temp_zip_name="/tmp/${plugin_name}-test.zip"

	# zip temp dir
	cd "$temp_zip_dir"
	zip -rq "$temp_zip_name" ./${plugin_name}

	# remove temp dir
	rm -r "$temp_zip_dir"

	echo "zip test-file: $temp_zip_name"
	exit
fi

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

# check for existence of /tmp/${plugin_name}_tmp_svn
if [ -d /tmp/${plugin_name}_tmp_svn ]
then
	echo "! the temporary subversion repository was not removed by a previous run...[ /tmp/${plugin_name}_tmp_svn ]"
	exit
fi

if [ -d ./sources/languages ]
then
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
	php /tmp/i18ntools/makepot.php wp-plugin sources/ sources/languages/${plugin_name}.pot

	# get Project-Id-Version from .pot file
	project_id_version=$(grep 'Project-Id-Version' sources/languages/${plugin_name}.pot|cut -d\" -f2|sed s/"\\\n"//)

	# set languages
	languages=('nl_NL')
	for lang in "${languages[@]}"
	do
		# update .po file from .pot
		echo "> update $lang.po file from .pot"
		msgmerge --update "sources/languages/${plugin_name}-$lang.po" sources/languages/${plugin_name}.pot
		# compare .pot to .po (fail when incomplete)
		echo "> compare .pot to $lang.po"
		lang_compare=$(msgcmp "sources/languages/${plugin_name}-$lang.po" sources/languages/${plugin_name}.pot 2>&1)
		if [ "$lang_compare" != "" ]
		then
			echo "! incomplete translation $lang"
			echo "! $lang_compare"
			if [ $wuFORCE == 1 ]
			then
				echo ">> FORCED CONTINUE..."
			else
				exit
			fi
		fi
		# update Project-Id-Version in .po from .pot
		sed -i s/'.*Project-Id-Version.*'/"\"$project_id_version\\\n\""/ sources/languages/${plugin_name}-nl_NL.po
		# make .mo file
		echo "> make $lang.mo file"
		lang_make=$(msgfmt -o "sources/languages/${plugin_name}-$lang.mo" -v "sources/languages/${plugin_name}-$lang.po" 2>&1)
		echo "> update $lang: $lang_make"
	done
fi

# convert wordpress-readme to github-readme
echo "> convert wordpress-readme to github-readme"
if [ ! -f /tmp/wp2md ]
then
	curl -s -L https://github.com/wpreadme2markdown/wp-readme-to-markdown/releases/latest | egrep -o '/wpreadme2markdown/wp-readme-to-markdown/releases/download/[0-9.]*/wp2md.phar' | wget --base=http://github.com/ -i - -O /tmp/wp2md
	chmod +x /tmp/wp2md
fi
/tmp/wp2md convert < assets/readme.txt > README.md
index="## Index \n\n"
grep '^## ' README.md | sed s/'## '// | sed s/' $'// | sed s/' '/-/g > /tmp/${plugin_name}_readme
while read line
do
	line_lower=$(echo "$line" | tr '[:upper:]' '[:lower:]')
	index="$index* [$line](#$line_lower)\n"
done < /tmp/${plugin_name}_readme

sed -i s/'# ${plugin_name_disp} '/"# ${plugin_name_disp} \n[![Wordpress-Active-Installs](https:\/\/img.shields.io\/wordpress\/plugin\/ai\/${plugin_name}.svg)](https:\/\/wordpress.org\/plugins\/${plugin_name}\/)\n"/ README.md
sed -i s/'# ${plugin_name_disp} '/"# ${plugin_name_disp} \n[![Wordpress-Downloads](https:\/\/img.shields.io\/wordpress\/plugin\/dt\/${plugin_name}.svg)](https:\/\/wordpress.org\/plugins\/${plugin_name}\/)"/ README.md
sed -i s/'# ${plugin_name_disp} '/"# ${plugin_name_disp} \n[![Wordpress-Version](https:\/\/img.shields.io\/wordpress\/plugin\/v\/${plugin_name}.svg)](https:\/\/wordpress.org\/plugins\/${plugin_name}\/)"/ README.md
sed -i s/'# ${plugin_name_disp} '/"# ${plugin_name_disp} \n[![Wordpress-Supported](https:\/\/img.shields.io\/wordpress\/v\/${plugin_name}.svg)](https:\/\/wordpress.org\/plugins\/${plugin_name}\/)"/ README.md

sed -i s/'## Description '/"${index}\n## Description "/ README.md
imgcache=$(date +%Y%m%d)
sed -i s/'.png'/".png?rev=$imgcache"/ README.md

# Replace double emtpy lines with one
sed -i '/^$/N;/^\n$/D' README.md

if [ $wuTEST == 1 ]
then
	echo "> test changes on ${plugin_name} test system"
	if [ ! -f ./wordpress_upload.conf ]
	then
		echo "! wptest_user=\"user\""
		echo "! wptest_host=\"example.org\""
		echo "! wptest_dir=\"/var/www/wordpress\""
		echo "! wptest_chown_user=\"user\""
		echo "! wptest_chown_group=\"user\""
		echo "! no wordpress_upload.conf found"
		exit
	else
		wptest_user=""
		wptest_host=""
		wptest_dir=""
		wptest_chown_user=""
		wptest_chown_group=""

		. ./wordpress_upload.conf
		if [ "$wptest_user" == "" ] || [ "$wptest_host" == "" ] || [ "$wptest_dir" == "" ] || [ "$wptest_chown_user" == "" ] || [ "$wptest_chown_group" == "" ]
		then
			echo "! wptest_user=\"$wptest_user\""
			echo "! wptest_host=\"$wptest_host\""
			echo "! wptest_dir=\"$wptest_dir\""
			echo "! wptest_chown_user=\"$wptest_chown_user\""
			echo "! wptest_chown_group=\"$wptest_chown_group\""
			echo "! wordpress_upload.conf does not contain neccesary config options, or some options are empty"
			exit
		fi
	fi

	echo "> rsync"
	rsync --recursive --info=progress2 --delete ./sources/ $wptest_user@$wptest_host:$wptest_dir/wp-content/plugins/${plugin_name}/

	echo "> chown wordpress www-dir"
	ssh $wptest_user@$wptest_host chown -R $wptest_chown_user:$wptest_chown_group $wptest_dir/

	echo "> Done!"
	exit
fi

# check that git is clean
echo "> check that git is clean"
git_clean=$(git clean -n; git status --porcelain)
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
git_branch=$(git rev-parse --abbrev-ref HEAD)
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
	cv_plugin=$(curl -s http://plugins.svn.wordpress.org/${plugin_name}/trunk/${plugin_name}.php | grep Version | cut -d" " -f2)
	# get version number from local files
	echo "> get version number from local files..."
	cv_local=$(grep Version sources/${plugin_name}.php | cut -d" " -f2)
	cv_stable=$(grep 'Stable tag:' assets/readme.txt | cut -d" " -f3)
	if [ -d sources/languages/ ]
	then
		cv_lang=$(grep Project-Id-Version sources/languages/${plugin_name}.pot | cut -d" " -f3 | cut -d"\\" -f1)
		cv_langNL=$(grep Project-Id-Version sources/languages/${plugin_name}-nl_NL.po | cut -d" " -f3 | cut -d"\\" -f1)
	else
		cv_lang=$cv_local
		cv_langNL=$cv_local
	fi
	# check matching version numbers in local files
	echo "> check matching version numbers in local files"
	if [ "$cv_local" != "$cv_stable" ] || [ "$cv_local" != "$cv_lang" ] || [ "$cv_local" != "$cv_langNL" ]
	then
		echo "> php:      $cv_local"
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
	cv_changelog=$(grep "= $cv_local =" assets/readme.txt)
	if [ "$cv_changelog" == "" ]
	then
		echo ">! No Changelog for version $cv_local"
		exit
	fi

	# check for version number increase
	echo "> check for version number increase"
	if [ "$cv_plugin" == "$cv_local" ] || [ "$cv_plugin" == "$cv_stable" ] || [ "$cv_plugin" == "$cv_lang" ] || [ "$cv_plugin" == "$cv_langNL" ]
	then
		echo "> WP.org:   $cv_plugin"
		echo "> php:      $cv_local"
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
	read -p "Increase version number from $cv_plugin to $cv_local ? [y/N] " increase_ok
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
svn checkout http://plugins.svn.wordpress.org/${plugin_name} /tmp/${plugin_name}_tmp_svn
if [ ! -f /tmp/${plugin_name}_tmp_svn/trunk/${plugin_name}.php ]
then
	echo "! plugin-checkout failed"
	exit
fi

# rsync git release to svn trunk
rsync --recursive --delete  sources/ /tmp/${plugin_name}_tmp_svn/trunk/

# cp readme file to svn trunk
cp assets/readme.txt /tmp/${plugin_name}_tmp_svn/trunk/readme.txt

# cp screenshots to svn assets
cp assets/screenshot* /tmp/${plugin_name}_tmp_svn/assets/

# cp icons to svn assets
cp assets/icon* /tmp/${plugin_name}_tmp_svn/assets/

# cp banners to svn assets
cp assets/banner* /tmp/${plugin_name}_tmp_svn/assets/

# changedir, remember current dir
startdir=$(pwd)
cd /tmp/${plugin_name}_tmp_svn

# add new and delete removed files from subversion
files_to_add=$(svn status | grep "^\?")
if [ "$files_to_add" != "" ]
then
	svn status | grep "^\?" | sed 's/? *//' | xargs -d'\n' svn add
fi
files_to_rm=$(svn status | grep "^\!")
if [ "$files_to_rm" != "" ]
then
	svn status | grep "^\!" | sed 's/? *//' | xargs -d'\n' svn rm
fi

# copy the code to a tags/VERSION directory
if [ $wuRELEASE == 1 ]
then
	echo "> make a tags/$cv_local directory on the wp.org SVN-server"
	cp -r trunk "tags/$cv_local"
	svn add "tags/$cv_local"
fi

# check-in the changes
if [ $wuRELEASE == 1 ]
then
	commit_msg="auto-commit [] stable: $cv_plugin -> $cv_local"
else
	read -p "please enter a commit message: " trunk_commit_msg
	if [ "$trunk_commit_msg" == "" ]
	then
		echo "! you need to specify a commit message"
		# remove temporary svn repo
		rm -rf /tmp/${plugin_name}_tmp_svn
		exit
	fi

	commit_msg="auto-commit [] trunk: $trunk_commit_msg"
fi
echo "> commit svn with msg: \"$commit_msg\""
svn ci -m "$commit_msg"

# go back to start dir
cd "$startdir"

# remove temporary svn repo
rm -rf /tmp/${plugin_name}_tmp_svn

echo "> Done!"

echo "> you should:"
if [ $wuRELEASE == 1 ]
then
	echo "> git push && git tag $cv_local && git push origin $cv_local"
else
	echo "> git push"
fi
