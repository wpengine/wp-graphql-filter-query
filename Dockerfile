ARG PHP_VERSION=7.4
ARG WORDPRESS_VERSION=5.9.3

FROM wordpress:${WORDPRESS_VERSION}-php${PHP_VERSION}-apache

ARG PLUGIN_NAME=wp-graphql-filter-query

# Setup the OS
RUN apt-get -qq update ; apt-get -y install unzip curl sudo subversion mariadb-client \
	&& apt-get autoclean \
	&& chsh -s /bin/bash www-data

# Install wp-cli
RUN curl https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar > /usr/local/bin/wp-cli.phar \
	&& echo "#!/bin/bash" > /usr/local/bin/wp-cli \
	&& echo "su www-data -c \"/usr/local/bin/wp-cli.phar --path=/var/www/html \$*\"" >> /usr/local/bin/wp \
	&& chmod 755 /usr/local/bin/wp* \
	&& echo "*** wp command installed"

# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
	&& php composer-setup.php \
	&& php -r "unlink('composer-setup.php');" \
	&& mv composer.phar /usr/local/bin/ \
	&& echo "#!/bin/bash" > /usr/local/bin/composer \
	&& echo "su www-data -c \"/usr/local/bin/composer.phar --working-dir=/var/www/html/wp-content/plugins/${PLUGIN_NAME} \$*\"" >> /usr/local/bin/composer \
	&& chmod ugo+x /usr/local/bin/composer \
	&& echo "*** composer command installed"

# Create testing environment
COPY bin/install-wp-tests.sh /usr/local/bin/
RUN echo "#!/bin/bash" > /usr/local/bin/install-wp-tests \
        && echo "su www-data -c \"install-wp-tests.sh \${WORDPRESS_DB_NAME}_test root root \${WORDPRESS_DB_HOST} latest\"" >> /usr/local/bin/install-wp-tests \
        && chmod ugo+x /usr/local/bin/install-wp-test* \
        && su www-data -c "/usr/local/bin/install-wp-tests.sh ${WORDPRESS_DB_NAME}_test root root '' latest true" \
        && echo "*** install-wp-tests installed"