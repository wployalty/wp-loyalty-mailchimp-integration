#!/bin/bash
echo "Plugin Build Script"
current_dir="$PWD"
react_folder_path=$current_dir"/admin-ui"
react_css_dist_path=$current_dir"/assets/admin/css/dist"
react_js_dist_path=$current_dir"/assets/admin/js/dist"
react_node_module_path=$current_dir"/admin-ui/node_modules"

composer_lock_pack=$current_dir"/composer.lock"

pro_plugin_name="wp-loyalty-mailchimp-integration"
pack_pro_folder=$current_dir"/../compressed_pack"
plugin_pro_compress_folder=$pack_pro_folder"/"$pro_plugin_name

composer_run() {
  # shellcheck disable=SC2164
  cd "$current_dir"
  rm "$composer_lock_pack"
  composer install --no-dev
  composer update --no-dev
  cd ..
  echo "Composer Done"
  echo "Admin UI NPM"
  rm -r "$react_node_module_path"
  rm -r "$react_css_dist_path"
  rm -r "$react_js_dist_path"
  # shellcheck disable=SC2164
  cd "$react_folder_path"
  source ~/.nvm/nvm.sh
  nvm use 20
  npm i -q
  npm run build -q
  npm run css -q
  echo "Admin UI NPM Done"
  # shellcheck disable=SC2164
  cd "$current_dir"
}
update_ini_file() {
  cd $current_dir
  wp i18n make-pot . "i18n/languages/$pro_plugin_name.pot" --slug="$pro_plugin_name" --domain="$pro_plugin_name" --include=$pro_plugin_name".php",/App/ --headers='{"Last-Translator":"Wployalty <support@wployalty.net>","Language-Team":"Wployalty <support@wployalty.net>"}' --allow-root
  cd $current_dir
  echo "Update ini done"
}
copy_pro_folder() {
  if [ -d "$pack_pro_folder" ]; then
    rm -r "$pack_pro_folder"
  fi
  mkdir "$pack_pro_folder"
  mkdir "$plugin_pro_compress_folder"
  move_dir=("App" "assets" "i18n" "vendor" "composer.json" "readme.txt" $pro_plugin_name".php")
  # shellcheck disable=SC2068
  for dir in ${move_dir[@]}; do
    cp -r "$current_dir/$dir" "$plugin_pro_compress_folder/$dir"
  done
}

zip_pro_folder() {
  cd "$pack_pro_folder"
  rm "$pro_plugin_name".zip
  zip -r "$pro_plugin_name".zip $pro_plugin_name -q
  zip -d "$pro_plugin_name".zip __MACOSX/\*
  zip -d "$pro_plugin_name".zip \*/.DS_Store
}

echo "Composer Run:"
composer_run
echo "Update ini"
update_ini_file
echo "Copy Folder:"
copy_pro_folder
echo "Zip Folder:"
zip_pro_folder
echo "End"
