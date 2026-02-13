FROM php:8.3-cli

WORKDIR /app
COPY . /app

# System deps for composer + sqlite
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
     libsqlite3-dev \
     pkg-config \
     git \
     unzip \
     zip \
  && docker-php-ext-install pdo pdo_sqlite \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]


