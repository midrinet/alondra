ARG WP_VERSION=6.9.1
ARG PHP_VERSION=8.5

FROM wordpress:${WP_VERSION}-php${PHP_VERSION}

SHELL ["/bin/bash", "-c"]

# Set UID and GID for www-data to 1000 and fix permissions in www
RUN usermod -u 1000 www-data \
&& groupmod -g 1000 www-data \
&& chown -R www-data:www-data /var/www

# Apache configuration
RUN a2enmod rewrite && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Above the XDebug layer rather than with the other scripts below, because that layer runs it.
COPY ./toggle-xdebug.sh /usr/local/bin/toggle-xdebug
RUN chmod +x /usr/local/bin/toggle-xdebug

# Setup XDEBUG and PHP configuration.
# opcache.revalidate_freq is 0 rather than the default 2: a host edit would otherwise
# serve stale bytecode for up to two seconds, which reads as an unrelated flake.
RUN pecl install xdebug && docker-php-ext-enable xdebug \
    && toggle-xdebug --mode=off \
    && echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/docker-php-woocommerce.ini \
    && echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/docker-php-woocommerce.ini \
    && echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/docker-php-woocommerce.ini \
    && echo "opcache.revalidate_freq = 0" >> /usr/local/etc/php/conf.d/docker-php-woocommerce.ini

# Install required packages
RUN apt-get update && apt-get install -y \
    wget \
    zip \
    mariadb-client \
    subversion \
    netcat-openbsd && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# A string rewrite, so it needs none of the files copied below.
RUN sed -i 's/exec "$@"/\/usr\/local\/bin\/wp-entrypoint.sh \&\& exec "$@"/g' /usr/local/bin/docker-entrypoint.sh

# Everything below is a copy or a variable, and all of it changes far more often than the
# layers above: keeping it last means editing a provisioning script, a demo image or a store
# setting costs a copy rather than the XDebug compile, the apt install and the WP-CLI download.

# Setup scripts
COPY ./wp-entrypoint.sh /usr/local/bin/wp-entrypoint.sh
COPY ./setup-tests.sh /usr/local/bin/setup-tests.sh
COPY ./run-tests.sh /usr/local/bin/run-tests.sh
RUN chmod +x /usr/local/bin/wp-entrypoint.sh /usr/local/bin/setup-tests.sh /usr/local/bin/run-tests.sh

# Demo store product images and the one file every downloadable product is sold as, baked
# in rather than mounted: changing one means rebuilding and republishing every <wp>-<php> tag.
COPY ./demo-store/images/*.jpg /demo-store/images/
COPY ./demo-store/files/*.pdf /demo-store/files/

ENV WP_LOCALE="en_US"
ENV WP_THEME="twentytwentyfour"
ENV WP_PLUGINS="woocommerce"

# The demo store identity lives here rather than in .env: .env is gitignored and
# already exists on every developer's machine, and compose passes it in as env_file,
# so a value defined there would keep whatever that stale copy holds.
ENV WC_STORE_NAME="Coffee Roasters"
ENV WC_STORE_TAGLINE="Small batch coffee, roasted in Madrid"

ENV WC_STORE_ADDRESS="Calle Melancolía, 1"
ENV WC_STORE_ADDRESS_2="Bajo"
ENV WC_STORE_CITY="Madrid"
ENV WC_STORE_POSTCODE="28001"
ENV WC_DEFAULT_COUNTRY="ES:M"
ENV WC_CURRENCY="EUR"
ENV WC_CURRENCY_POSITION="right_space"
ENV WC_PRICE_THOUSAND_SEPARATOR="."
ENV WC_PRICE_DECIMAL_SEPARATOR=","
ENV WC_WEIGHT_UNIT="kg"
ENV WC_DIMENSION_UNIT="cm"
# Free rather than a flat rate: 007 asserts the cart footer total against product sums
# alone, so a priced shipping line would break six assertions at the next teardown.
ENV WC_SHIPPING_ZONE_METHOD_ID="free_shipping"
ENV WC_SHIPPING_ZONE_METHOD_SETTINGS='{"title":"Free shipping"}'
