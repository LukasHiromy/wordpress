<?php

namespace WPStaging\Framework\Database;

use WPStaging\Core\Utils\Logger;
use WPStaging\Framework\Adapter\PhpAdapter;

class TablesRenamer
{
    /** @var TableService */
    private $tableService;

    // eg: ['wp123456_options']
    /** @var array */
    protected $tablesBeingRenamed = [];

    // eg: ['options']
    /** @var array */
    protected $tablesBeingRenamedUnprefixed = [];

    // eg: ['wp_options']
    /** @var array */
    protected $existingTables = [];

    // eg: ['options']
    /** @var array */
    protected $existingTablesUnprefixed = [];

    /** @var array */
    protected $shortNamedTablesToRename = [];

    /** @var array */
    protected $shortNamedTablesToDrop = [];

    /** @var array */
    protected $excludedTables = [];

    /** @var int Total tables to be renamed */
    protected $totalTables = 0;

    /** @var int How many tables renamed */
    protected $tablesRenamed = 0;

    /** @var string */
    protected $productionTablePrefix;

    /** @var string */
    protected $tmpPrefix;

    /** @var string */
    protected $dropPrefix;

    /** @var bool */
    protected $renameViews;

    /** @var bool */
    protected $logEachRename = false;

    /** @var Logger */
    protected $logger = null;

    /** @var PhpAdapter */
    protected $phpAdapter;

    /** @var callable|null */
    protected $thresholdCallable = null;

    public function __construct(TableService $tableService, PhpAdapter $phpAdapter)
    {
        $this->tableService = $tableService;
        $this->phpAdapter   = $phpAdapter;
    }

    /**
     * @param string $productionTablePrefix
     * @return $this
     */
    public function setProductionTablePrefix($productionTablePrefix)
    {
        $this->productionTablePrefix = $productionTablePrefix;
        return $this;
    }

    /**
     * @param string $tmpPrefix
     * @return $this
     */
    public function setTmpPrefix($tmpPrefix)
    {
        $this->tmpPrefix = $tmpPrefix;
        return $this;
    }

    /**
     * @param string $dropPrefix
     * @return $this
     */
    public function setDropPrefix($dropPrefix)
    {
        $this->dropPrefix = $dropPrefix;
        return $this;
    }

    /**
     * @param bool $renameViews
     * @return $this
     */
    public function setRenameViews($renameViews)
    {
        $this->renameViews = $renameViews;
        return $this;
    }

    /**
     * @param bool $logEachRename
     * @return $this
     */
    public function setLogEachRename($logEachRename)
    {
        $this->logEachRename = $logEachRename;
        return $this;
    }

    /**
     * @param Logger $logger
     * @return $this
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param callable|null $thresholdCallable
     * @return $this
     */
    public function setThresholdCallable($thresholdCallable)
    {
        $this->thresholdCallable = $thresholdCallable;
        return $this;
    }

    /**
     * @param array $shortNamedTablesToRename
     */
    public function setShortNamedTablesToRename($shortNamedTablesToRename)
    {
        $this->shortNamedTablesToRename = $shortNamedTablesToRename;
        return $this;
    }

    /**
     * @param array $shortNamedTablesToDrop
     */
    public function setShortNamedTablesToDrop($shortNamedTablesToDrop)
    {
        $this->shortNamedTablesToDrop = $shortNamedTablesToDrop;
        return $this;
    }

    /**
     * @param $excludedTables
     * @return $this
     */
    public function setExcludedTables($excludedTables)
    {
        $this->excludedTables = $excludedTables;
        return $this;
    }

    /** @return int */
    public function getRenamedTables()
    {
        return $this->tablesRenamed;
    }

    /** @return int */
    public function getTotalTables()
    {
        return $this->totalTables;
    }

    /** @return array */
    public function getViewsToBeRenamed()
    {
        return $this->tablesBeingRenamedUnprefixed['views'];
    }

