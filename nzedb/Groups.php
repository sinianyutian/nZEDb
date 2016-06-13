<?php
namespace nzedb;

use nzedb\db\Settings;

class Groups
{
	/**
	 * @var \nzedb\db\Settings
	 */
	public $pdo;

	/**
	 * @var ColorCLI
	 */
	public $colorCLI;

	/**
	 * Construct.
	 *
	 * @param array $options Class instances.
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Settings' => null,
			'ColorCLI' => null
		];
		$options += $defaults;

		$this->pdo = ($options['Settings'] instanceof Settings ? $options['Settings'] : new Settings());
		$this->colorCLI = ($options['ColorCLI'] instanceof ColorCLI ? $options['ColorCLI'] : new ColorCLI());
	}

	/**
	 * Returns all groups and the count of releases for each group
	 * 
	 * @return array
	 */
	public function getAll()
	{
		return $this->pdo->query(
			"SELECT g.*,
				COALESCE(COUNT(r.id), 0) AS num_releases
			FROM groups g
			LEFT OUTER JOIN releases r ON g.id = r.groups_id
			GROUP BY g.id
			ORDER BY g.name ASC",
			true, nZEDb_CACHE_EXPIRY_LONG
		);
	}

	/**
	 * Returns an associative array of groups for list selection
	 * 
	 * @return array
	 */
	public function getGroupsForSelect()
	{
		$groups = $this->getActive();
		$temp_array = [];

		$temp_array[-1] = "--Please Select--";

		foreach ($groups as $group) {
			$temp_array[$group["name"]] = $group["name"];
		}

		return $temp_array;
	}

