<?php
class bible_ref
{
	private $med_names = Array(
		'genesis',
		'exodus',
		'leviticus',
		'numbers',
		'deuteronomy',
		'joshua',
		'judges',
		'ruth',
		'1 samuel',
		'2 samuel',
		'1 kings',
		'2 kings',
		'1 chronicles',
		'2 chronicles',
		'ezra',
		'nehemiah',
		'esther',
		'job',
		'psalm',
		'proverbs',
		'ecclesiastes',
		'song of solomon',
		'isaiah',
		'jeremiah',
		'lamentations',
		'ezekiel',
		'daniel',
		'hosea',
		'joel',
		'amos',
		'obadiah',
		'jonah',
		'micah',
		'nahum',
		'habakkuk',
		'zephaniah',
		'haggai',
		'zechariah',
		'malachi',
		'matthew',
		'mark',
		'luke',
		'john',
		'acts',
		'romans',
		'1 corinthians',
		'2 corinthians',
		'galatians',
		'ephesians',
		'philippians',
		'colossians',
		'1 thessalonians',
		'2 thessalonians',
		'1 timothy',
		'2 timothy',
		'titus',
		'philemon',
		'hebrews',
		'james',
		'1 peter',
		'2 peter',
		'1 john',
		'2 john',
		'3 john',
		'jude',
		'revelation'
	);

	private $short_names = Array(
		'Gen',
		'Ex',
		'Lev',
		'Num',
		'Deut',
		'Josh',
		'Judges',
		'Ruth',
		'1 Sam',
		'2 Sam',
		'1 Ki',
		'2 Ki',
		'1 Chr',
		'2 Chr',
		'Ezra',
		'Neh',
		'Esth',
		'Job',
		'Ps',
		'Pr',
		'Eccl',
		'Song Sol',
		'Isa',
		'Jer',
		'Lam',
		'Ezek',
		'Dan',
		'Hos',
		'Joel',
		'Amos',
		'Obad',
		'Jonah',
		'Micah',
		'Nahum',
		'Hab',
		'Zeph',
		'Hag',
		'Zech',
		'Mal',
		'Matt',
		'Mk',
		'Lk',
		'Jn',
		'Acts',
		'Rom',
		'1 Cor',
		'2 Cor',
		'Gal',
		'Eph',
		'Phil',
		'Col',
		'1 Thes',
		'2 Thes',
		'1 Tim',
		'2 Tim',
		'Titus',
		'Philemon',
		'Heb',
		'Jam',
		'1 Pet',
		'2 Pet',
		'1 Jn',
		'2 Jn',
		'3 Jn',
		'Jude',
		'Rev'
	);

	private $names_to_numbers = Array(
		'genesis'=>0,
		'gen'=>0,
		'genes'=>0,
		'exodus'=>1,
		'exod'=>1,
		'ex'=>1,
		'leviticus'=>2,
		'levit'=>2,
		'lev'=>2,
		'numbers'=>3,
		'nums'=>3,
		'num'=>3,
		'deuteronomy'=>4,
		'deut'=>4,
		'joshua'=>5,
		'josh'=>5,
		'judges'=>6,
		'judg'=>6,
		'ruth'=>7,
		'1samuel'=>8,
		'1sam'=>8,
		'1sam'=>8,
		'2samuel'=>9,
		'2sam'=>9,
		'2sam'=>9,
		'1kings'=>10,
		'1ki'=>10,
		'1ki'=>10,
		'2kings'=>11,
		'2ki'=>11,
		'2ki'=>11,
		'1chronicles'=>12,
		'1chron'=>12,
		'1chr'=>12,
		'1chron'=>12,
		'1chr'=>12,
		'2chronicles'=>13,
		'2chron'=>13,
		'2chr'=>13,
		'2chr'=>13,
		'2chron'=>13,
		'ezra'=>14,
		'nehemiah'=>15,
		'nehem'=>15,
		'neh'=>15,
		'esther'=>16,
		'esth'=>16,
		'est'=>16,
		'job'=>17,
		'psalms'=>18,
		'psalm'=>18,
		'pss'=>18,
		'ps'=>18,
		'proverbs'=>19,
		'prov'=>19,
		'pr'=>19,
		'ecclesiastes'=>20,
		'eccles'=>20,
		'eccl'=>20,
		'ecc'=>20,
		'songofsolomon'=>21,
		'songofsongs'=>21,
		'songofsong'=>21,
		'sos'=>21,
		'songofsol'=>21,
		'isaiah'=>22,
		'isa'=>22,
		'jeremiah'=>23,
		'jerem'=>23,
		'jer'=>23,
		'lamentations'=>24,
		'lam'=>24,
		'ezekiel'=>25,
		'ezek'=>25,
		'daniel'=>26,
		'dan'=>26,
		'hosea'=>27,
		'hos'=>27,
		'joel'=>28,
		'jl'=>28,
		'jo'=>28,
		'amos'=>29,
		'am'=>29,
		'obadiah'=>30,
		'obd'=>30,
		'ob'=>30,
		'jonah'=>31,
		'jon'=>31,
		'micah'=>32,
		'mic'=>32,
		'nahum'=>33,
		'nah'=>33,
		'habakkuk'=>34,
		'hab'=>34,
		'zephaniah'=>35,
		'zeph'=>35,
		'haggai'=>36,
		'hag'=>36,
		'zechariah'=>37,
		'zech'=>37,
		'zec'=>37,
		'malachi'=>38,
		'mal'=>38,
		'matthew'=>39,
		'mathew'=>39,
		'matt'=>39,
		'mat'=>39,
		'mark'=>40,
		'mk'=>40,
		'luke'=>41,
		'lk'=>41,
		'john'=>42,
		'jn'=>42,
		'actsoftheapostles'=>43,
		'acts'=>43,
		'ac'=>43,
		'romans'=>44,
		'rom'=>44,
		'1corinthians'=>45,
		'1cor'=>45,
		'1cor'=>45,
		'2corinthians'=>46,
		'2cor'=>46,
		'2cor'=>46,
		'galatians'=>47,
		'gal'=>47,
		'ephesians'=>48,
		'eph'=>48,
		'philippians'=>49,
		'phil'=>49,
		'colossians'=>50,
		'col'=>50,
		'1thessalonians'=>51,
		'1thess'=>51,
		'1thes'=>51,
		'1thes'=>51,
		'2thessalonians'=>52,
		'2thess'=>52,
		'2thes'=>52,
		'2thes'=>52,
		'1timothy'=>53,
		'1tim'=>53,
		'1tim'=>53,
		'2timothy'=>54,
		'2tim'=>54,
		'2tim'=>54,
		'titus'=>55,
		'tit'=>55,
		'ti'=>55,
		'philemon'=>56,
		'hebrews'=>57,
		'heb'=>57,
		'james'=>58,
		'jam'=>58,
		'1peter'=>59,
		'1pet'=>59,
		'1pet'=>59,
		'2peter'=>60,
		'2pet'=>60,
		'2pet'=>60,
		'1john'=>61,
		'1jn'=>61,
		'1jn'=>61,
		'2john'=>62,
		'2jn'=>62,
		'2jn'=>62,
		'3john'=>63,
		'3jn'=>63,
		'3jn'=>63,
		'jude'=>64,
		'revelation'=>65,
		'rev'=>65
	);

