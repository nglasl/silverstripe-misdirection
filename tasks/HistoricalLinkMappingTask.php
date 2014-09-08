<?php
/**
 * Creates a LinkMapping record for all previous SiteTree URL's still in use
 * - will not create link mappings for SitreTree objects that no longer exist
 * 
 * Approach:
 * Loop through all records in the SiteTree_versions table, replaying the 'publish' action
 * into a temporary table (replay_table). At each point of record creation, collect all affected
 * URL's based on the following conditions:
 * 
 * If the SiteTree_versions record reflects a change in the URLSegment or ParentID, then;
 * 1. Collect the URL for addition to LinkMapping at end of this script
 * 2. Recurse downward through all children, and collect their new URL's 
 * 
 * Note:
 * 1. The RecordID value in the SiteTree_Versions table becomes the ID value in the replay_table
 * 2. The MySQL 'Replace Into' query method is used to only retain the latest version of the record
 * 3. Pages that are Moved are picked up by subsequent publishes - no special handling required
 * 4. Pages that are deleted are handled by cascade operation (assumes SiteTree::$enforce_strict_hierarchy = true)
 * 
 * Warning: 
 * If you elect to turn off temporary table usage, please ensure you don't have an existing named table matching $default_table 
 * 
 * Configuration:
 * 	- $use_temporary_table   	(default = true) turn off to examine results in replay table after connection completes
 *  - $live						(default = false) pass through true when ready to create LinkMapping records
 * 
 * @todo Remove MySQL reliance
 * 
 * 
 * @package silverstripe-linkmapping
 * @subpackage tasks
 * @author rodney@silverstripe.com.au
 */
class HistoricalLinkMappingTask extends BuildTask {
	
	protected $title = "Create Link Mappings for SiteTree Versions";
	
	protected $description = "Create a link mapping entry for each previous URL version of existing pages. Checking is performed
								to ensure that duplicate link mapping records are not created.";
	
	protected $live = false; // Don't create LinkMapping records until user specifically requests live run, with get param live=true
	
	protected $linkmappings = array(); 	// Array of linkmappings to be reviewed & created
	
	protected static $default_table = 'LinkMapping_replay';	// Use, unless Temporary Table is set to true

	protected static $use_temporary_table = true;
	protected $replay_table = '';

	// The following is defined in seconds, and by default will be 10 minutes.

	protected static $time_limit = 600;

	protected static $db_columns = array(
		'ID'			=> 'int',
		'VersionsID'	=> 'int',
		'ParentID'		=> 'int',
		'URLSegment'	=> 'varchar(255)',
		'Version'		=> 'int',
		'FullURL'		=> 'text'
	);
	
	protected $replaceColumnString = '';

	public function run($request) {

		// Enforce a maximum execution time limit, which by default will be 10 minutes.

		increase_time_limit_to(self::$time_limit);
		if ($request->getVar('live') == 'true') {
			$this->live = true;
		} else {
			echo '<br />Running in Test mode... to actually create Link Mappings, please add ?live=true to url.<br /><br />';
		}

		$this->setupStructure();

		$records = $this->publishedVersionRecords();
		$this->processRecords($records);

		$this->checkAndCreateMappings();

		echo '<br />Done';
	}


	/**
	 * Establish a full list of Published Pages from the Versioned Table, in order to recusively
	 * process and create url path variations from.
	 * 
	 * Overall, this requires a bottom up approach to ensure that all changes in URL path up until that point in time
	 * are covered off.
	 * 
	 * @return SQLQuery resource
	 */
	protected function publishedVersionRecords() {
		$query = new SQLQuery (
			'"ID","RecordID","ParentID", "URLSegment","Version"',
			'"SiteTree_versions"',
			'"WasPublished" = 1',
			'"ID" ASC',
			null,
			null,
			null
		);
		
		$records = $query->execute();
		return $records;
	}
	

	/**
	 * Add Records to replay table one by one, saving the current URL, and any affected children URL's along the way
	 * 
	 * @param DB_Resource_Record Records to process
	 */
	protected function processRecords($records) {
		
		foreach ($records as $record) {
			
			if ($record["RecordID"] == 8) {
				$debugStop = true;
			}
			
			//check if this record will affect the URL of itself or descendents - prior to updating
			$update = $this->urlUpdateRequired($record["RecordID"], $record["URLSegment"], $record["ParentID"]);
			
			//add record to replay table
			$this->addRecord($record);

			//
			$replayRecord = $this->getReplayRecordByID($record["RecordID"]);
			$url = $this->getURLForRecord($replayRecord);
			
			if ($url) {					
				$this->addMappingToList($url, $replayRecord["ID"]);				
			}
			
			//get url for this record
			if ($update) {
				// generate new urls for all children -- given that they'll be affected
				$children = $this->childPages($replayRecord["ID"]);
				$this->updateURLs($children);
			}
			
		}
	}
	
	protected function updateURLs($records) {
		foreach ($records as $record) {
			// get child url mapping
			$url = $this->getURLForRecord($record);
			$this->addMappingToList($url, $record["ID"]);
			
			$children = $this->childPages($record["ID"]);
			$this->updateURLs($children);
		}
	}
	
