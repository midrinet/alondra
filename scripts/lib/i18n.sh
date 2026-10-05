# Sourced by the i18n scripts of free and the add-on. WP-CLI's bundled i18n command runs in the
# same wordpress:cli image scripts/release uses, so no extra tooling is installed anywhere.

I18N_IMAGE=wordpress:cli-2.12
# --include matches a path segment anywhere, so a src/ inside any of these would count without it.
I18N_EXCLUDE=core,vendor,node_modules,dev,tests,tests-e2e,release,graphify-out

# Usage: i18n_wp <plugin dir> <out dir> <wp args...>
# The plugin is mounted read-only as the working directory and <out dir> at /out. Not at /<slug>: make-pot
# strips the directory's path from every file's, so /alondra/alondra.php would read as a hidden .php and be skipped.
i18n_wp() {
    local dir="$1" out="$2"
    shift 2
    docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -v "$dir":/plugin-source:ro -v "$out":/out -w /plugin-source \
        "$I18N_IMAGE" wp "$@"
}

# Regenerates languages/<slug>.pot, or with --check fails when its strings are stale.
# <sources> is make-pot's comma-separated --include: the plugin's own files, never a built bundle or a stray checkout.
# Usage: i18n_make_pot <plugin dir> <slug> <sources> [--check] [make-pot args...]
i18n_make_pot() {
    local dir="$1" slug="$2" sources="$3"
    shift 3
    local check=0
    if [[ "$1" == --check ]]; then
        check=1
        shift
    fi

    local tmp
    tmp="$(mktemp -d)"
    trap 'rm -rf "$tmp"; trap - RETURN' RETURN
    i18n_wp "$dir" "$tmp" i18n make-pot . "/out/$slug.pot" --slug="$slug" --domain="$slug" --include="$sources" --exclude="$I18N_EXCLUDE" "$@" || return 1

    if (( ! check )); then
        cp "$tmp/$slug.pot" "$dir/languages/$slug.pot"
        return
    fi
    # Source references are left out, so a string that only moved lines does not fail the check.
    local volatile='^("POT-Creation-Date:|#: )'
    if ! diff -u <(grep -Ev "$volatile" "$dir/languages/$slug.pot") <(grep -Ev "$volatile" "$tmp/$slug.pot"); then
        echo "languages/$slug.pot is stale: run the make-pot script and commit the result." >&2
        return 1
    fi
    echo "languages/$slug.pot is up to date."
}

# Fails when a gettext call in the plugin's sources uses a domain other than <slug>, or none.
# Usage: i18n_check_text_domain <plugin dir> <slug> <sources> [allowed msgid, as the POT escapes it...]
i18n_check_text_domain() {
    local dir="$1" slug="$2" sources="$3"
    shift 3

    local tmp
    tmp="$(mktemp -d)"
    trap 'rm -rf "$tmp"; trap - RETURN' RETURN
    i18n_wp "$dir" "$tmp" i18n make-pot . /out/own.pot --slug="$slug" --domain="$slug" --include="$sources" --exclude="$I18N_EXCLUDE" --skip-audit --quiet || return 1
    i18n_wp "$dir" "$tmp" i18n make-pot . /out/other.pot --slug="$slug" --ignore-domain --subtract=/out/own.pot \
        --include="$sources" --exclude="$I18N_EXCLUDE" --skip-audit --quiet || return 1

    # The first msgid is the header; a multi-line msgid shows as msgid "" and is never allowed.
    local allowed="$tmp/allowed" foreign
    touch "$allowed"
    (( $# )) && printf 'msgid "%s"\n' "$@" > "$allowed"
    foreign="$(grep '^msgid ' "$tmp/other.pot" | tail -n +2 | grep -vxF -f "$allowed" || true)"
    if [[ -n "$foreign" ]]; then
        echo "Strings outside the $slug text domain:" >&2
        grep -B2 -xF -f <(printf '%s\n' "$foreign") "$tmp/other.pot" >&2
        return 1
    fi
    echo "Every string is on the $slug text domain."
}

# Fails when a languages/*.po misses a string of the committed POT, or holds an untranslated or fuzzy one.
# Usage: i18n_check_translations <plugin dir> <slug>
i18n_check_translations() {
    local dir="$1" slug="$2"

    local tmp
    tmp="$(mktemp -d)"
    trap 'rm -rf "$tmp"; trap - RETURN' RETURN
    cp "$dir"/languages/*.po "$tmp"/ || return 1
    # update-po adds the POT's new strings as untranslated, so a PO left behind fails below.
    i18n_wp "$dir" "$tmp" i18n update-po "languages/$slug.pot" /out --quiet || return 1

    awk '
        function unquote(s) { sub(/^[^"]*"/, "", s); sub(/"$/, "", s); return s }
        function end_msgstr() { if (part == "str" && str == "") empty = 1 }
        function end_entry() {
            end_msgstr()
            file = FILENAME; sub(/.*\//, "languages/", file)
            if (id != "" && fuzzy) { print file ": fuzzy: " id; bad = 1 }
            else if (id != "" && empty) { print file ": untranslated: " id; bad = 1 }
            id = ""; str = ""; part = ""; fuzzy = 0; empty = 0
        }
        FNR == 1 && NR > 1 { end_entry() }
        /^$/ { end_entry(); next }
        /^#,/ && /fuzzy/ { fuzzy = 1; next }
        /^#/ { next }
        /^msgid / { part = "id"; id = unquote($0); next }
        /^msgid_plural / { part = "plural"; next }
        /^msgstr/ { end_msgstr(); part = "str"; str = unquote($0); next }
        /^"/ { if (part == "id") id = id unquote($0); else if (part == "str") str = str unquote($0) }
        END { end_entry(); exit bad }
    ' "$tmp"/*.po || { echo "Translations are incomplete." >&2; return 1; }
    echo "Every translation is complete."
}
