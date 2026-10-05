#!/bin/bash
# Sourced by scripts/phpcs and scripts/phpcbf. One copy of the host-to-container path rewrite and of
# the invocation, because the two wrappers only ever differ in which binary they run.

# Usage: phpcs_run <phpcs|phpcbf> "$@"
phpcs_run() {
    local tool="$1"
    shift

    local repo_root
    repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.."; pwd)"

    # The plugin is mounted from the repo root, so dev/helper and dev/demo-theme come along
    # with it. A bare invocation is the quality gate CLAUDE.md documents and reaches all three.
    local targets=( wp-content/plugins/alondra )

    if [ $# -gt 0 ]; then
        targets=()
        local arg rel
        for arg in "$@"; do
            rel="${arg#$repo_root/}"
            if [[ "$rel" != /* && "$rel" != -* && -e "$repo_root/$rel" ]]; then
                targets+=("wp-content/plugins/alondra/$rel")
            else
                targets+=("$arg")
            fi
        done
    fi

    # --basepath is on both tools, not just PHPCBF. PHPCBF needs it to write a fixed file back,
    # since the ruleset's own basepath strips the path down to somewhere inside the plugin;
    # PHPCS carries it so the paths it reports are the paths PHPCBF then fixes.
    docker compose exec web-alondra php "wp-content/plugins/alondra/vendor/bin/$tool" \
        --standard=./wp-content/plugins/alondra/.phpcs.xml.dist \
        --basepath=/var/www/html \
        "${targets[@]}"
}