	/**
	 * Get all properties of a single group by its ID
	 * 
	 * @param $id
	 *
	 * @return array|bool
	 */
	public function getByID($id)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT * FROM groups WHERE id = %d ", $id));
	}

	/**
	 * Get all properties of all groups ordered by name ascending
	 * 
	 * @return array
	 */
	public function getActive()
	{
		return $this->pdo->query(
			"SELECT * FROM groups WHERE active = 1 ORDER BY name ASC",
			true, nZEDb_CACHE_EXPIRY_SHORT
		);
	}

	/**
	 * Get active backfill groups ordered by name ascending
	 * 
	 * @return array
	 */
	public function getActiveBackfill()
	{
		return $this->pdo->query(
			"SELECT * FROM groups WHERE backfill = 1 AND last_record != 0 ORDER BY name ASC",
			true, nZEDb_CACHE_EXPIRY_SHORT
		);
	}

	/**
	 * Get active backfill groups ordered by the newest backfill postdate
	 * 
	 * @return array
	 */
	public function getActiveByDateBackfill()
	{
		return $this->pdo->query(
			"SELECT * FROM groups WHERE backfill = 1 AND last_record != 0 ORDER BY first_record_postdate DESC",
			true, nZEDb_CACHE_EXPIRY_SHORT
		);
	}

	/**
	 * Get all active group IDs
	 * 
	 * @return array
	 */
	public function getActiveIDs()
	{
		return $this->pdo->query(
			"SELECT id FROM groups WHERE active = 1 ORDER BY name ASC",
			true, nZEDb_CACHE_EXPIRY_SHORT
		);
	}

	/**
	 * Get all group columns by Name
	 * 
	 * @param $grp
	 *
	 * @return array|bool
	 */
	public function getByName($grp)
	{
		return $this->pdo->queryOneRow(
			sprintf("SELECT * FROM groups WHERE name = %s", $this->pdo->escapeString($grp))
		);
	}

	/**
	 * Get a group name using its ID.
	 *
	 * @param int|string $id The group ID.
	 *
	 * @return string Empty string on failure, groupName on success.
	 */
	public function getByNameByID($id)
	{
		$res = $this->pdo->queryOneRow(sprintf("SELECT name FROM groups WHERE id = %d ", $id));

		return ($res === false ? '' : $res["name"]);
	}

	/**
	 * Get a group ID using its name.
	 *
	 * @param string $name The group name.
	 *
	 * @return string Empty string on failure, groups_id on success.
	 */
	public function getIDByName($name)
	{
		$res = $this->pdo->queryOneRow(
			sprintf("SELECT id FROM groups WHERE name = %s", $this->pdo->escapeString($name))
		);

		return ($res === false ? '' : $res["id"]);
	}

	/**
	 * Set the backfill to 0 when the group is backfilled to max.
	 *
	 * @param $name
	 */
	public function disableForPost($name)
	{
		$this->pdo->queryExec(
			sprintf("
				UPDATE groups
				SET backfill = 0
				WHERE name = %s",
				$this->pdo->escapeString($name)
			)
		);
	}

	/**
	 * Gets a count of all groups in the table
	 * 
	 * @param string $groupname
	 *
	 * @return mixed
	 */
	public function getCount($groupname = "")
	{
		$res = $this->pdo->queryOneRow(
			sprintf(
				"SELECT COUNT(id) AS num
				 FROM groups
				 WHERE 1 = 1 %s",
				($groupname !== ''
					?
					sprintf(
						"AND groups.name LIKE %s ",
						$this->pdo->escapeString("%" . $groupname . "%")
					)
					: ''
				)
			)
		);

		return ($res === false ? 0 : $res["num"]);
	}

	/**
	 * Gets a count of all groups in the table by its active status
	 *
	 * @param int $active Status of the group
	 * @param string $groupname Name of the group
	 *
	 * @return mixed
	 */
	public function getCountByActive($active, $groupname = "")
	{
		$res = $this->pdo->queryOneRow(
			sprintf("
				SELECT COUNT(id) AS num
				FROM groups
				WHERE 1 = 1 %s
				AND active = {$active}",
				($groupname !== ''
					?
					sprintf(
						"AND groups.name LIKE %s ",
						$this->pdo->escapeString("%" . $groupname . "%")
					)
					: ''
				)
			)
		);

		return ($res === false ? 0 : $res["num"]);
	}

	/**
	 * @param        $start
	 * @param        $num
	 * @param string $groupname
	 *
	 * @return mixed
	 */
	public function getRange($start, $num, $groupname = "")
	{
		return $this->pdo->query(
			sprintf("
				SELECT groups.*,
				COALESCE(rel.num, 0) AS num_releases
				FROM groups
				LEFT OUTER JOIN
					(SELECT groups_id, COUNT(id) AS num
						FROM releases GROUP BY groups_id
					) rel
				ON rel.groups_id = groups.id
				WHERE 1 = 1 %s
				ORDER BY groups.name " .
					($start === false ? '' : " LIMIT " . $num . " OFFSET " . $start),
				($groupname !== ''
					?
					sprintf(
						"AND groups.name LIKE %s ",
						$this->pdo->escapeString("%" . $groupname . "%")
					)
					: ''
				)
			), true, nZEDb_CACHE_EXPIRY_SHORT
		);
	}

	/**
	 * Gets group information by its active state
	 *
	 * @param        $start
	 * @param        $num
	 * @param int    $active
	 *
	 * @param string $groupname
	 *
	 * @return mixed
	 */
	public function getRangeByActive($start, $num, $active, $groupname = "")
	{
		return $this->pdo->query(
			sprintf("
				SELECT groups.*, COALESCE(rel.num, 0) AS num_releases
				FROM groups
				LEFT OUTER JOIN
					(SELECT groups_id, COUNT(id) AS num
						FROM releases
						GROUP BY groups_id
					) rel
				ON rel.groups_id = groups.id
				WHERE 1 = 1 %s
				AND active = {$active}
				ORDER BY groups.name
				%s",
				($groupname !== ''
					?
					sprintf(
						"AND groups.name LIKE %s ",
						$this->pdo->escapeString("%" . $groupname . "%")
					)
					: ''
				),
				($start === false ? '' : " LIMIT " . $num . " OFFSET " . $start)
			), true, nZEDb_CACHE_EXPIRY_SHORT
		);
	}

	/**
	 * Update an existing group.
	 *
	 * @param array $group
	 *
	 * @return bool
	 */
	public function update($group)
	{

		$minFileString =
			($group["minfilestoformrelease"] == '' ?
				"minfilestoformrelease = NULL," : sprintf(" minfilestoformrelease = %d,",
						$this->formatNumberString($group["minfilestoformrelease"], false))
			);

		$minSizeString =
			($group["minsizetoformrelease"] == '' ?
				"minsizetoformrelease = NULL" : sprintf(" minsizetoformrelease = %d",
						$this->formatNumberString($group["minsizetoformrelease"], false))
			);

		return $this->pdo->queryExec(
			sprintf(
				"UPDATE groups
				SET name = %s, description = %s, backfill_target = %s, first_record = %s, last_record = %s,
				last_updated = NOW(), active = %s, backfill = %s, %s %s
				WHERE id = %d",
				$this->pdo->escapeString(trim($group["name"])),
				$this->pdo->escapeString(trim($group["description"])),
				$this->formatNumberString($group["backfill_target"]),
				$this->formatNumberString($group["first_record"]),
				$this->formatNumberString($group["last_record"]),
				$this->formatNumberString($group["active"]),
				$this->formatNumberString($group["backfill"]),
				$minFileString,
				$minSizeString,
				$group["id"]
			)
		);
	}

	/**
	 * Checks group name is standard and replaces any shorthand prefixes
	 * 
	 * @param string $groupName The full name of the usenet group being evaluated
	 *
	 * @return string The name of the group after replacing any shorthand prefix
	 */
	public function isValidGroup($groupName)
	{
		if (preg_match('/(\w\.)+\w/i', $groupName)) {

			return preg_replace('/^a\.b\./i', 'alt.binaries.', $groupName, 1);
		}

		return false;
	}

	/**
	 * Add a new group.
	 *
	 * @param array $group
	 *
	 * @return bool
	 */
	public function add($group)
	{
		$minFileString =
			($group["minfilestoformrelease"] == '' ? "NULL" : sprintf("%d", $this->formatNumberString($group["minfilestoformrelease"], false))
			);

		$minSizeString =
			($group["minsizetoformrelease"] == '' ? "NULL" : sprintf("%d", $this->formatNumberString($group["minsizetoformrelease"], false))
			);

		return $this->pdo->queryInsert(
			sprintf("
				INSERT INTO groups
					(name, description, backfill_target, first_record, last_record, last_updated,
					active, backfill, minfilestoformrelease, minsizetoformrelease)
				VALUES (%s, %s, %s, %s, %s, NOW(), %s, %s, %s, %s)",
				$this->pdo->escapeString(trim($group["name"])),
				(isset($group["description"]) ? $this->pdo->escapeString(trim($group["description"])) : "''"),
				(isset($group["backfill_target"]) ? $this->formatNumberString($group["backfill_target"]) : "1"),
				(isset($group["first_record"]) ? $this->formatNumberString($group["first_record"]) : "0"),
				(isset($group["last_record"]) ? $this->formatNumberString($group["last_record"]) : "0"),
				(isset($group["active"]) ? $this->formatNumberString($group["active"]) : "0"),
				(isset($group["backfill"]) ? $this->formatNumberString($group["backfill"]) : "0"),
				$minFileString,
				$minSizeString
			)
		);
	}

	/**
	 * Format numeric string when adding/updating groups.
	 *
	 * @param string $setting
	 * @param bool   $escape
	 *
	 * @return string|int
	 */
	protected function formatNumberString($setting, $escape = true)
	{
		$setting = trim($setting);
		if ($setting === "0" || !is_numeric($setting)) {
			$setting = '0';
		}

		return ($escape ? $this->pdo->escapeString($setting) : (int)$setting);
	}

	/**
	 * Delete a group.
	 *
	 * @param int|string $id ID of the group.
	 *
	 * @return bool
	 */
	public function delete($id)
	{
		$this->purge($id);

		return $this->pdo->queryExec(
			sprintf("
				DELETE FROM groups
				WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Reset a group.
	 *
	 * @param string|int $id The group ID.
	 *
	 * @return bool
	 */
	public function reset($id)
	{
		// Remove rows from collections / binaries / parts.
		(new Binaries(['Groups' => $this, 'Settings' => $this->pdo]))->purgeGroup($id);

		// Remove rows from part repair.
		$this->pdo->queryExec(sprintf("DELETE FROM missed_parts WHERE group_id = %d", $id));

		$this->pdo->queryExec(sprintf('DROP TABLE IF EXISTS collections_%d', $id));
		$this->pdo->queryExec(sprintf('DROP TABLE IF EXISTS binaries_%d', $id));
		$this->pdo->queryExec(sprintf('DROP TABLE IF EXISTS parts_%d', $id));
		$this->pdo->queryExec(sprintf('DROP TABLE IF EXISTS missed_parts_%d', $id));

		// Reset the group stats.
		return $this->pdo->queryExec(
			sprintf("
				UPDATE groups
				SET backfill_target = 1, first_record = 0, first_record_postdate = NULL, last_record = 0,
					last_record_postdate = NULL, last_updated = NULL
				WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Reset all groups.
	 *
	 * @return bool
	 */
	public function resetall()
	{
		$this->pdo->queryExec("TRUNCATE TABLE collections");
		$this->pdo->queryExec("TRUNCATE TABLE binaries");
		$this->pdo->queryExec("TRUNCATE TABLE parts");
		$this->pdo->queryExec("TRUNCATE TABLE missed_parts");
		$groups = $this->pdo->query("SELECT id FROM groups");
		foreach ($groups as $group) {
			$this->pdo->queryExec('DROP TABLE IF EXISTS collections_' . $group['id']);
			$this->pdo->queryExec('DROP TABLE IF EXISTS binaries_' . $group['id']);
			$this->pdo->queryExec('DROP TABLE IF EXISTS parts_' . $group['id']);
			$this->pdo->queryExec('DROP TABLE IF EXISTS missed_parts_' . $group['id']);
		}

		// Reset the group stats.
		return $this->pdo->queryExec("
			UPDATE groups
			SET backfill_target = 1, first_record = 0, first_record_postdate = NULL,
				last_record = 0, last_record_postdate = NULL, last_updated = NULL, active = 0"
		);
	}

	/**
	 * Purge a single group or all groups.
	 *
	 * @param int|string|bool $id The group ID. If false, purge all groups.
	 */
	public function purge($id = false)
	{
		if ($id === false) {
			$this->resetall();
		} else {
			$this->reset($id);
		}

		$releaseArray = $this->pdo->queryDirect(
			sprintf("SELECT id, guid FROM releases %s",
				($id === false ? '' : 'WHERE groups_id = ' . $id))
		);

		if ($releaseArray instanceof \Traversable) {
			$releases     = new Releases(['Settings' => $this->pdo, 'Groups' => $this]);
			$nzb          = new NZB($this->pdo);
			$releaseImage = new ReleaseImage($this->pdo);
			foreach ($releaseArray as $release) {
				$releases->deleteSingle(
					[
						'g' => $release['guid'],
						'i' => $release['id']
					],
					$nzb,
					$releaseImage
				);
			}
		}
	}

	/**
	 * Adds new newsgroups based on a regular expression match against USP available
	 *
	 * @param string $groupList
	 * @param int    $active
	 * @param int    $backfill
	 *
	 * @return array
	 */
	public function addBulk($groupList, $active = 1, $backfill = 1)
	{
		if (preg_match('/^\s*$/m', $groupList)) {
			$ret = "No group list provided.";
		} else {
			$nntp = new NNTP(['Echo' => false]);
			if ($nntp->doConnect() !== true) {
				return 'Problem connecting to usenet.';
			}
			$groups = $nntp->getGroups();
			$nntp->doQuit();

			if ($nntp->isError($groups)) {
				return 'Problem fetching groups from usenet.';
			}

			$regFilter = '/' . $groupList . '/i';

			$ret = [];

			foreach ($groups as $group) {
				if (preg_match($regFilter, $group['group']) > 0) {
					$res = $this->getIDByName($group['group']);
					if ($res === '') {
						$this->add(
							[
								'name'        => $group['group'],
								'active'      => $active,
								'backfill'    => $backfill,
								'description' => 'Added by bulkAdd',
							]
						);
						$ret[] = ['group' => $group['group'], 'msg' => 'Created'];
					}
				}
			}

			if (count($ret) === 0) {
				$ret = 'No groups found with your regex, try again!';
			}
		}

		return $ret;
	}

	/**
	 * Updates the group active status
	 * 
	 * @param     $id
	 * @param int $status
	 *
	 * @return string
	 */
	public function updateGroupStatus($id, $status = 0)
	{
		$this->pdo->queryExec(
			sprintf("
				UPDATE groups
				SET active = %d
				WHERE id = %d",
				$status,
				$id
			)
		);

		return "Group $id has been " . (($status == 0) ? 'deactivated' : 'activated') . '.';
	}

	/**
	 * Updates the group backfill status
	 * 
	 * @param     $id
	 * @param int $status
	 *
	 * @return string
	 */
	public function updateBackfillStatus($id, $status = 0)
	{
		$this->pdo->queryExec(
			sprintf("
				UPDATE groups
				SET backfill = %d
				WHERE id = %d",
				$status,
				$id
			)
		);

		return "Group $id has been " . (($status == 0) ? 'deactivated' : 'activated') . '.';
	}

	/**
	 * @var array
	 */
	private $cbppTableNames;

	/**
	 * Get the names of the collections/binaries/parts/part repair tables.
	 * If TPG is on, try to create new tables for the groups_id, if we fail, log the error and exit.
	 *
	 * @param bool $tpgSetting false, tpg is off in site setting, true tpg is on in site setting.
	 * @param int  $groupID    ID of the group.
	 *
	 * @return array The table names.
	 */
	public function getCBPTableNames($tpgSetting, $groupID)
	{
		$groupKey = ($groupID . '_' . (int)$tpgSetting);

		// Check if buffered and return. Prevents re-querying MySQL when TPG is on.
		if (isset($this->cbppTableNames[$groupKey])) {
			return $this->cbppTableNames[$groupKey];
		}

		$tables           = [];
		$tables['cname']  = 'collections';
		$tables['bname']  = 'binaries';
		$tables['pname']  = 'parts';
		$tables['prname'] = 'missed_parts';

		if ($tpgSetting === true) {
			if ($groupID == '') {
				exit('Error: You must use .../misc/update/nix/multiprocessing/releases.php since you have enabled TPG!');
			}

			if ($this->createNewTPGTables($groupID) === false && nZEDb_ECHOCLI) {
				exit('There is a problem creating new TPG tables for this group ID: ' . $groupID .
					 PHP_EOL);
			}

			$groupEnding = '_' . $groupID;
			$tables['cname'] .= $groupEnding;
			$tables['bname'] .= $groupEnding;
			$tables['pname'] .= $groupEnding;
			$tables['prname'] .= $groupEnding;
		}

		// Buffer.
		$this->cbppTableNames[$groupKey] = $tables;

		return $tables;
	}

	/**
	 * Check if the tables exist for the groups_id, make new tables for table per group.
	 *
	 * @param int $groupID
	 *
	 * @return bool
	 */
	public function createNewTPGTables($groupID)
	{
		$cbpm = ['collections', 'binaries', 'parts', 'missed_parts'];

		foreach ($cbpm as $tableName) {
			if ($this->pdo->queryExec(sprintf('SELECT * FROM %s_%s LIMIT 1', $tableName, $groupID), true) === false) {
				if ($this->pdo->queryExec(sprintf('CREATE TABLE %s_%s LIKE %s', $tableName, $groupID, $tableName), true) === false) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * @note Disable group that does not exist on USP server
	 * @param $id
	 *
	 */
	public function disableIfNotExist($id)
	{
		$this->updateGroupStatus($id, 0);
		$this->colorCLI->doEcho(
			$this->colorCLI->error(
				'Group does not exist on server, disabling'
			)
		);
	}
}
