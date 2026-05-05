# Deployer v8 Compatibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update n98-deployer from Deployer v6 to v8 by removing dead code, fixing removed APIs, and aligning with v8's native config and selector systems.

**Architecture:** Drop the custom `GetReleasesListService` (reads a CSV format removed in v8) and `setReleaseListProxyToEnv()` (was already broken). Replace `onRoles()` with v8's `select('role=X')` label selector. Fix all other call-site API breakages (removed `addDefault`, array run options, removed `stage` argument).

**Tech Stack:** PHP 8.1+, deployer/deployer ^8.0, Composer

---

## File Map

| Action | File | Change |
|--------|------|--------|
| Modify | `composer.json` | `^6.0` → `^8.0` |
| Modify | `src/Deployer/Registry.php` | `onRoles()` → `select()` |
| Modify | `src/Deployer/Recipe/Magento2Recipe.php` | Add `readlink_bin` default |
| Modify | `src/Deployer/Task/DeployTasks.php` | Remove `Deployer::addDefault()` call |
| Modify | `src/Deployer/Task/MagentoTasks.php` | Named arg `timeout:`, fix `stage` arg |
| Modify | `src/Deployer/Config/ReleaseConfig.php` | Remove dead code, drop `Context` import |
| Delete | `src/Deployer/Service/GetReleasesListService.php` | Replaced by v8 native |

---

### Task 1: Bump Deployer version constraint

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Update composer.json**

Change the require constraint:
```json
"require": {
    "deployer/deployer": "^8.0"
}
```

- [ ] **Step 2: Install dependencies**

```bash
composer require "deployer/deployer:^8.0" --no-interaction
```

Expected: resolves deployer v8.x, installs successfully.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: bump deployer/deployer to ^8.0"
```

---

### Task 2: Fix Registry — replace onRoles() with select()

**Files:**
- Modify: `src/Deployer/Registry.php`

`onRoles()` was removed in Deployer v7. The replacement is `$task->select()` with a label-expression string. Hosts must now configure labels via `->setLabels(['role' => ['db', 'web']])` instead of `->roles('db', 'web')`.

- [ ] **Step 1: Replace onRoles with select in registerTask()**

Open `src/Deployer/Registry.php`. Change the `registerTask` method from:
```php
public static function registerTask($code, $desc, \Closure $body, array $roles = null)
{
    \Deployer\desc($desc);
    $task = \Deployer\task($code, $body);

    if (is_array($roles)) {
        $task->onRoles(...$roles);
    }

    return $task;
}
```

To:
```php
public static function registerTask($code, $desc, \Closure $body, array $roles = null)
{
    \Deployer\desc($desc);
    $task = \Deployer\task($code, $body);

    if (is_array($roles) && count($roles) > 0) {
        $selector = implode(',', array_map(fn(string $role) => "role=$role", $roles));
        $task->select($selector);
    }

    return $task;
}
```

- [ ] **Step 2: Verify no other onRoles() calls remain**

```bash
grep -rn "onRoles" /Users/vladyslavpodorozhnyi/sites/n98-deployer/src/
```

Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add src/Deployer/Registry.php
git commit -m "feat: replace onRoles() with select() label selector for Deployer v8"
```

---

### Task 3: Add readlink_bin default to Magento2Recipe

**Files:**
- Modify: `src/Deployer/Recipe/Magento2Recipe.php`
- Modify: `src/Deployer/Task/DeployTasks.php`

`\Deployer\Deployer::addDefault()` no longer exists in v8. The `readlink_bin` default it was setting must now live in the recipe configuration.

- [ ] **Step 1: Add readlink_bin to Magento2Recipe::configuration()**

Open `src/Deployer/Recipe/Magento2Recipe.php`. Add the following line inside `configuration()`, after the `set('app_dir', '')` line:

```php
\Deployer\set('app_dir', '');
\Deployer\set('readlink_bin', 'readlink');
```

- [ ] **Step 2: Remove addDefault() from DeployTasks::initialize()**

Open `src/Deployer/Task/DeployTasks.php`. Remove this line from `initialize()`:

```php
\Deployer\Deployer::addDefault('readlink_bin', ['readlink']);
```

The method body should become:
```php
public static function initialize()
{
    self::initStableRelease();
    self::initReleaseName();
}
```

- [ ] **Step 3: Verify no Deployer::addDefault calls remain**

```bash
grep -rn "addDefault" /Users/vladyslavpodorozhnyi/sites/n98-deployer/src/
```

Expected: no output.

- [ ] **Step 4: Commit**

```bash
git add src/Deployer/Recipe/Magento2Recipe.php src/Deployer/Task/DeployTasks.php
git commit -m "fix: move readlink_bin default to recipe config, remove removed addDefault() call"
```

---

### Task 4: Fix MagentoTasks — named run() args and stage argument

**Files:**
- Modify: `src/Deployer/Task/MagentoTasks.php`

Two breaking changes here:
1. `run($cmd, ['timeout' => X])` — options array is removed; v8 uses named arguments.
2. `input()->getArgument('stage')` — there is no `stage` argument in v8. Use `currentHost()->getAlias()` instead.