    public function setupRenamer()
    {
        $this->tablesBeingRenamed = [];

        $this->tablesBeingRenamed['tables'] = $this->tableService->findTableNamesStartWith($this->tmpPrefix) ?: [];
        $this->tablesBeingRenamed['views']  = [];
        if ($this->renameViews) {
            $this->tablesBeingRenamed['views'] = $this->tableService->findViewsNamesStartWith($this->tmpPrefix) ?: [];
        }

        $this->tablesBeingRenamed['all'] = array_merge($this->tablesBeingRenamed['tables'], $this->tablesBeingRenamed['views']);

        $this->totalTables = count($this->tablesBeingRenamed['all']);
        $tmpDatabasePrefix = $this->tmpPrefix;

        foreach ($this->tablesBeingRenamed as $viewsOrTables => $tableName) {
            $this->tablesBeingRenamedUnprefixed[$viewsOrTables] = array_map(function ($tableName) use ($tmpDatabasePrefix) {
                $tableName = $this->getFullNameTableFromShortName($tableName, $tmpDatabasePrefix);
                return substr($tableName, strlen($tmpDatabasePrefix));
            }, $this->tablesBeingRenamed[$viewsOrTables]);
        }

        $this->existingTables = [];
        $this->existingTables['tables'] = $this->tableService->findTableNamesStartWith($this->productionTablePrefix) ?: [];
        $this->existingTables['views']  = [];
        if ($this->renameViews) {
            $this->existingTables['views']  = $this->tableService->findViewsNamesStartWith($this->productionTablePrefix) ?: [];
        }

        $this->existingTables['all'] = array_merge($this->existingTables['tables'], $this->existingTables['views']);

        $productionTablePrefix = $this->productionTablePrefix;

        foreach ($this->existingTables as $viewsOrTables => $tableName) {
            $this->existingTablesUnprefixed[$viewsOrTables] = array_map(function ($tableName) use ($productionTablePrefix) {
                return substr($tableName, strlen($productionTablePrefix));
            }, $this->existingTables[$viewsOrTables]);
        }
    }

    /**
     * @param string $table
     * @param string $prefix
     *
     * @return string
     */
    public function getFullNameTableFromShortName($table, $prefix)
    {
        $shortTables = [];
        if ($prefix === $this->tmpPrefix) {
            $shortTables = $this->shortNamedTablesToRename;
        } elseif ($prefix === $this->dropPrefix) {
            $shortTables = $this->shortNamedTablesToDrop;
        }

        if (!array_key_exists($table, $shortTables)) {
            return $table;
        }

        return $shortTables[$table];
    }

    /**
     * @param string $table
     * @param string $prefix
     * @return false|string
     */
    public function getTableShortName($table, $prefix)
    {
        $shortTables = [];
        if ($prefix === $this->tmpPrefix) {
            $shortTables = $this->shortNamedTablesToRename;
        } elseif ($prefix === $this->dropPrefix) {
            $shortTables = $this->shortNamedTablesToDrop;
        }

        return array_search($table, $shortTables);
    }

    /**
     * Return true if all conflicting tables renamed, false otherwse
     * @return bool
     */
    public function renameConflictingTables()
    {
        $this->tableService->getDatabase()->exec('START TRANSACTION;');

        foreach ($this->getTablesThatExistInBothExistingAndTempUnprefixed() as $conflictingTableWithoutPrefix) {
            if ($this->isExcludedTable($conflictingTableWithoutPrefix)) {
                $this->tablesRenamed++;
                continue;
            }

            $currentTable = $this->productionTablePrefix . $conflictingTableWithoutPrefix;
            $tableToDrop =  $this->getTableShortName($currentTable, $this->dropPrefix);
            if ($tableToDrop === false) {
                $tableToDrop = $this->dropPrefix . $conflictingTableWithoutPrefix;
            }

            // Prefix existing table with toDrop prefix
            $this->tableService->getDatabase()->exec(sprintf(
                "RENAME TABLE `%s` TO `%s`;",
                $currentTable,
                $tableToDrop
            ));

            $this->renameTable($conflictingTableWithoutPrefix);

            if (!$this->phpAdapter->isCallable($this->thresholdCallable)) {
                continue;
            }

            if (call_user_func($this->thresholdCallable)) {
                $this->tableService->getDatabase()->exec('COMMIT;');
                return false;
            }
        }

        $this->tableService->getDatabase()->exec('COMMIT;');

        return true;
    }

