# Alondra

This repository contains the plugin Alondra.

## How to use

You can download the plugin from https://wordpress.org/plugins/alondra/ and use it on your own WooCommerce installation.

## Support

Ask questions in the [plugin's support forum on WordPress.org](https://wordpress.org/support/plugin/alondra/). Use GitHub issues and pull requests here for reproducible bugs and contributions.

## Hook deprecation policy

The `alondra_*` actions and filters are public API that add-ons build on; `tests/test-hook-surface.php` pins the exact set the plugin fires, so a change to it is always deliberate.

- A hook is never deleted outright. It is retired with `apply_filters_deprecated()` / `do_action_deprecated()`, naming the version and its replacement, and announced in the changelog.
- A deprecated hook keeps working for at least two minor releases after the one that deprecates it (deprecated in 2.1 means it still fires in 2.2 and 2.3); it is removed only in a later release whose changelog says so.
- Adding an argument at the end is backwards compatible. Removing, reordering or changing the meaning of an argument counts as a deprecation of the hook.

## Running with Docker 🐳

### Building the Docker image

You can skip this step if the image you require is already published as `ghcr.io/midrinet/alondra:<wp>-<php>` (the registry and user come from `CONTAINER_REGISTRY` and `CONTAINER_REGISTRY_USER` in `.env`); `scripts/setup` uses a local or published tag when one exists.

To build the image, run the following command:

```bash
docker/build-image.sh
```
For building the image some parameters are required. By default, they are read from the ```.env``` file in the root of the repository. If the file doesn't exists or you need to build an image using a PHP and WordPress versions that differ from the values in the file, you can pass the following parameters to the script:

| Argument | Description |
| -------- | ------------------------------------------------------------------ |
| ```--php=<version>``` | PHP version to use |
| ```--wp=<version>``` | WordPress version to use |

Finally, if you want to push the image to the registry, you can pass the ```--push``` argument to the script.

Example:

```bash
docker/build-image.sh --php=8.2 --wp=6.7.2 --push
```

This script builds a multi-stage Docker image supporting amd64 and arm64 architectures. The image is tagged with the PHP and WordPress versions used to build it.

The store identity and the demo store assets (`docker/demo-store/` — the product images and the PDF the downloadable products are sold as) are baked into this image (`compose.yml` bind-mounts the repo's own `docker/wp-entrypoint.sh`, `docker/setup-tests.sh` and `docker/run-tests.sh` over the image's copies, so those three are always current). `scripts/setup` reuses a tag that already exists locally without pulling or rebuilding it, so after changing any of them you have to rebuild the image yourself — a teardown and a fresh setup will not do it:

```bash
scripts/teardown
docker/build-image.sh
scripts/setup
```

A push to `main` that changes `docker/` or `.env.sample` publishes the image from CI. To republish a tag by hand: `gh workflow run build-docker-image.yml --ref main -f wp=<wp> -f php=<php>`.

### Building the Composer image

The `scripts/composer` wrapper uses the image `michelescalantea/composer:2.8.5-php7.4`. Build it once from the repo root:

```bash
docker build -f docker/Dockerfile.composer -t michelescalantea/composer:2.8.5-php7.4 .
```

### VSCode Extensions

Project comes with a workspace configuration for Visual Studio Code. The following extensions are required in order to work properly:

* [PHPUnit Test Explorer](https://marketplace.visualstudio.com/items?itemName=recca0120.vscode-phpunit)
* [PHP Sniffer & Beautifier Docker](https://marketplace.visualstudio.com/items?itemName=mtbdata.vscode-phpsab-docker)
* [Code Spell Checker](https://marketplace.visualstudio.com/items?itemName=streetsidesoftware.code-spell-checker)
* [PHP Extension Pack](https://marketplace.visualstudio.com/items?itemName=xdebug.php-pack)
* [PHP Intelephense](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client)
* [ENV](https://marketplace.visualstudio.com/items?itemName=IronGeek.vscode-env)
* [Docker](https://marketplace.visualstudio.com/items?itemName=ms-azuretools.vscode-docker)
* [Better Comments](https://marketplace.visualstudio.com/items?itemName=aaron-bond.better-comments)
* [SCSS Formatter](https://marketplace.visualstudio.com/items?itemName=sibiraj-s.vscode-scss-formatter)

### Starting the environment

To setup and start the containers run:

```bash
scripts/setup
```
Optionally, you can pass the following parameters to the setup script:

| Argument | Description |
| -------- | ------------------------------------------------------------------ |
| ```--install=<0\|1>``` | Perform the installation of packages (1) or not (0). Default is 1 |

### The demo store

The environment provisions a demo store rather than a bare WordPress: **Coffee Roasters**, a small coffee roastery with a real catalog, product images, a Twenty Twenty-Five child theme from `dev/demo-theme/`, and tiered pricing groups on every coffee. It is the store the plugin's screenshots are taken on as well as the fixture the Playwright suite reads.

The catalog is created by `dev/helper/demo-store/seed.php`, which `scripts/setup` runs on every start. It is idempotent — every product, term, user and pricing group is resolved before anything is written — so running it again reports `unchanged` and costs nothing. The seeder also describes five bundle packs; those need WooCommerce Product Bundles, which this plugin does not support and the setup does not activate, so they are reported as skipped and the store ships without them.

Provisioning is one-shot per volume: WordPress is installed and configured only on first boot. To pick up a change to the entrypoint, the site identity or the theme activation, reset the environment:

```bash
scripts/teardown
scripts/setup
```

### Restoring the demo store after a test run

The Playwright suite clears Alondra's tables between tests, which deletes the demo tiered pricing groups — so after a run the product pages no longer show a tier table and the store is not presentable. Re-seed it:

```bash
curl -X POST 'http://localhost:8080/?alondra-webhook=seed_demo_store'
```

That clears Alondra's three tables and re-runs the whole seeder: the pricing groups come back with the ids the demo store expects, and everything else reports `unchanged`. Do this before taking screenshots of a store the suite has run against.

**It deletes any pricing group you created by hand.** The suite clears the tables at the start of each spec rather than the end, so the last spec's group outlives the run and keeps the lowest id — and a store without a license picks the first matching group by creation id, so that leftover would win over the re-seeded demo group and the product page would show its tiers. Restoring the demo store means starting from empty tables. Nothing outside those three tables is removed, and `scripts/setup` runs the same seeder without clearing, so your own groups survive a normal setup.

### Customization

When the setup script runs, it takes the configuration from the ```.env``` file in the root of the repository. If the file doesn't exists, it will create a new one, copying the ```.env.sample``` template. In order to customize your environment before the setup occurs, you might create your ```.env``` file. To avoid errors, is important that you make a duplicate of ```.env.sample``` and then rename it to ```.env```

You can read the ```.env.sample``` file to know what are the available configuration variables and understand the purpose of each one.

### Stopping the environment

To stop the containers and perform the cleanup operations run:

```bash
./scripts/teardown
```

Then, access to [plugin settings](http://localhost:8000/wp-admin) and login with user `admin` and password `admin`, or browse the [frontend](http://localhost:8000/?post_type=product)

### Configuration

By default, the environment is set up with the latest versions of WordPress and MariaDB.
You might like to change this behavior in some scenarios (for example, to test with a different version of WordPress/PHP). 

For those cases, make a copy of the ```.env.sample``` file in the root directory of the repository, rename it to ```.env``` and customize the values according your needs.

## Utilities

This repo contains a group of utility scripts under ```scripts/``` directory. The goal is to ease the execution of common tasks without installing additional software.

| Utility | Description |
| -------- | ------------------------------------------------------------------ |
| ```./scripts/composer <arguments>``` | This is a wrapper to run composer commands. You can define the target directory with `--working-dir=` |
| ```./scripts/pnpm <arguments>``` | This is a wrapper to run pnpm commands (the pinned version, inside Docker) |
| ```./scripts/phpcs [files...]``` | Run PHP code sniffer on the project files, or on specific files |
| ```./scripts/phpcbf [files...]``` | Automatically correct coding standard violations on the project files, or on specific files |
| ```./scripts/phpstan``` | Run PHPStan on the project files |
| ```./scripts/php-syntax-check [--php=<version>]``` | Check PHP syntax compatibility. Defaults to PHP 7.4 |
| ```./scripts/install-git-hooks``` | Install git hooks: pre-commit, pre-push, post-merge, post-checkout (see below) |
| ```./scripts/cp-sources``` | Copy WordPress Core and WooCommerce code to ```docker/``` |
| ```./scripts/setup``` | Setup and start the Docker environment |
| ```./scripts/teardown``` | Stop the Docker environment and perform cleanup operations |
| ```docker compose exec web-alondra toggle-xdebug --mode=debug``` | Turn XDebug on (`--mode=profile` to profile, `--mode=off` to turn it off). By default XDebug is disabled to improve the performance |

## Git Hooks

Three git hooks are provided to enforce code quality. Install them all once after cloning the repository:

```bash
scripts/install-git-hooks
```

### `pre-commit`

Runs automatically on every `git commit`. When PHP files are staged it will:

1. **PHPCBF** — auto-fix coding standard violations and re-stage the corrected files
2. **PHPCS** — verify no unfixable violations remain (aborts the commit if any are found)
3. **PHP syntax compatibility** — checks syntax against PHP 7.4, 8.0, 8.1, 8.2 and 8.3
4. **PHPStan** — runs static analysis at level 9 (aborts the commit on failure)

Steps 1 to 3 cover staged PHP anywhere in the repo: the plugin at the root, `dev/helper/` and `dev/demo-theme/`. Step 4 runs only when a staged file is outside `dev/`, since the plugin is the only tree `phpstan.neon` lists.

To bypass in an emergency (not recommended): `git commit --no-verify`

### `pre-push`

Runs automatically on every `git push`. Executes the full **PHPUnit** test suite inside Docker and aborts the push if any test fails.

To bypass in an emergency (not recommended): `git push --no-verify`

### `post-merge` / `post-checkout`

After a branch switch or merge, checks whether `composer.json`, `composer.lock`, `package.json`, or `pnpm-lock.yaml` have changed and prints a reminder to re-run the relevant install command:

```bash
scripts/composer install   # if PHP deps changed
scripts/pnpm install       # if Node deps changed
scripts/pnpm run build
scripts/composer install --working-dir=alondra-helper
```


## Tests

### Setup

Run following script to generate required files and initialize testing env:

```bash
docker compose exec web-alondra /usr/local/bin/setup-tests.sh
```
### Execution

```bash
docker compose exec web-alondra /usr/local/bin/run-tests.sh
```

## End to end Tests

You can use the provided utility `scripts/playwright` to run E2E tests defined in `tests-e2e` directory. This utility will run tests in a headless mode inside of a Docker container of the official image provided by the Playwright team.

Also, you can pass additional arguments to the utility to configure test execution, like this:

 ```bash
 scripts/playwright --shard=1/10 --project=chromium
 ```

 Make sure you have Alondra and Alondra Helper plugins installed and activated in your WordPress instance before running the tests.

Some examples of arguments you can append to the command above:

| Argument | Description |
| -------- | ------------------------------------------------------------------ |
| `--workers 4` | Runs 4 workers in parallel. Each worker will execute a test case. This is the default value. |
| `--debug` | Runs tests in debug mode |
| `--project=configuration-onboarding` | Execute an specific tests group. Options are defined in the `playwright.config.js` in the `projects` property. See the `name` property of each element of the array   |
| `./tests-e2e/example.spec.js` | Execute specific test file. Supports multiple file paths space separated. Also supports file name without extension and path like this: `example` |

More info at: https://playwright.dev/docs/intro

### Running using headed mode

It is possible to run Playwright in headed mode. This will open a browser window to execute the tests. For now, it is not possible by using the utility inside the container, so you need Node and pnpm on your local machine (Node ^22.22.3 or ^24.15.0, e.g. through nvm; pnpm through `corepack enable` or `npm i -g pnpm`). See system requirements at: https://playwright.dev/docs/intro#system-requirements.

Then, install browsers using this command (from the repository root):

```bash
pnpm exec playwright install
```

To run the tests in headed mode, run the following command in the repository root directory:

```bash
scripts/playwright --headed
```

Also you can use the UI Mode to run the tests. To do this, run the following command in the repository root directory:

```bash
scripts/playwright --ui
```

Note: append many arguments as needed to the command.