- [ ] **Step 1: Fix run() timeout in runSetupUpgrade()**

Change `runSetupUpgrade()` from:
```php
public static function runSetupUpgrade()
{
    \Deployer\cd('{{release_path_app}}');
    \Deployer\run(\Deployer\get('php_bin') . ' bin/magento setup:upgrade --no-interaction --keep-generated', [
        'timeout' => \Deployer\get('magento_setup_upgrade_timeout', 300),
    ]);
}
```

To:
```php
public static function runSetupUpgrade()
{
    \Deployer\cd('{{release_path_app}}');
    \Deployer\run(
        \Deployer\get('php_bin') . ' bin/magento setup:upgrade --no-interaction --keep-generated',
        timeout: \Deployer\get('magento_setup_upgrade_timeout', 300),
    );
}
```

- [ ] **Step 2: Fix getArgument('stage') in updateMagentoConfig()**

Change `updateMagentoConfig()` from:
```php
public static function updateMagentoConfig()
{
    $env = \Deployer\get('config_store_env');
    if (empty($env)) {
        $env = \Deployer\input()->getArgument('stage');
    }

    $dir = \Deployer\get('config_store_dir');
    if (empty($dir)) {
        $dir = '{{release_path}}/config/store';
    }

    \Deployer\cd('{{release_path_app}}');
    \Deployer\run(\Deployer\get('php_bin') . " bin/magento config:data:import $dir $env");
}
```

To:
```php
public static function updateMagentoConfig()
{
    $env = \Deployer\get('config_store_env');
    if (empty($env)) {
        $env = \Deployer\currentHost()->getAlias();
    }

    $dir = \Deployer\get('config_store_dir');
    if (empty($dir)) {
        $dir = '{{release_path}}/config/store';
    }

    \Deployer\cd('{{release_path_app}}');
    \Deployer\run(\Deployer\get('php_bin') . " bin/magento config:data:import $dir $env");
}
```

- [ ] **Step 3: Verify no remaining array-style run() options**

```bash
grep -n "run(.*\['" /Users/vladyslavpodorozhnyi/sites/n98-deployer/src/Deployer/Task/MagentoTasks.php
```

Expected: no output.

- [ ] **Step 4: Commit**

```bash
git add src/Deployer/Task/MagentoTasks.php
git commit -m "fix: use named args for run() timeout, replace removed stage argument with currentHost()->getAlias()"
```

---

### Task 5: Clean up ReleaseConfig — remove dead code and broken Context usage

**Files:**
- Modify: `src/Deployer/Config/ReleaseConfig.php`

`Deployer\Task\Context::get()->getEnvironment()` no longer exists in v8 (Context only has `getHost()` and `getConfig()`). The `setReleaseListProxyToEnv()` method was also calling the non-existent `ReleaseConfig::getReleasesList()` method, so it was already broken. Remove it entirely. Also remove the `releases_list` override from `register()` — v8's native implementation is correct and sufficient.

- [ ] **Step 1: Rewrite ReleaseConfig.php**

Replace the entire file content of `src/Deployer/Config/ReleaseConfig.php` with:

```php
<?php
/**
 * @copyright Copyright (c) 2017 netz98 GmbH (http://www.netz98.de)
 *
 * @see LICENSE
 */

namespace N98\Deployer\Config;

use N98\Deployer\Service\GetReleasesNameService;

/**
 * ReleaseConfig
 */
class ReleaseConfig
{
    /**
     * Register Config Proxies that are executed when config is fetched the first time
     */
    public static function register()
    {
        \Deployer\set('release_path_app', function () { return ReleaseConfig::getReleasePathAppDir(); });
        \Deployer\set('shared_path_app', function () { return ReleaseConfig::getSharedPathAppDir(); });

        \Deployer\set('release_name', function () { return GetReleasesNameService::execute(); });
    }

    /**
     * @return string
     */
    public static function getReleasePathAppDir()
    {
        return self::buildAppPath('{{release_path}}');
    }

    /**
     * @return string
     */
    public static function getSharedPathAppDir()
    {
        return self::buildAppPath('{{deploy_path}}/shared');
    }

    /**
     * @return string
     */
    public static function getAppDir()
    {
        return \Deployer\get('app_dir');
    }

    /**
     * @param string $appPath
     *
     * @return string
     */
    protected static function buildAppPath($appPath)
    {
        $appdir = self::getAppDir();
        if (!empty($appdir)) {
            $appPath .= "/$appdir";
        }

        return $appPath;
    }
}
```

- [ ] **Step 2: Verify no Context import remains**