    /**
     * Return true if all non-conflicting tables renamed, false otherwse
     * @return bool
     */
    public function renameNonConflictingTables()
    {
        $this->tableService->getDatabase()->exec('START TRANSACTION;');
        foreach ($this->getTablesThatExistInTempButNotInSite() as $nonConflictingTable) {
            if ($this->isExcludedTable($nonConflictingTable)) {
                continue;
            }

            $this->renameTable($nonConflictingTable);

            if (!$this->phpAdapter->isCallable($this->thresholdCallable)) {
                continue;
            }

            if (call_user_func($this->thresholdCallable)) {
                $this->tableService->getDatabase()->exec('COMMIT;');
                return false;
            }
        }

        $this->tableService->getDatabase()->exec('COMMIT;');

        return true;
    }

    public function renameTablesToDrop()
    {
        foreach ($this->getTablesThatExistInSiteButNotInTemp() as $table) {
            $fullTableName = $this->productionTablePrefix . $table;
            $tableToDrop = $this->getTableShortName($fullTableName, $this->dropPrefix);
            if ($tableToDrop === false) {
                $tableToDrop = $this->dropPrefix . $table;
            }

            $this->tableService->getDatabase()->exec(sprintf(
                "RENAME TABLE `%s` TO `%s`;",
                $fullTableName,
                $tableToDrop
            ));
        }
    }

    /**
     * Get active plugins from tmp options table
     * Update tmp options table with active plugins from production options table to reduce fatal error during renaming process
     * @return string
     */
    public function getActivePluginsToPreserve()
    {
        $tmpOptionsTable = $this->tmpPrefix . 'options';
        if (!$this->tableExists($tmpOptionsTable)) {
            return '';
        }

        $activePluginsToPreserve = $this->getOptionValue($tmpOptionsTable, 'active_plugins');
        $currentActivePlugins = $this->getOptionValue($this->productionTablePrefix . 'options', 'active_plugins');
        $this->updateOptionValue($tmpOptionsTable, 'active_plugins', $currentActivePlugins);

        return $activePluginsToPreserve;
    }

    /**
     * Get active sitewide plugins from tmp sitemeta table
     * Update tmp sitemeta table with active plugins from production options table to reduce fatal error during renaming process
     * @return string
     */
    public function getActiveSitewidePluginsToPreserve()
    {
        $tmpSiteMetaTable = $this->tmpPrefix . 'sitemeta';
        if (!$this->tableExists($tmpSiteMetaTable)) {
            return '';
        }

        $option = 'active_sitewide_plugins';
        $activePluginsToPreserve = $this->getNetworkOptionValue($tmpSiteMetaTable, $option);
        $currentActivePlugins = $this->getNetworkOptionValue($this->productionTablePrefix . 'sitemeta', $option);
        $this->updateNetworkOptionValue($tmpSiteMetaTable, $option, $currentActivePlugins);

        return $activePluginsToPreserve;
    }

    /**
     * @param string $activePlugins
     * @return bool
     */
    public function restorePreservedActivePlugins($activePlugins, $activeWpstgPlugin, $isNetworkActivatedPlugin)
    {
        if ($isNetworkActivatedPlugin) {
            return $this->updateOptionValue($this->productionTablePrefix . 'options', 'active_plugins', $activePlugins);
        }

        $activePlugins = maybe_unserialize($activePlugins);
        $activePlugins = array_filter($activePlugins, function ($pluginSlug) {

            // Disable all wp staging plugins, we will reactive current active wp staging plugin later
            if (strpos($pluginSlug, 'wp-staging') !== false) {
                return false;
            }

            return true;
        });

        // reactivating current active wp staging plugin
        $activePlugins[] = $activeWpstgPlugin;
        sort($activePlugins);

        $activePlugins = serialize($activePlugins);

        return $this->updateOptionValue($this->productionTablePrefix . 'options', 'active_plugins', $activePlugins);
    }

    /**
     * @param string $activeSitewidePlugins
     * @param string $activeWpstgPlugin
     * @return bool
     */
    public function restorePreservedActiveSitewidePlugins($activeSitewidePlugins, $activeWpstgPlugin)
    {
        $activeSitewidePlugins = maybe_unserialize($activeSitewidePlugins);
        $activeSitewidePlugins[$activeWpstgPlugin] = time();

        return $this->updateNetworkOptionValue($this->productionTablePrefix . 'sitemeta', 'active_sitewide_plugins', serialize($activeSitewidePlugins));
    }

    /**
     * @param string $tableName
     * @return bool
     */
    protected function isExcludedTable($tableName)
    {
        return in_array($tableName, $this->excludedTables);
    }

