#!/bin/bash

# Check if need to install WordPress
if [ ! -f /var/www/html/.post-install-complete ]; then
     wait_for() {
        local retry=60
        local timeout=1
        local start=$(date +%s)

        while [ $(($(date +%s) - $start)) -lt $retry ]; do
            if "$@" > /dev/null 2>&1; then
                return 0
            fi
            sleep $timeout
        done
        return 1
    }

    # Override WP_URL if PUBLIC_URL is set
    if [ -n "$PUBLIC_URL" ]; then
        WP_URL="$PUBLIC_URL"
    fi
    
    # Wait for database to be ready and then create it.
    DB_PORT=3306
    result=$(wait_for nc -z "${WORDPRESS_DB_HOST}" "${DB_PORT}")
    if [ "$result" == "1" ]; then
        echo "❌ ${WORDPRESS_DB_HOST}:${DB_PORT} is not available"
        touch /var/www/html/.post-install-failed
        exit 1
    fi

    cd '/var/www/html'

    # Run WordPress installation wizard
    wp core install --allow-root \
    --url="${WP_URL}" \
    --title="${WC_STORE_NAME}" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --locale="${WP_LOCALE}" \
    --skip-email

    get_pkg_and_version() {
        IFS=':' read -r pkg version <<< "$1"
        if [ -n "${version}" ]; then
            version=" --version=${version}"
        fi
        echo "${pkg}${version}"
    }

    # Install plugins
    wp plugin deactivate --allow-root --all

    if [ -n "${WP_PLUGINS}" ]; then
        IFS=',' read -ra plugins <<< "${WP_PLUGINS}"
        for plugin in "${plugins[@]}"; do
            plugin_version=$(get_pkg_and_version "${plugin}")
            wp plugin install $plugin_version --activate --allow-root
        done
    fi

    # Check if WooCommerce is installed and run the setup script
    if ! wp plugin is-active --allow-root woocommerce; then
        echo "❌ WooCommerce is not installed"
        exit 1
    fi

   # Install theme
    if [ -n "${WP_THEME}" ]; then
        IFS=',' read -ra themes <<< "${WP_THEME}"
        for theme in "${themes[@]}"; do
            theme_version=$(get_pkg_and_version "${theme}")
            wp theme install --allow-root $theme_version --activate
        done
    fi

    # Last, so it supersedes whatever WP_THEME activated. Its parent, twentytwentyfive,
    # ships with core, and demo-theme itself is bind-mounted from dev/demo-theme/.
    wp theme activate --allow-root demo-theme

    # Setup WordPress options
    # blogname is already written by `wp core install --title`; the tagline has no such flag.
    wp option update --allow-root blogdescription "${WC_STORE_TAGLINE}"
    wp option update permalink_structure '/%postname%/' --allow-root

    # Setup WooCommerce options
    wp option update --allow-root woocommerce_store_address "${WC_STORE_ADDRESS}"
    wp option update --allow-root woocommerce_store_address_2 "${WC_STORE_ADDRESS_2}"
    wp option update --allow-root woocommerce_store_city "${WC_STORE_CITY}"
    wp option update --allow-root woocommerce_default_country "${WC_DEFAULT_COUNTRY}"
    wp option update --allow-root woocommerce_store_postcode "${WC_STORE_POSTCODE}"
    wp option update --allow-root woocommerce_currency "${WC_CURRENCY}"
    wp option update --allow-root woocommerce_currency_pos "${WC_CURRENCY_POSITION}"
    wp option update --allow-root woocommerce_price_thousand_sep "${WC_PRICE_THOUSAND_SEPARATOR}"
    wp option update --allow-root woocommerce_price_decimal_sep "${WC_PRICE_DECIMAL_SEPARATOR}"
    # WooCommerce takes both unit defaults from its US locale table whatever the store country is,
    # so a Madrid shop renders lbs and inches on every product page until these are written.
    wp option update --allow-root woocommerce_weight_unit "${WC_WEIGHT_UNIT}"
    wp option update --allow-root woocommerce_dimension_unit "${WC_DIMENSION_UNIT}"

    # WC_Install adds both of these as 'yes'. store_pages_only is inert while coming_soon is
    # 'no', but it is the mode the site-visibility screen still shows as the stored sub-choice.
    wp option update --allow-root woocommerce_coming_soon no
    wp option update --allow-root woocommerce_store_pages_only no

    wp wc shipping_zone_method create 0 --method_id="${WC_SHIPPING_ZONE_METHOD_ID}" --settings="${WC_SHIPPING_ZONE_METHOD_SETTINGS}" --user=admin --allow-root

    wp plugin activate --allow-root alondra
    wp plugin is-active --allow-root alondra || { echo "❌ alondra failed to activate (missing vendor/ or fatal during bootstrap)"; exit 1; }
    wp plugin activate --allow-root alondra-helper
    # TODO: Add default settings for Alondra plugin

    wp plugin uninstall --allow-root $(wp plugin list --allow-root --status=inactive --field=name | grep -v -v '_\?\(alondra\|learnpress\)' | tr "\n" " ")

    chown -R www-data:www-data /var/www/html
    touch /var/www/html/.post-install-complete
fi
echo "✅ Alondra plugin installed and configured."