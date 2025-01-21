<?php

/**
 * Static functions for identifying and fixing incorrect person Age Brackets caused by https://github.com/tbar0970/jethro-pmm/issues/1086
 * This is used as an upgrade report (upgrades/2024-upgrades-to-2.36-report-if-agebrackets-are-broken.php)
 * and by the interactive view (views/view_10_admin__10_fix_broken_age_brackets.class.php).
 */
class AgeBracketChangesFixer
{
	/**
	 * Identify persons that may have been incorrectly set to age bracket 'Adult' during a bulk edit.
	 * @return BadChangeGroup[]  An array of instances where Age Bracket may have gone wrong, each grouped by bulk edit.
	 */
	public static function getBadChangeGroups(): array
	{
		$adult = self::getDefaultAgeBracketLabel(); // usually 'Adult' - this is what the edit form defaulted to
		$sql = 'SELECT _person.* from _person 
			 JOIN age_bracket ON _person.age_bracketid = age_bracket.id
			 WHERE age_bracket.is_default=1 AND history like \'%Age bracket changed from "%" to "'.$adult.'"%\';';
		// _person db records, indexed by id
		$persons = array_reduce(
			$GLOBALS['db']->queryAll($sql),
			function ($all, $row) {
				$all[$row['id']] = $row;
				return $all;
			},
			[]);
		$dateBuggyJethroReleased = strtotime("2024-05-30");

		// The fact that N changes were made 'together' is an important indicator that bulk change (and hence the bug)
		// was involved. So our first step is to group likely problems by time.
		// $tstamp_to_affectedpersons is an array of [quantizedtimestamp -> [personid -> BadChange]]
		$tstamp_to_affectedpersons = [];
		foreach ($persons as $personid => $persondetails) {
			$hist = unserialize($persondetails['history']);
			if (!$hist) {
				echo "Could not unserialize person $personid history";
				continue;
			}
			foreach (array_reverse($hist, true) as $time => $histrecord) {
				if ($time < $dateBuggyJethroReleased) break; // Ignore history record that happened before the buggy Jethro was released
				$histlines = explode(PHP_EOL, $histrecord); // Separate lines of history record
				if (count($histlines) <= 2) continue; // Besides the 'Updated by' and 'Age bracket changed from ..' line, there must be some other change for the Age bracket change to have been accidental
				$oldAgeBrackets = array_filter(array_map(function ($line) use ($adult) {
					if (preg_match('/Age bracket changed from "(.*)" to "'.$adult.'"/', $line, $match)) {
						return $match[1];
					} else return null;
				}, $histlines));
				if (empty($oldAgeBrackets)) continue;  // This change doesn't change 'Age Bracket'.
				$oldagebracket = array_values($oldAgeBrackets)[0];
				$badchange = new BadChange($time, $personid, $oldagebracket, $adult, $persondetails, $histlines);
				$quantized_timestamp = intval($time / 10) * 10; // Group by 10s intervals in case a bulk edit took more than 1s
				$tstamp_to_affectedpersons[$quantized_timestamp][$personid] = $badchange;
				break; // We've found the most recent 'Age bracket changed' history item; older ones are irrelevant, so finish with this person.
			}
		}

		// Iterate through person changes grouped by time. Confirm the grouped changes were bulk edits, i.e. the changes were made by the same person
		$badchangegroups = [];
		foreach ($tstamp_to_affectedpersons as $time => $badchange) {
			$firstlines = array_map(function ($changeinfo) {
				return $changeinfo->getHistLines()[0];
			}, $badchange);
			if (count(array_unique($firstlines)) != 1) trigger_error("First lines in history are expected to always be 'Updated by ...", E_USER_ERROR);
			$oldagebracket = array_values(array_unique($firstlines))[0];
			if (preg_match('/Updated by (.+) \(#(\d+)\)/', $oldagebracket, $matches)) {
				$updater = $matches[1];
				$updaterid = $matches[2];
			} else {
				trigger_error('First line of change, '.$firstlines[1].' does not match expected /Updated by ... (#...)/ regex.', E_USER_ERROR);
			}
			// Get the other fields changed (e.g. 'Status'), that the user was trying to set, when they accidentally set 'Age bracket'
			$other_changed_fieldnames = array_values(array_map(function ($personinfo) use ($adult) {
				return array_values(array_map(function ($histline) {
					return preg_replace('/(.+) changed from "(.*)" to "(.*)"/', '\1', $histline);
				}, array_filter($personinfo->getHistLines(), function ($histline) use ($adult) {
					return !preg_match('/^Updated by /', $histline) &&
						!preg_match('/Age bracket changed from ".+" to "'.$adult.'"/', $histline);
				})));
			}, $badchange));
			$other_changed_fieldnames = array_unique(array_merge(...$other_changed_fieldnames));

			$badchangegroup = new BadChangeGroup($time, $updater, $updaterid, $other_changed_fieldnames, $badchange);
			$badchangegroups[$time] = $badchangegroup;
		}
		// Sort by the number of persons affected, ascending. The low-count changes are more likely to not be buggy.
		uasort($badchangegroups, fn($a, $b) => ($a->count() <=> $b->count()));
		return $badchangegroups;
	}

