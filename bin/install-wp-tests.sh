#!/usr/bin/env bash

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e 's/\/$//')
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress/}

download() {
	if [ "$(which curl)" ]; then
		curl -s "$1" > "$2"
	elif [ "$(which wget)" ]; then
		wget -nv -O "$2" "$1"
	else
		echo "Neither curl nor wget is available. Please install one to proceed."
		exit 1
	fi
}

if [ ! -d "$WP_CORE_DIR" ]; then
	mkdir -p "$WP_CORE_DIR"
fi

if [ ! -d "$WP_TESTS_DIR" ]; then
	mkdir -p "$WP_TESTS_DIR"
fi

install_wp() {
	if [ -f "$WP_CORE_DIR/wp-includes/version.php" ]; then
		return
	fi

	local ARCHIVE_NAME
	if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
		ARCHIVE_NAME="wordpress-$WP_VERSION.tar.gz"
		download "https://wordpress.org/wordpress-$WP_VERSION.tar.gz" "$TMPDIR/$ARCHIVE_NAME"
	else
		ARCHIVE_NAME="wordpress.tar.gz"
		download "https://wordpress.org/latest.tar.gz" "$TMPDIR/$ARCHIVE_NAME"
	fi

	tar --strip-components=1 -zxmf "$TMPDIR/$ARCHIVE_NAME" -C "$WP_CORE_DIR"
}

install_test_suite() {
	if [ -f "$WP_TESTS_DIR/includes/functions.php" ]; then
		return
	fi

	svn co --quiet https://develop.svn.wordpress.org/tags/$(get_wp_version)/tests/phpunit/includes/ "$WP_TESTS_DIR/includes"
	svn co --quiet https://develop.svn.wordpress.org/tags/$(get_wp_version)/tests/phpunit/data/ "$WP_TESTS_DIR/data"
}

install_db() {
	if [ "$SKIP_DB_CREATE" = "true" ]; then
		return 0
	fi

	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" || true
}

get_wp_version() {
	if [[ $WP_VERSION == latest ]]; then
		download https://api.wordpress.org/core/version-check/1.7/ "$TMPDIR/wp-latest.json"
		local LATEST
		LATEST=$(php -r "echo json_decode(file_get_contents('$TMPDIR/wp-latest.json'))->offers[0]->current;")
		echo "$LATEST"
	else
		echo "$WP_VERSION"
	fi
}

install_wp
install_test_suite
install_db
