<?php

/**
 *	Instantiate a link mapping for each site tree version URL, recursively replaying the publish into a temporary table.
 *	NOTE: If disabling the temporary table usage, ensure the default table doesn't already exist.
 *	@author Rodney Way <rodney@silverstripe.com.au>
 *
 *	@URLparameter live <{CREATE_LINK_MAPPINGS}> boolean
 */

class MisdirectionHistoricalLinkMappingsTask extends BuildTask {

	protected $title = 'Misdirection Historical Link Mapping';

	protected $description = 'Instantiate a link mapping for each site tree version URL, recursively replaying the publish history into a temporary table.';

	/**
	 *	Don't create link mapping records until the user specifically requests a live run, using the GET parameter.
	 */

	protected $live = false;

	/**
	 *	The array of link mappings to be instantiated.
	 */

	protected $linkMappings = array();

	/**
	 *	The table created when disabling the temporary table usage.
	 */

	protected static $default_table = 'LinkMapping_replay';

	protected static $use_temporary_table = true;

	protected $replayTable = '';

	protected static $db_columns = array(
		'ID' => 'Int',
		'VersionsID' => 'Int',
		'ParentID' => 'Int',
		'URLSegment' => 'Varchar(255)',
		'Version' => 'Int',
		'FullURL' => 'Text'
	);

	protected $replaceColumnString = '';

	public function run($request) {

		increase_time_limit_to();
		if($request->getVar('live')) {
			$this->live = true;
		}
		else {
			echo '<div>Running in <strong>test</strong> mode... to actually create link mappings, append <strong>?live=1</strong> to the URL...</div><br>';
		}
		$this->setupStructure();
		$records = $this->getPublishedVersionRecords();
		$this->processRecords($records);
		$this->checkAndCreateMappings();
		echo '<strong>Complete!</strong>';
	}

	/**
	 *	Retrieve the list of published site tree elements from the version table, in order to recursively process and create URL path variations from the bottom up (ensuring all changes at that point in time are covered).
	 *
	 *	@return ss query
	 */

	protected function getPublishedVersionRecords() {

		$query = new SQLSelect('ID, RecordID, ParentID, URLSegment, Version', 'SiteTree_versions', 'WasPublished = 1', 'ID ASC');
		return $query->execute();
	}

	/**
	 *	Add records to the replay table one by one, saving the current and affected children URLs.
	 *
	 *	@parameter ss query
	 */

	protected function processRecords($records) {

		foreach($records as $record) {

			// Determine if this record has an updated URL.

			$update = $this->isUpdated($record['RecordID'], $record['URLSegment'], $record['ParentID']);

			// Add this record to the replay table.

			$this->addRecord($record);
			$replayRecord = $this->getReplayRecordByID($record['RecordID']);

			// Retrieve the URL for this record.

			$URL = $this->getURLForRecord($replayRecord);
			if($URL) {
				$this->addMappingToList($URL, $replayRecord['ID']);
			}
			if($update) {

				// Generate new URLs for each child element.

				$children = $this->getChildren($replayRecord['ID']);
				$this->updateURLs($children);
			}
		}
	}

	protected function updateURLs($records) {

		foreach($records as $record) {

			// Retrieve the URL for this child element.

			$URL = $this->getURLForRecord($record);
			$this->addMappingToList($URL, $record['ID']);
			$children = $this->getChildren($record['ID']);
			$this->updateURLs($children);
		}
	}

	protected function addMappingToList($URL, $ID) {

		$this->linkMappings[$URL] = $ID;
		DB::query("UPDATE {$this->replayTable} SET FullURL = '{$URL}' WHERE ID = {$ID};");
	}

	protected function addRecord($record) {

		if($record['RecordID'] != $record['ParentID']) {

			// This is only used retain the latest version of the record.

			DB::prepared_query("REPLACE INTO {$this->replayTable}({$this->replaceColumnString}) VALUES(?, ?, ?, ?, ?);", array(
				$record['RecordID'],
				$record['ID'],
				$record['ParentID'],
				$record['URLSegment'],
				$record['Version']
			));
		}
	}