	/**
	 * @param BadChangeGroup[] $badchangegroups
	 * @return BadChangeFixInfo[]
	 *
	 */
	public static function fix($badchangegroups): array
	{
		$results = [];
		foreach ($badchangegroups as $id => $cg) {
			foreach ($cg->getBadChanges() as $badchange) {
				$results[] = self::fixBadChange($badchange);
			}
		}
		return $results;
	}

	/**
	 * Fix the 'Age Bracket' of all persons in a BadChange. The change will be recorded in the person's history as done by user 'system'.
	 * @param BadChange $badchange
	 * @return BadChangeFixInfo
	 */
	public static function fixBadChange(BadChange $badchange): BadChangeFixInfo
	{
		$GLOBALS['system']->includeDBClass('person');
		$person = new Person();
		$person->load($badchange->getPersonId());
		$person->setValue('age_bracketid', self::_getAgeBracketIdByLabel($badchange->getOldagebracket()));

		// We don't want the current user recorded in the issue history as the 'fixer', as this is conceptually something Jethro would fix itself.
		$_SESSION['user']['first_name'] = 'system';
		$_SESSION['user']['last_name'] = '';
		$_SESSION['id']['id'] = -1;
		$person->save();
		return new BadChangeFixInfo($person->id, $person->toString(), $person->getValue('history'), $badchange->getOldAgebracket());
}

	/**
	 * Return the default Age Bracket name, usually 'Adult'.
	 * @return string
	 */
	static function getDefaultAgeBracketLabel(): string
	{
		return $GLOBALS['db']->queryOne("select label from age_bracket where is_default=1;");
	}

	/**
	 * Return the database id of the given Age Bracket. If $label isn't found an error is triggered.
	 * @param $label e.g. 'Adult'
	 * @return int e.g. 1
	 */
	private static function _getAgeBracketIdByLabel($label) : int
	{
		$id = $GLOBALS['db']->queryOne("select id from age_bracket where label=".$GLOBALS['db']->quote($label));
		if (is_null($id)) trigger_error("No age bracket '$label'.");
		return $id;
	}
}

/**
 * A collection of BadChanges that happened at (roughly) the same time, by the same person.
 * If a BadChangeGroup contains more than one BadChange, that indicates a Bulk Edit happened and the Age Bracket changes are likely wrong.
 * If the BadChangeGroup contains just one BadChange, it might be a bulk edit (affected by the bug) or a regular edit (not affected).
 **/
class BadChangeGroup
{
	private int $quantized_time;
	private string $updater;
	private int $updaterid;
	/**
	 * @var array<string>
	 */
	private array $other_changed_fieldnames;
	/** @var BadChange[] $changes */
	private array $changes;