	protected function addMappingToList($url, $id) {
		$this->linkmappings[$url] = $id;
		DB::query("UPDATE ".$this->replay_table." SET \"FullURL\"='".$url."' WHERE \"ID\"=".$id);		
	}

	protected function addRecord($record) {
		//@todo - remove reliance on MySQL (or test that this works with other db's)

		if($record["RecordID"] != $record["ParentID"]) {
			$sql = 'REPLACE INTO '.$this->replay_table.' '.
					'('.$this->replaceColumnString.')'.
					' VALUES ('.
					"'".$record["RecordID"]."'".
					",'".$record["ID"]."'".
					",'".$record["ParentID"]."'".
					",'".Convert::raw2sql($record["URLSegment"])."'".
					",'".$record["Version"]."'".
			')';

			DB::query($sql);
		}
	}
	
	/**
	 * Perform recursive url lookup if URLSegment or ParentID for the current
	 * record has changed
	 * 
	 * @param int $id
	 * @param string $oldURLSegment
	 * @param int $oldParentID
	 * 
	 * @return boolean
	 */
	protected function urlUpdateRequired($id, $oldURLSegment, $oldParentID) {		
		$record = $this->getReplayRecordByID($id);
		
		if (( $record && ($record["URLSegment"] != $oldURLSegment || $record["ParentID"] != $oldParentID)
				) || (
					!$record
				)) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Get Record from Replay Table by ID
	 * 
	 * @param Int $id
	 */
	protected function getReplayRecordByID($id) {
		$query = new SQLQuery (
			'*',
			$this->replay_table,
			'"ID" = '.$id
		);
		$records = $query->execute();
		
		$record = $records->first();
		
		return $record;
	}
	
	/**
	 * Return Child Records for the given ID
	 * 
	 * @param int $id SiteTreeID
	 * @return SS_Query All child records
	 * 
	 */
	protected function childPages($id) {
		$query = new SQLQuery (
			'*',
			$this->replay_table,
			'"ParentID" = '.intval($id)
		);
		
		$record = $query->execute();
		return $record;
	}
	
	/**
	 * Recursively get the full URL for the given record
	 * 
	 * Note: In the event that a 'hole' present in the tree 
	 * 
	 * @param SiteTree_version $record  - database record
	 */
	protected function getURLForRecord($record = null, $url = null) {
		if (!$record) {
			return false;
		}
		
		$parentID 	= $record["ParentID"];
		$seg 		= $record["URLSegment"];
		$version	= $record["Version"];
		$id			= $record["ID"];
		
		if (!$url) {
			$url = $seg;
		} else {
			$url = $seg.'/'.$url;
		}
				
		if ($parentID == 0) {
			//have reached top of chain... all done
			return $url;
		} else {
			//Get most recently published Parent
			$parentQuery = new SQLQuery (
				'"ID","ParentID", "URLSegment","Version"',
				'"'.$this->replay_table.'"',
				'"ID"='.$parentID,
				null,
				null,
				null,
				1
			);
			
			$parent = $parentQuery->execute()->first();
			return $this->getURLForRecord($parent, $url);
		}
	}


	/**
	 * Create LinkMappings (the whole point of this task) skipping records that;
	 * - are the current live url
	 * - have been un-published
	 * 
	 */
	protected function checkAndCreateMappings() {
		$livePages = DataObject::get('SiteTree');
		$livePageArray = $livePages->map()->toArray();
		
		foreach ($this->linkmappings as $url => $SiteTreeID) {
			
			// Check if destination page is still live...
			if (isset($livePageArray[$SiteTreeID])) {
				
				// Finally, ensure that URL != current live URL
				$query = new SQLQuery (
							'ID', 
							$this->replay_table, 
							'"FullURL"='.'\''.$url.'\''
				);
				
				if ($query->count('ID') == 0) {
					print($SiteTreeID.' - '.$url.'<br />');
					
					if ($this->live) {
						singleton('SiteTree')->createLinkMapping($url, $SiteTreeID);
					}
				}
			}
		}
	}	
		
		
	/**
	 * Method creates a Database Table of the minimal columns requried to replay the SiteTree
	 * creation based on the chronological order of the SiteTree_versions table
	 * 
	 * @return boolean Success or Failure
	 */
	protected function setupStructure() {
		//@todo - implement alternative sql calls for Sqlite and PostgreSQL (if needed - pending testing)
		if (DB::getConn()->getDatabaseServer() != 'mysql') {
			throw new Exception('This task currently only supports mysql...');
		}
		
		$replaceArray = self::$db_columns;
		unset($replaceArray["FullURL"]);
		$this->replaceColumnString = implode(',',array_keys($replaceArray));
		
		$tableList = DB::tableList();
		
		if (self::$use_temporary_table || !in_array(self::$default_table,$tableList)) {
			
			$options = self::$use_temporary_table ? array('temporary'=>true) : null;
			
			$this->replay_table = DB::createTable(self::$default_table, self::$db_columns, null, $options);
			
		} else {
			//Delete all records from table
			$query = new SQLQuery (
				'',
				$table
			);
			
			$query->setDelete(true);
			$query->execute();
		}
	}		
	
}