    /**
     * @return array
     */
    protected function getTablesThatExistInBothExistingAndTempUnprefixed()
    {
        return array_intersect($this->tablesBeingRenamedUnprefixed['all'], $this->existingTablesUnprefixed['all']);
    }

    /**
     * @return array
     */
    protected function getTablesThatExistInSiteButNotInTemp()
    {
        return array_diff($this->existingTablesUnprefixed['all'], $this->tablesBeingRenamedUnprefixed['all']);
    }

    /**
     * @return array
     */
    protected function getTablesThatExistInTempButNotInSite()
    {
        return array_diff($this->tablesBeingRenamedUnprefixed['all'], $this->existingTablesUnprefixed['all']);
    }

    /**
     * @param string $tableWithoutPrefix
     */
    protected function renameTable($tableWithoutPrefix)
    {
        $tmpDatabasePrefix = $this->tmpPrefix;
        $tableToRename     = $tmpDatabasePrefix . $tableWithoutPrefix;
        $tmpName           = $this->getTableShortName($tableToRename, $tmpDatabasePrefix);
        $tableAfterRenamed = $this->productionTablePrefix . $tableWithoutPrefix;
        if ($tmpName !== false) {
            $tableToRename = $tmpName;
        }

        // Rename restored table to existing table
        $database = $this->tableService->getDatabase();
        $result = $database->exec(sprintf(
            "RENAME TABLE `%s` TO `%s`;",
            $tableToRename,
            $tableAfterRenamed
        ));

        if ($result !== false) {
            $this->tablesRenamed++;
            if ($this->logEachRename && $this->logger instanceof Logger) {
                $this->logger->info("DB Rename: Renamed table {$tableToRename} to {$tableAfterRenamed}.");
            }

            return;
        }

        if ($this->logEachRename && $this->logger instanceof Logger) {
            $this->logger->warning("DB Rename: Unable to rename table {$tableToRename} to {$tableAfterRenamed}.");
        }
    }

    /**
     * @param string $tableName
     * @return bool
     */
    protected function tableExists($tableName)
    {
        $database  = $this->tableService->getDatabase()->getWpdba()->getClient();
        $tableName = $database->esc_like($tableName);
        $sql       = "SHOW TABLES LIKE '{$tableName}'";
        $result    = $database->get_results($sql, ARRAY_A);

        return !empty($result);
    }

    /**
     * @param string $tableName
     * @param string $optionName
     * @return string
     */
    protected function getOptionValue($tableName, $optionName)
    {
        $database   = $this->tableService->getDatabase()->getWpdba()->getClient();
        $optionName = $database->esc_like($optionName);
        $sql        = "SELECT option_value FROM {$tableName} WHERE option_name LIKE '{$optionName}'";
        $result     = $database->get_results($sql, ARRAY_A);
        if (empty($result)) {
            return '';
        }

        return $result[0]['option_value'];
    }

    /**
     * @param string $tableName
     * @param string $optionName
     * @param string $optionValue
     * @return bool
     */
    protected function updateOptionValue($tableName, $optionName, $optionValue)
    {
        $database   = $this->tableService->getDatabase()->getWpdba()->getClient();
        $optionName = $database->esc_like($optionName);
        $sql        = "UPDATE {$tableName} SET option_value = '{$optionValue}' WHERE option_name LIKE '{$optionName}'";

        return $database->query($sql);
    }

    /**
     * @param string $tableName
     * @param string $optionName
     * @return string
     */
    protected function getNetworkOptionValue($tableName, $optionName)
    {
        $database   = $this->tableService->getDatabase()->getWpdba()->getClient();
        $optionName = $database->esc_like($optionName);
        $sql        = "SELECT meta_value FROM {$tableName} WHERE meta_name LIKE '{$optionName}'";
        $result     = $database->get_results($sql, ARRAY_A);
        if (empty($result)) {
            return '';
        }

        return $result[0]['option_value'];
    }

    /**
     * @param string $tableName
     * @param string $optionName
     * @param string $optionValue
     * @return bool
     */
    protected function updateNetworkOptionValue($tableName, $optionName, $optionValue)
    {
        $database   = $this->tableService->getDatabase()->getWpdba()->getClient();
        $optionName = $database->esc_like($optionName);
        $sql        = "UPDATE {$tableName} SET meta_value = '{$optionValue}' WHERE meta_name LIKE '{$optionName}'";

        return $database->query($sql);
    }
}
