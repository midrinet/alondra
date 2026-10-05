# Sourced by the other scripts. Resolves where the project lives.

# BASEDIR = nearest ancestor of $PWD with a compose.yml, so the scripts work the same
# from the repo root and from inside a project that holds this repo as a submodule.
BASEDIR="$PWD"
while [ ! -f "$BASEDIR/compose.yml" ]; do
    if [ "$BASEDIR" = "/" ]; then
        echo "❌ No compose.yml found from $PWD upwards. Run this from a project root."
        exit 1
    fi
    BASEDIR="$(dirname "$BASEDIR")"
done

# SCRIPTS = where these scripts live, which is not BASEDIR when used as a submodule.
SCRIPTS="$(dirname "$(realpath "$0")")"

# Building the image belongs to this repo, next to these scripts.
DOCKERDIR="$(dirname "$SCRIPTS")/docker"

# Shared defaults ship with the scripts; the outer project's .env extends them.
# Same file in both when this repo runs standalone.
BASE_ENV="$(dirname "$SCRIPTS")/.env"

# build-image.sh must read the .env of whoever invoked us, not its own neighbour.
export ENV_FILE="$BASEDIR/.env"

# A .env cannot include another one, docker compose rejects the syntax. Sourcing
# both here covers the scripts and, through allexport, compose interpolation too.
load_env() {
    for f in "$BASE_ENV" "$ENV_FILE"; do
        if [ ! -f "$f" ] && [ -f "$f.sample" ]; then
            cp "$f.sample" "$f"
        fi
    done

    set -o allexport
    if [ -f "$BASE_ENV" ] && [ "$BASE_ENV" != "$ENV_FILE" ]; then
        . "$BASE_ENV"
    fi
    . "$ENV_FILE"
    set +o allexport
}