	public function __construct(int $quantized_time, string $updater, int $updaterid, array $other_changed_fieldnames, array $agebracket_change_info)
	{
		$this->quantized_time = $quantized_time;
		$this->updater = $updater;
		$this->updaterid = $updaterid;
		$this->other_changed_fieldnames = $other_changed_fieldnames;
		$this->changes = $agebracket_change_info;
	}

	public function getId(): int
	{
		return $this->quantized_time;
	}

	public function getUpdater(): string
	{
		return $this->updater;
	}

	public function getUpdaterid(): int
	{
		return $this->updaterid;
	}

	public function getQuantizedTimestamp(): string
	{
		return $this->quantized_time;
	}

	public function getQuantizedTime(): string
	{
		return format_datetime($this->quantized_time);
	}

	/**
	 * @return string[]
	 */
	public function getOtherChangedFieldNames(): array
	{
		return $this->other_changed_fieldnames;
	}

	/** More than one change at the same time, i.e. a bulk edit. */
	public function isBulkEdit(): bool
	{
		return count($this->changes) > 1;
	}
	/**
	 * Number of persons changed. If only one, this might not have been a bulk edit affected by the bug!
	 * @return int
	 */
	public function count(): int
	{
		return count($this->changes);
	}

	/**
	 * @return BadChange[]
	 */
	public function getBadChanges(): array
	{
		return $this->changes;
	}

	public function getAffectedPersons(): array
	{
		return array_unique(array_map(fn($c) => $c->getPersonName(), $this->changes));
	}

	public function toString(): string
	{
		return "On ".$this->getQuantizedTime().", ".$this->getUpdater()." changed ".$this->count()." ".($this->count() == 1 ? "person" : "people")." to '".AgeBracketChangesFixer::getDefaultAgeBracketLabel()."', when changing other fields ".(implode(', ', array_map(fn($s) => "'".$s."'", $this->other_changed_fieldnames))).PHP_EOL;
	}
}

/**
 * Information about a single point in time when Age Bracket changed (possibly incorrectly) to Adult.
 */
class BadChange
{
	protected $time;
	protected $personid;
	protected $oldagebracket;
	protected $newagebracket;
	protected $histlines;
	protected $persondetail;

	/**
	 * @param int $time
	 * @param int $personid
	 * @param string $oldagebracket
	 * @param array $persondetail
	 * @param array $histlines
	 */
	public function __construct(int $time, int $personid, string $oldagebracket, string $newagebracket, array $persondetail, array $histlines)
	{
		$this->time = $time;
		$this->personid = $personid;
		$this->oldagebracket = $oldagebracket;
		$this->newagebracket = $newagebracket;
		$this->histlines = $histlines;
		$this->persondetail = $persondetail;
	}

	public function getTime(): int
	{
		return $this->time;
	}

	public function getPersonid(): int
	{
		return $this->personid;
	}

	public function getPersonName(): string
	{
		return $this->persondetail['first_name'].' '.$this->persondetail['last_name'];
	}

	public function getHistlines(): array
	{
		return $this->histlines;
	}

	public function getPersondetail(): array
	{
		return $this->persondetail;
	}

	public function getNewagebracket(): string
	{
		return $this->newagebracket;
	}

	public function getOldagebracket(): string
	{
		return $this->oldagebracket;
	}
}
class BadChangeFixInfo
{
	private $personid;
	private $personname;
	private $history;
	private $agebracket;

	/**
	 * @return mixed
	 */
	public function getPersonid()
	{
		return $this->personid;
	}

	/**
	 * @return mixed
	 */
	public function getPersonName()
	{
		return $this->personname;
	}

	/**
	 * @return mixed
	 */
	public function getHistory()
	{
		return $this->history;
	}

	/**
	 * @return mixed
	 */
	public function getAgebracket()
	{
		return $this->agebracket;
	}

	public function __construct($personid, $personname, $history, $agebracket)
	{

		$this->personid = $personid;
		$this->personname = $personname;
		$this->history = $history;
		$this->agebracket = $agebracket;
	}
}