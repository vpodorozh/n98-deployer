# run-as-root/run-as-root-deployer

Deployer recipes, tasks, and configuration for Magento 2 deployments.

Forked from [netz98/n98-deployer](https://github.com/netz98/n98-deployer).

## Requirements

- PHP >= 8.1
- deployer/deployer ^8.0

## Installation

```bash
composer require run-as-root/run-as-root-deployer
```

## Usage

In your `deploy.php`:

```php
use RunAsRoot\Deployer\Recipe\Magento2Recipe;
use RunAsRoot\Deployer\Task\BuildTasks;
use RunAsRoot\Deployer\Task\DeployTasks;
use RunAsRoot\Deployer\Task\MagentoTasks;

require 'recipe/common.php';

Magento2Recipe::configuration();

// your set() overrides here

Magento2Recipe::tasks();

task('deploy', [
    'deploy:initialize',
    'deploy:prepare',
    'deploy:release',
    BuildTasks::TASK_UPLOAD_ARTIFACTS,
    'deploy:shared',
    MagentoTasks::TASK_SYMLINKS_ENABLE,
    'deploy:symlink',
    MagentoTasks::TASK_MAINTENANCE_MODE_ENABLE,
    MagentoTasks::TASK_CACHE_CLEAR,
    MagentoTasks::TASK_SETUP_UPGRADE,
    MagentoTasks::TASK_CACHE_CLEAR,
    MagentoTasks::TASK_MAINTENANCE_MODE_DISABLE,
    'cleanup',
    'success',
]);

fail('deploy', DeployTasks::TASK_ROLLBACK);
```

## Host configuration

Deployer v8 uses label-based host selection. Assign roles via `setLabels()`:

```php
host('myserver')
    ->setConfigFile('.ssh/config')
    ->setDeployPath('/var/www/myproject')
    ->setLabels(['role' => ['web', 'db', 'production']]);
```

Tasks registered with a role selector (e.g. `magento:setup_upgrade` targets `role=db`) will only
run on hosts whose `role` label contains that value.

## Available tasks

### Deploy

| Task | Constant | Description |
|------|----------|-------------|
| `deploy:initialize` | `DeployTasks::TASK_INITIALIZE` | Detect stable release and resolve release name |
| `rollback` | `DeployTasks::TASK_ROLLBACK` | Rollback to last stable release |
| `link:cachetool` | `DeployTasks::TASK_LINKCACHETOOL` | Symlink cachetool binary into release |

### Build

| Task | Constant | Description |
|------|----------|-------------|
| `build:upload_artifacts` | `BuildTasks::TASK_UPLOAD_ARTIFACTS` | Upload and extract tar artifacts |
| `build:shared_dirs_generate` | `BuildTasks::TASK_SHARED_DIRS_GENERATE` | Create shared directories on server |
| `build:change_owner_and_mode` | `BuildTasks::TASK_CHANGE_OWNER_AND_MODE` | Fix file ownership and permissions |
| `build:link_env_config` | `BuildTasks::TASK_LINK_ENV_CONFIG` | Symlink `app/etc/env.php` from shared |

### Magento

| Task | Constant | Description |
|------|----------|-------------|
| `magento:maintenance_mode_enable` | `MagentoTasks::TASK_MAINTENANCE_MODE_ENABLE` | Enable maintenance mode |
| `magento:maintenance_mode_disable` | `MagentoTasks::TASK_MAINTENANCE_MODE_DISABLE` | Disable maintenance mode |
| `magento:symlinks_enable` | `MagentoTasks::TASK_SYMLINKS_ENABLE` | Allow symlinks via n98-magerun2 |
| `magento:setup_upgrade` | `MagentoTasks::TASK_SETUP_UPGRADE` | Run `bin/magento setup:upgrade` |
| `magento:setup_downgrade` | `MagentoTasks::TASK_SETUP_DOWNGRADE` | Downgrade DB schema versions |
| `magento:config_data_export` | `MagentoTasks::TASK_CONFIG_DATA_EXPORT` | Export core_config_data to YAML |
| `magento:config_data_import` | `MagentoTasks::TASK_CONFIG_DATA_IMPORT` | Import config from store config dir |
| `magento:cms_data_import` | `MagentoTasks::TASK_CMS_DATA_IMPORT` | Import CMS data |
| `magento:cache_enable` | `MagentoTasks::TASK_CACHE_ENABLE` | Enable all caches |
| `magento:cache_disable` | `MagentoTasks::TASK_CACHE_DISABLE` | Disable all caches |
| `magento:cache_disable:fpc` | `MagentoTasks::TASK_CACHE_DISABLE_FPC` | Disable full-page cache |
| `magento:cache_clear` | `MagentoTasks::TASK_CACHE_CLEAR` | Flush all caches |
| `magento:cache_clear_config` | `MagentoTasks::TASK_CACHE_CLEAR_CONFIG` | Flush config cache only |

### System

| Task | Constant | Description |
|------|----------|-------------|
| `sys:php-fpm:restart` | `SystemTasks::TASK_PHP_FPM_RESTART` | Restart php-fpm (requires `phpfpm_service`) |
| `sys:nginx:restart` | `SystemTasks::TASK_NGINX_RESTART` | Restart nginx (requires `nginx_service`) |
| `sys:cron:stop` | `SystemTasks::TASK_CRON_STOP` | Stop cron daemon (requires `cron_service`) |
| `sys:cron:start` | `SystemTasks::TASK_CRON_START` | Start cron daemon (requires `cron_service`) |

## Configuration

Key variables with their defaults:

| Variable | Default | Description |
|----------|---------|-------------|
| `app_dir` | `''` | Subdirectory within release where Magento lives |
| `readlink_bin` | `readlink` | Path to GNU readlink binary |
| `php_bin` | `php` | PHP binary (override via `PHP_BIN` env var) |
| `artifacts_dir` | `artifacts` | Local directory containing build artifacts |
| `artifacts_compression` | `gz` | Artifact compression: `gz` or `zstd` |
| `magento_build_artifacts` | `['shop.tar.gz']` | List of artifact filenames to upload |
| `magento_setup_upgrade_timeout` | `300` | Timeout in seconds for `setup:upgrade` |
| `config_store_dir` | `{{release_path}}/config/store` | Path to config import directory |
| `config_store_env` | _(host alias)_ | Environment name passed to config importer |
| `release_name_usetimestamp` | `0` | Append timestamp to branch-based release names |
| `unique_release_id` | _(unset)_ | CI job ID or similar to ensure unique release names |
| `webserver_user` | _(unset)_ | Webserver user for chown tasks |
| `webserver_group` | _(unset)_ | Webserver group for chown tasks |
| `phpfpm_service` | _(unset)_ | Service name for php-fpm restart |
| `nginx_service` | _(unset)_ | Service name for nginx restart |
| `cron_service` | _(unset)_ | Service name for cron stop/start |
