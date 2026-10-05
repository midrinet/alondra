#!/bin/bash
export XDEBUG_MODE=off
VERSION='latest'
if [ -n "$1" ]; then
    VERSION=$1
fi
rm -rf \
/var/www/html/wp-content/plugins/alondra/.circleci \
/var/www/html/wp-content/plugins/alondra/bin \
/var/www/html/wp-content/plugins/alondra/tests/bootstrap.php

mv /var/www/html/wp-content/plugins/alondra/phpunit.xml.dist /var/www/html/wp-content/plugins/alondra/phpunit.xml.dist.backup
mv /var/www/html/wp-content/plugins/alondra/.phpcs.xml.dist /var/www/html/wp-content/plugins/alondra/.phpcs.xml.dist.backup

echo "s" | wp scaffold plugin-tests alondra --allow-root

mv -f /var/www/html/wp-content/plugins/alondra/phpunit.xml.dist.backup /var/www/html/wp-content/plugins/alondra/phpunit.xml.dist
mv -f /var/www/html/wp-content/plugins/alondra/.phpcs.xml.dist.backup /var/www/html/wp-content/plugins/alondra/.phpcs.xml.dist
# Add WooCommerce, and rerun the SDK's start.php: PHPUnit's early vendor/autoload.php ran it before ABSPATH existed.
sed -i '/require dirname( dirname( __FILE__ ) ) . \x27\/alondra.php\x27;/c\require \x27load-wc.php\x27;\nrequire dirname( dirname( __FILE__ ) ) . \x27/vendor/freemius/wordpress-sdk/start.php\x27;\nrequire dirname( dirname( __FILE__ ) ) . \x27/alondra.php\x27;' /var/www/html/wp-content/plugins/alondra/tests/bootstrap.php

/var/www/html/wp-content/plugins/alondra/bin/install-wp-tests.sh wordpress_test root "${MARIADB_ROOT_PASSWORD}" "${WORDPRESS_DB_HOST}" "${VERSION}"

rm -f /var/www/html/wp-content/plugins/alondra/tests/test-sample.php