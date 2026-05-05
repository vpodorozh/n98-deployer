# CHANGELOG

## 5.0.0

- upgrade to deployer 8.0.x:
    - removed `GetReleasesListService` — Deployer v8 native `releases_list` is used instead
    - removed `setReleaseListProxyToEnv()` from `ReleaseConfig` (was already calling a non-existent method)
    - `Registry::registerTask()` now uses `select('role=X')` label selectors instead of removed `onRoles()`
    - consumers must update host config: `->roles('db')` → `->setLabels(['role' => ['db']])`
    - consumers must update host config: `->stage('x')` → remove (use host alias as target in `dep deploy <alias>`)
    - consumers must update host config: `->configFile('x')` → `->setConfigFile('x')`
    - consumers must update custom task role filters: `->onRoles('staging')` → `->select('role=staging')`
    - `run()` timeout now uses named argument: `timeout: $value` instead of `['timeout' => $value]`
    - `config_store_env` fallback now uses `currentHost()->getAlias()` instead of removed `stage` argument
    - read the deployer UPGRADE guide: https://github.com/deployphp/deployer/blob/master/UPGRADE.md

For history prior to 5.0.0 see the [original n98-deployer changelog](https://github.com/netz98/n98-deployer/blob/master/CHANGELOG.md).
