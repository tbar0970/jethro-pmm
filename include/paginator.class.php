<?php
class Paginator
{
	private $_slice_size = 26;
	private $_slice_num = 1;

	public function __construct($slice_size=26, $slice_num=1)
	{
		$this->_slice_size = max(1, min(26, (float)$slice_size));
		$this->_slice_num = max(1, min(26, (int)$slice_num));
	}

	public function getCurrentSliceStartEnd()
	{
		$x = 'A';
		$y = chr(ord($x) + round($this->_slice_size - 1));
		$i = 1;
		while (ord($x) <= ord('Z') && $i <= 26) {
			$y = chr(ord($x) + round($this->_slice_size - 1));
			if (ord($y) > ord('Z')) $y = NULL; // include staff after Z in last batch
			if ($i == $this->_slice_num) {
				if ($x == 'A') $x = NULL; // include stuff before A in first batch
				return Array($x, $y);
			}
			$x = chr(ord($y) + 1);
			$i++;
		}
		return Array($x, NULL);
	}

	public function printPageNav()
	{
		echo '<div class="pagination"><ul>';
		$x = 'A';
		$i = 1;
		while (ord($x) <= ord('Z') && $i <= 26) {
			$y = chr(ord($x) + round($this->_slice_size - 1));
			if (ord($y) > ord('Z')) $y = 'Z';
			if ($i != $this->_slice_num) {
				echo '<li><a href="'.build_url(Array('slice_size' => round($this->_slice_size, 1), 'slice_num' => $i)).'">';
			} else {
				echo '<li class="active"><a href="#">';
			}
			echo ($x == $y) ? $x : ($x.' - '.$y);
			echo '</a></li>';
			$x = chr(ord($y) + 1);
			$i++;
		}
		echo '</ul></div>';
	}

}
