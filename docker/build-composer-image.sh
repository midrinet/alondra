#!/bin/bash
# Build the Composer Docker image
PUSH=0
BASEDIR="$(dirname $(realpath $0))"
ENV_FILE="$BASEDIR/../.env"

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
while [[ "$#" -gt 0 ]]; do
    if [ "$1" == "--push" ]; then
        PUSH=1
    fi
    shift
done

build_args=""

# Build the Docker image
echo "Building Composer Docker image"

if [ $PUSH -eq 1 ]; then
    echo "Login to the Container Registry..."
    if [ -z "$CONTAINER_REGISTRY_TOKEN" ]; then
        echo "❌ Please, set an environment variable named CONTAINER_REGISTRY_TOKEN with your token"
        exit 1
    fi
    if [ -z "$CONTAINER_REGISTRY_USER" ]; then
        echo "❌ Please, set an environment variable named CONTAINER_REGISTRY_USER with your username"
        exit 1
    fi
    if [ -z "$CONTAINER_REGISTRY" ]; then
        echo "❌ Please, set an environment variable named CONTAINER_REGISTRY with your registry URL"
        exit 1
    fi

    echo $CONTAINER_REGISTRY_TOKEN | docker login "$CONTAINER_REGISTRY" -u "$CONTAINER_REGISTRY_USER" --password-stdin || (echo "❌ Login failed" && exit 1)
    echo "The resulting image will be pushed to the Container Registry"
    build_args+=" --platform linux/amd64,linux/arm64 --push"
else
    build_args+=" --load"
fi

build_args+=" --tag $CONTAINER_REGISTRY/$CONTAINER_REGISTRY_USER/composer:latest"
build_args+=" -f $BASEDIR/Dockerfile.composer $BASEDIR"

BUILDER_NAME="docker-builder"
EXISTING_BUILDER=$(docker buildx ls --format '{{.Name}}' | grep -w "$BUILDER_NAME")

export DOCKER_BUILDKIT=1
if [ -z "$EXISTING_BUILDER" ]; then
  docker buildx create --name "$BUILDER_NAME" --use || (echo "❌ Builder creation failed" && exit 1)
  docker buildx inspect "$BUILDER_NAME" --bootstrap || (echo "❌ Builder bootstrap failed" && exit 1)
else
  ACTIVE_BUILDER=$(docker buildx ls | grep -w "$BUILDER_NAME" | awk '/\*/ {print $1}')

  if [ "$ACTIVE_BUILDER" != "*" ]; then
    docker buildx use "$BUILDER_NAME" || (echo "❌ Builder use failed" && exit 1)
  fi
fi

docker buildx build $build_args
