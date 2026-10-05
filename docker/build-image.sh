#!/bin/bash
# Build the Docker image
PUSH=0
WP_VERSION=""
PHP_VERSION=""
BASEDIR="$(dirname $(realpath $0))"
ENV_FILE="${ENV_FILE:-$BASEDIR/../.env}"

# Check if .env file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "❌ .env file not found. Please create a .env file using .env.sample as a template and set the required environment variables"
    exit 1
fi

set -o allexport
source $ENV_FILE
set +o allexport

# Parse arguments:
# --push: Push the image to the registry
# --wp=VERSION: Supported versions are 6.4.3...
# --php=VERSION: Supported versions are 7.4, 8.1, 8.2, 8.3, 8.4
while [[ "$#" -gt 0 ]]; do
    if [ "$1" == "--push" ]; then
        PUSH=1
    elif [ "$1" == "--build" ]; then
        build=1
    elif [[ "$1" == --wp=* ]]; then
        WP_VERSION="${1#*=}"
    elif [[ "$1" == --php=* ]]; then
        PHP_VERSION="${1#*=}"
    fi
    shift
done

if [ -z "$WP_VERSION" ]; then
    echo "❌ Please set the WordPress version using either --wp=VERSION or defining WP_VERSION in your .env file"
    exit 1
fi

if [ -z "$PHP_VERSION" ]; then
    echo "❌ Please set the PHP version using either --php=VERSION or defining PHP_VERSION in your .env file"
    exit 1
fi

build_args=" --build-arg PHP_VERSION=$PHP_VERSION"
build_args+=" --build-arg WP_VERSION=$WP_VERSION"

# Build the Docker image
echo "Building Docker image for WordPress $WP_VERSION with PHP $PHP_VERSION..."

if [ $PUSH -eq 1 ]; then
    echo "Login to the Container Registry..."
    if [ -z "$CONTAINER_REGISTRY_TOKEN" ]; then
        echo "❌ Please, set an environment variable named CONTAINER_REGISTRY_TOKEN with your token. You can define it in your .env file"
        exit 1
    fi
    if [ -z "$CONTAINER_REGISTRY_USER" ]; then
        echo "❌ Please, set an environment variable named CONTAINER_REGISTRY_USER with your username. You can define it in your .env file"
        exit 1
    fi
    if [ -z "$CONTAINER_REGISTRY" ]; then
        echo "❌ Please, set an environment variable named CONTAINER_REGISTRY with your registry URL. You can define it in your .env file"
        exit 1
    fi

    echo $CONTAINER_REGISTRY_TOKEN | docker login "$CONTAINER_REGISTRY" -u "$CONTAINER_REGISTRY_USER" --password-stdin || (echo "❌ Login failed" && exit 1)
    echo "The resulting image will be pushed to the Container Registry"
    build_args+=" --platform linux/amd64,linux/arm64 --push"
else
    build_args+=" --load"
fi

build_args+=" --tag "$CONTAINER_REGISTRY/$CONTAINER_REGISTRY_USER/alondra":$WP_VERSION-$PHP_VERSION"

DOCKERFILE="Dockerfile"

build_args+=" -f $BASEDIR/$DOCKERFILE $BASEDIR"

BUILDER_NAME="docker-builder"
EXISTING_BUILDER=$(docker buildx ls --format '{{.Name}}' | grep -w "$BUILDER_NAME")

export DOCKER_BUILDKIT=1
if [ -z "$EXISTING_BUILDER" ]; then
  docker buildx create --name "$BUILDER_NAME" --use || (echo "❌ Builder creation failed" && exit 1)
  docker buildx inspect "$BUILDER_NAME" --bootstrap || (echo "❌ Builder bootstrap failed" && exit 1)
else
  # Check if the builder is already in use
  ACTIVE_BUILDER=$(docker buildx ls | grep -w "$BUILDER_NAME" | awk '/\*/ {print $1}')

  if [ "$ACTIVE_BUILDER" != "*" ]; then
    # Use the builder if it's not the active one
    docker buildx use "$BUILDER_NAME" || (echo "❌ Builder use failed" && exit 1)
  fi
fi

docker buildx build $build_args