```bash
grep -n "Context\|getEnvironment\|releases_list\|getReleasesList" /Users/vladyslavpodorozhnyi/sites/n98-deployer/src/Deployer/Config/ReleaseConfig.php
```

Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add src/Deployer/Config/ReleaseConfig.php
git commit -m "fix: remove broken Context/getEnvironment usage and dead releases_list override for Deployer v8"
```

---

### Task 6: Delete GetReleasesListService

**Files:**
- Delete: `src/Deployer/Service/GetReleasesListService.php`

This service read from `.dep/releases` CSV format which no longer exists in Deployer v8. The v8 native `releases_list` config closure (in `recipe/deploy/release.php`) reads from `.dep/releases_log` JSON format and is fully sufficient. The class is no longer referenced after Task 5.

- [ ] **Step 1: Verify no remaining references to GetReleasesListService**

```bash
grep -rn "GetReleasesListService" /Users/vladyslavpodorozhnyi/sites/n98-deployer/src/
```

Expected: no output (after Task 5 removed the import from ReleaseConfig.php).

- [ ] **Step 2: Delete the file**

```bash
rm /Users/vladyslavpodorozhnyi/sites/n98-deployer/src/Deployer/Service/GetReleasesListService.php
```

- [ ] **Step 3: Commit**

```bash
git add -u src/Deployer/Service/GetReleasesListService.php
git commit -m "chore: remove GetReleasesListService — replaced by Deployer v8 native releases_list"
```

---

### Task 7: Smoke-test the autoloader and class resolution

**Files:**
- No code changes — verification only

- [ ] **Step 1: Regenerate autoloader**

```bash
composer dump-autoload
```

Expected: "Generated autoload files", no errors.

- [ ] **Step 2: Verify no fatal errors on load**

```bash
php -r "
require 'vendor/autoload.php';
use N98\Deployer\Recipe\Magento2Recipe;
use N98\Deployer\Recipe\N98Magento2Recipe;
use N98\Deployer\Registry;
use N98\Deployer\Config\ReleaseConfig;
use N98\Deployer\Task\BuildTasks;
use N98\Deployer\Task\DeployTasks;
use N98\Deployer\Task\MagentoTasks;
use N98\Deployer\Task\SystemTasks;
echo 'All classes loaded OK' . PHP_EOL;
"
```

Expected output:
```
All classes loaded OK
```

- [ ] **Step 3: Verify no references to removed Deployer v6 APIs remain**

```bash
grep -rn "onRoles\|addDefault\|getEnvironment\|Deployer\\\\Type\\\\Csv\|getArgument.*stage" /Users/vladyslavpodorozhnyi/sites/n98-deployer/src/
```

Expected: no output.

- [ ] **Step 4: Commit final state**

```bash
git add composer.json composer.lock
git commit -m "chore: verify Deployer v8 compatibility — all v6 APIs removed"
```

---

### Task 8: Update CHANGELOG and README

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `README.md`

- [ ] **Step 1: Prepend entry to CHANGELOG.md**

Add at the top of `CHANGELOG.md`:

```markdown
## 5.0.0

- upgrade to deployer 8.0.x:
    - removed `GetReleasesListService` — Deployer v8 native `releases_list` is used instead
    - removed `setReleaseListProxyToEnv()` from `ReleaseConfig` (was already broken)
    - `Registry::registerTask()` now uses `select('role=X')` label selectors instead of removed `onRoles()`
    - consumers must update host config: `->roles('db')` → `->setLabels(['role' => ['db']])`
    - consumers must update host config: `->stage('x')` → remove (use host alias as target)
    - consumers must update host config: `->configFile('x')` → `->setConfigFile('x')`
    - `run()` timeout now uses named argument syntax
    - read the deployer UPGRADE guide: https://github.com/deployphp/deployer/blob/master/UPGRADE.md
```

- [ ] **Step 2: Update README.md**

Replace the existing README content with:

```markdown
# n98-deployer
Configuration, Recipes, Tasks etc. for Deployer

## upgrade to 5.0.x (Deployer 8.x)

This version is only compatible with deployer >= 8.0.x.

### Host configuration migration

Deployer v8 removed `->roles()` and `->stage()` from the Host API. Update your host definitions:

```php
// Before (Deployer v6)
host('myserver')
    ->configFile('.ssh/config')
    ->stage('production')
    ->roles('web', 'db', 'production');

// After (Deployer v8)
host('myserver')
    ->setConfigFile('.ssh/config')
    ->setLabels(['role' => ['web', 'db', 'production']]);
```

Tasks restricted to roles (e.g. `magento:setup_upgrade` runs on hosts with `role=db`) will now
match hosts with a matching `role` label.

### Custom task migration

```php
// Before
task('mytask', function () { ... })->onRoles('staging');

// After
task('mytask', function () { ... })->select('role=staging');
```

A detailed Deployer upgrade guide: https://github.com/deployphp/deployer/blob/master/UPGRADE.md

## upgrade to 2.0.x

This version is only compatible with deployer > 5.0.x.
A detailed instruction on how to migrate your individualized deployer setup
can be found here: https://github.com/deployphp/deployer/blob/master/UPGRADE.md
```

- [ ] **Step 3: Commit**

```bash
git add CHANGELOG.md README.md
git commit -m "docs: add v5.0.0 changelog entry and migration guide for Deployer v8"
```