	private $book = null;
	private $start_ch = 0;
	private $start_v = 0;
	private $end_ch = 0;
	private $end_v = 0;

	public function __construct($str)
	{
		if (empty($str)) return;
		if ($str[0] == '0') {
			list($this->book, $this->start_ch, $this->start_v, $this->end_ch, $this->end_v) = sscanf($str, '%03d_%03d:%03d-%03d:%03d');
		} else {
			$str = strtolower(str_replace(' ', '', $str));
			$pattern = "/([0-9]{0,1}([^0-9]+))(([0-9]+)([\-\:.])){0,1}(([0-9]+)([\-\:.])){0,1}(([0-9]+)([\-\:.])){0,1}([0-9]+)/";
			$matches = Array();
			preg_match($pattern, $str, $matches);
			$this->book = @$this->names_to_numbers[$matches[1]];
			if (is_null($this->book)) {
				trigger_error("Unknown bible book $matches[1]");
				return false;
			}
			if (!empty($matches[10])) {
				// four numbers
				$this->start_ch = $matches[4];
				$this->start_v = $matches[7];
				$this->end_ch = $matches[10];
				$this->end_v = $matches[12];
			} else if (!empty($matches[7])) {
				// three numbers
				$this->start_ch = $this->end_ch = $matches[4];
				$this->start_v = $matches[7];
				$this->end_v = $matches[12];
			} else if (!empty($matches[4])) {
				// two numbers
				if ($matches[5] == '-') {
					// several whole chapters
					$this->start_ch = $matches[4];
					$this->start_v = 1;
					$this->end_ch = $matches[12];
					$this->end_v = 999;
				} else {
					// a single verse
					$this->start_ch = $this->end_ch = $matches[4];
					$this->start_v = $this->end_v = $matches[12];
				}
			} else {
				// one number
				$this->start_ch = $this->end_ch = $matches[12];
				$this->start_v = 1;
				$this->end_v = 999;
			}
		}
	}

	public function toString($short=false)
	{
		if (is_null($this->book)) return '';
		$book = $short ? $this->short_names[$this->book] : ucwords($this->med_names[$this->book]);
		if ($this->start_ch == $this->end_ch) {
			if ($this->start_v == $this->end_v) {
				// single verse
				return $book.' '.$this->start_ch.':'.$this->start_v;
			} else {
				// within a single chapter
				if (($this->start_v == 1) && ($this->end_v == 999)) {
					// whole chapter
					
					return $book.' '.$this->start_ch;
				} else {
					// designated portion
					return $book.' '.$this->start_ch.':'.$this->start_v.'-'.$this->end_v;
				}
			}
		} else {
			if (($this->start_v == 1) && ($this->end_v == 999)) {
				// whole chapters
				return $book.' '.$this->start_ch.' - '.$this->end_ch;
			} else {
				// full four-number reference
				return $book.' '.$this->start_ch.':'.$this->start_v.' - '.$this->end_ch.':'.$this->end_v;
			}
		}
	}

	public function toShortString()
	{
		return $this->toString(true);
	}

	public function getLinkedShortString()
	{
		$url = str_replace('__REFERENCE__', $this->toShortString(), BIBLE_URL);
		return '<a target="bible" class="nowrap" href="'.$url.'">'.$this->toShortString().'</a>';
	}

	public function toCode()
	{
		return sprintf('%03d_%03d:%03d-%03d:%03d', $this->book, $this->start_ch, $this->start_v, $this->end_ch, $this->end_v);
	}

	public function printJSRegex()
	{
		$books = implode('|', array_keys($this->names_to_numbers));
		echo '/^('.$books.')(([0-9]+)([\-\:.])){0,1}(([0-9]+)([\-\:.])){0,1}(([0-9]+)([\-\:.])){0,1}([0-9]+)$/gi';
	}


}
