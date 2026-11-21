#!/usr/bin/env bash
# Installs the WordPress test library and sets up the test database.
# Usage: bash tests/bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]

set -e

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}
TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}

download() {
    if [ "$(which curl)" ]; then
        curl -s "$1" > "$2"
    elif [ "$(which wget)" ]; then
        wget -nv -O "$2" "$1"
    fi
}

install_wp() {
    if [ -d "$WP_CORE_DIR" ]; then
        return
    fi
    mkdir -p "$WP_CORE_DIR"

    if [ "$WP_VERSION" = "latest" ]; then
        local ARCHIVE_NAME="latest"
    else
        local ARCHIVE_NAME="wordpress-${WP_VERSION}"
    fi

    download "https://wordpress.org/${ARCHIVE_NAME}.tar.gz" /tmp/wordpress.tar.gz
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"

    download "https://raw.github.com/markoheijnen/wp-mysqli/master/db.php" "$WP_CORE_DIR/wp-content/db.php"
}

install_test_suite() {
    if [ -d "$TESTS_DIR" ]; then
        return
    fi
    mkdir -p "$TESTS_DIR"

    download "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/" /tmp/wp-includes.txt 2>/dev/null || true
    svn co --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/" "$TESTS_DIR/includes"
    svn co --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data/" "$TESTS_DIR/data"

    download "https://develop.svn.wordpress.org/tags/${WP_VERSION}/wp-tests-config-sample.php" "$TESTS_DIR/wp-tests-config.php"

    sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$TESTS_DIR/wp-tests-config.php"
    sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$TESTS_DIR/wp-tests-config.php"
    sed -i "s/yourusernamehere/$DB_USER/" "$TESTS_DIR/wp-tests-config.php"
    sed -i "s/yourpasswordhere/$DB_PASS/" "$TESTS_DIR/wp-tests-config.php"
    sed -i "s|localhost|${DB_HOST}|" "$TESTS_DIR/wp-tests-config.php"
}

create_database() {
    mysql -u "$DB_USER" --password="$DB_PASS" -h "$DB_HOST" -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\`;" 2>/dev/null || true
}

install_wp
install_test_suite
create_database