	/**
	 *	Determine whether the URL segment or parent ID has been updated for a record.
	 *
	 *	@parameter <{RECORD_ID}> integer
	 *	@parameter <{OLD_URL_SEGMENT}> string
	 *	@parameter <{OLD_PARENT_ID}> integer
	 *	@return boolean
	 */

	protected function isUpdated($ID, $oldURLSegment, $oldParentID) {

		$record = $this->getReplayRecordByID($ID);
		return (!$record || ($record && (($record['URLSegment'] != $oldURLSegment) || ($record['ParentID'] != $oldParentID))));
	}

	/**
	 *	Retrieve a record from the replay table by ID.
	 *
	 *	@parameter <{RECORD_ID}> integer
	 *	@return array
	 */

	protected function getReplayRecordByID($ID) {

		$query = new SQLSelect('*', $this->replayTable, "ID = {$ID}");
		$records = $query->execute();
		return $records->first();
	}

	/**
	 *	Retrieve the children for the given ID.
	 *
	 *	@parameter <{RECORD_ID}> integer
	 *	@return ss query
	 */

	protected function getChildren($ID) {

		$query = new SQLSelect('*', $this->replayTable, 'ParentID = ' . (int)$ID);
		return $query->execute();
	}

	/**
	 *	Recursively retrieve the entire URL for the given record.
	 *
	 *	@parameter <{RECORD}> array
	 *	@parameter <{RECURSIVE_URL}> string
	 *	@return string/boolean
	 */

	protected function getURLForRecord($record = null, $URL = null) {

		if(!$record) {
			return false;
		}
		$parentID = $record['ParentID'];
		$seg = $record['URLSegment'];
		$URL = !$URL ? $seg : "{$seg}/{$URL}";
		if($parentID == 0) {

			// The top of the chain has been reached.

			return $URL;
		}
		else {

			// Retrieve the parent element which was most recently published.

			$parentQuery = new SQLSelect('ID, ParentID, URLSegment, Version', $this->replayTable, "ID = {$parentID}", null, null, null, 1);
			$parent = $parentQuery->execute()->first();
			return $this->getURLForRecord($parent, $URL);
		}
	}

	/**
	 *	Instantiate the appropriate link mappings, making sure they are not referencing the current live URL.
	 */

	protected function checkAndCreateMappings() {

		$livePages = SiteTree::get()->map()->toArray();
		foreach($this->linkMappings as $URL => $siteTreeID) {

			// Check that the destination site tree element is live.

			if(isset($livePages[$siteTreeID])) {

				// Check that the URL is not the current live URL.

				$query = new SQLSelect('ID', $this->replayTable, "FullURL = '{$URL}'");
				if($query->count('ID') == 0) {
					echo "<div>{$siteTreeID} - {$URL}</div><br>";
					if($this->live) {
						singleton('MisdirectionService')->createPageMapping($URL, $siteTreeID);
					}
				}
			}
		}
	}

	/**
	 *	Create a database table to replay the site tree creation, based on the chronological order of the site tree version table.
	 */

	protected function setupStructure() {

		if(!(DB::get_conn() instanceof MySQLDatabase)) {
			exit('This task currently only supports <strong>MySQL</strong>...');
		}
		$replaceArray = self::$db_columns;
		unset($replaceArray['FullURL']);
		$this->replaceColumnString = implode(',', array_keys($replaceArray));
		$tableList = DB::table_list();
		if(self::$use_temporary_table || !in_array(self::$default_table, $tableList)) {
			$options = self::$use_temporary_table ? array(
				'temporary' => true
			) : null;
			$this->replayTable = DB::create_table(self::$default_table, self::$db_columns, null, $options);
		}
		else {

			// Delete all records from the table.

			$query = new SQLDelete(self::$default_table);
			$query->execute();
		}
	}

}
