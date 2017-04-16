<?php
namespace CheeseBurgames\MFX;

abstract class DatabaseUpdater {
	
	/**
	 * Retrieves the key for this updater
	 * @return string
	 */
	abstract protected function key();
	
	/**
	 * Retrieves the path to the SQL update file for this updater
	 * @return string
	 */
	abstract protected function pathToSQLFile();
	
	private static $_updatersData = NULL;
	
	public static function update() {
		$updaters = Config::get('database.updaters', array());
		if (!is_array($updaters) || empty($updaters))
			return;
		
		// Load versions and file modification times
		$dbm = DatabaseManager::open('__mfx');
		$dbm->exec('LOCK TABLES `mfx_database_updaters` WRITE');
		$sql = "SELECT `updater_key`, `updater_version`, UNIX_TIMESTAMP(`updater_file_modified`) AS `updater_filemtime` FROM `mfx_database_updaters`";
		self::$_updatersData = $dbm->getIndexed($sql, 'updater_key', DBM_OBJECT);
		
		foreach ($updaters as $updater) {
			$rc = new \ReflectionClass($updater);
			if (!$rc->isSubclassOf(__CLASS__))
				continue;
			if (self::ensureUpToDate($rc->newInstance(), $dbm) === false)
				break;
		}
		
		$dbm->exec('UNLOCK TABLES');
	}
	
	private static function ensureUpToDate(DatabaseUpdater $updater, DatabaseManager $dbmMFX) {
		$key = $updater->key();
		$pathToSQL = $updater->pathToSQLFile();
			
		// Looking for SQL file
		if (empty($pathToSQL) || !is_string($pathToSQL) || !file_exists($pathToSQL) || !is_file($pathToSQL) || !is_readable($pathToSQL)) {
			trigger_error(sprintf(dgettext('mfx', "Wrong SQL update file path for DatabaseUpdater '%s'."), $key), E_USER_ERROR);
			return false;
		}
		
		// Checking file modification time
		$mtime = filemtime($pathToSQL);
		if (array_key_exists($key, self::$_updatersData) && self::$_updatersData[$key]->updater_filemtime == $mtime)
			return true;
		
		// Fetching SQL content
		$fc = file_get_contents($pathToSQL);
		$chunks = preg_split('/^-- \[\s*(VERSION:\s*\d+)\s*\](?:\s+\[\s*(CONNECTION:\s*\S+)\s*\])?$/mU', $fc, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		
		// Updating
		$regs = NULL;
		$version = NULL;
		$dbm = NULL;
		foreach ($chunks as $chunk) {
			if ($version === NULL && !preg_match('/^VERSION:\s*(\d+)$/', $chunk)) {
				trigger_error(sprintf(dgettext('mfx', "The SQL update file should start with a VERSION token (updater key: '%s')."), $key), E_USER_ERROR);
				return;
			}
			
			if (preg_match('/^VERSION:\s*(\d+)$/', $chunk, $regs))
				$version = intval($regs[1]);
			else if (preg_match('/^CONNECTION:\s*(\S+)$/', $chunk, $regs))
				$dbm = DatabaseManager::open($regs[1]);
			else {
				if (array_key_exists($key, self::$_updatersData) && self::$_updatersData[$key]->updater_version >= $version)
					continue;
				
				$queries = preg_split('/;$/m', $chunk);
				$queries = array_map('trim', $queries);
				if ($dbm === NULL)
					$dbm = DatabaseManager::open();
				foreach ($queries as $query) {
					if (!empty($query) && $dbm->exec($query) === false) {
						trigger_error(sprintf(dgettext('mfx', "An error has occured while processing DatabaseUpdater '%s'."), $key), E_USER_ERROR);
						return false;
					}
				}
				
				$sql = "INSERT INTO `mfx_database_updaters` (`updater_key`, `updater_version`, `updater_file_modified`)
							VALUE (?, ?, FROM_UNIXTIME(?))
							ON DUPLICATE KEY UPDATE `updater_version` = VALUES(`updater_version`), `updater_file_modified` = VALUES(`updater_file_modified`)";
				if ($dbmMFX->exec($sql, $key, $version, $mtime) === false) {
					trigger_error(sprintf(dgettext('mfx', "An error has occured while processing DatabaseUpdater '%s'."), $key), E_USER_ERROR);
					return false;
				}
			}
		}
		
		return true;
	}
	
}