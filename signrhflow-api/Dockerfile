FROM php:8.4-cli

# Instala dependências do sistema e extensões vitais para o projeto
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libsqlite3-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo pdo_pgsql pdo_sqlite \
    && pecl install redis \
    && docker-php-ext-enable redis

# Instala o Composer (Gerenciador de pacotes do PHP)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Garante que as dependências sejam instaladas e roda o servidor na porta 8000
CMD bash -c "composer install && php artisan serve --host=0.0.0.0 --port=8000"