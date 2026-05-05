# n98-deployer
Configuration, Recipes, Tasks etc. for Deployer

## upgrade to 5.0.x (Deployer 8.x)

This version is only compatible with deployer >= 8.0.x.

### Host configuration migration

Deployer v8 removed `->roles()`, `->stage()`, and `->configFile()` from the Host API.
Update your host definitions:

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

Tasks restricted by role (e.g. `magento:setup_upgrade` runs on `role=db` hosts) will now
match hosts that have a `role` label containing the matching value.

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
