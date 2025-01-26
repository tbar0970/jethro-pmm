<?php
/**
 * JETHRO PMM
 * 
 * Call_DTMF class - plays DTMF tones for the submitted number
 *
 * Adapted from DTMF generator by Christian Schmidt at https://aggemam.dk/code/dtmf
 *
 * @author Tom Barrett <tom@tombarrett.id.au>
 * @version $Id: call_dtmf.class.php,v 1.4 2010/10/15 02:44:01 tbar0970 Exp $
 * @package jethro-pmm
 */
class Call_DTMF extends Call
{
	/**
	 * Execute this call
	 *
	 * @return void
	 * @access public
	 */
	function run()
	{
		//samples per second
		$sample_rate = isset($sample_rate) ? intval($sample_rate) : 8000;

		//signal length in milliseconds
		$signal_length = isset($signal_length) ? intval($signal_length) : 100;

		//break between signals in milliseconds
		$break_length = isset($break_length) ? intval($break_length) : 100;

		//pause length in milliseconds - pause character is ','
		$pause_length = isset($pause_length) ? intval($pause_length) : 500;

		//amplitude of wave file in the range 0-64
		$amplitude = isset($amplitude) ? intval($amplitude) : 64;

		//$upper_case and $lower_case specifies how letters in upper and 
		//lower case are treated. Either should have one of the following
		//values: 'abcd', 'spell' or false.
		//
		//'abcd' means that letters specify the signals A, B, C, D (some phones 
		//has special keys for these signals positioned to the right of the keys 
		//9, 6, 3, # respectively)
		//
		//'spell' means that letters spell numbers like in 1-800-CALL-NOW.
		//
		//false means that letters of the specified case cannot not be used.
		$upper_case = isset($upper_case) ? $upper_case : 'abcd';
		$lower_case = isset($lower_case) ? $lower_case : 'spell';

		//build frequency tables
		$lowfreqs = array(697, 770, 852, 941);
		$highfreqs = array(1209, 1336, 1477, 1633);
		$signals = array(
			'1', '2', '3', 'A',
			'4', '5', '6', 'B',
			'7', '8', '9', 'C',
			'*', '0', '#', 'D');
		$i = 0; foreach ($signals as $signal) {
			$low[$signal] = $lowfreqs[$i / 4] / $sample_rate * 2 * M_PI;
			$high[$signal] = $highfreqs[$i % 4] / $sample_rate * 2 * M_PI;
			$i++;
		}

		$alphabet    = 'abcdefghijklmnopqrstuvwxyz';
		$spelldigits = '22233344455566677778889999';

		$n = $_REQUEST['n'];
		if ($lower_case == 'spell') {
			$n = strtr($n, $alphabet, $spelldigits);
		} else if ($lower_case == 'abcd') {
			$n = preg_replace('/[^0-9a-d#*,]/', '', $n);
		}
		if ($upper_case == 'spell') {
			$n = strtr($n, strtoupper($alphabet), $spelldigits);
		} else if ($upper_case == 'abcd') {
			$n = preg_replace('/[^0-9A-D#*,]/', '', $n);
		}

		//remove frequently used formatting characters 
		//that are not part of the actual number
		$n = strtr($n, '+-()',
					   '    ');
		$n = str_replace(' ', '', $n);

		$output = '';

		for ($i = 0; $i < strlen($n); $i++) {
			$signal = $n[$i];
			
			if ($signal == ',') {
				$output .= str_repeat("\0", $pause_length * $sample_rate);
			} else if ($low[$signal]) {
				for ($j = 0; $j < $signal_length / 1000 * $sample_rate; $j++) {
					$output .= chr(floor($amplitude * (sin($j * $low[$signal]) +
													   sin($j * $high[$signal]))));
				}
				$output .= str_repeat("\0", $break_length / 1000 * $sample_rate);
			} else {
				//an invalid character has been encountered - stop
				break;
			}
		}

		//make sure that all output contains at least 1 byte excl. the header
		if (strlen($output) == 0) {
			$output = "\0";
		}

		//description of snd/au format available at http://www.wotsit.org/search.asp?s=music
		$output = ".snd" .                //"magic number"
			"\0\0\0\x18" .                //data offset
			$this->encode_int(strlen($output)) . //data size (0xffffffff = unknown)
			"\0\0\0\2" .                  //encoding (2 = 8-bit linear PCM, 3 = 16-bit linear PCM)
			$this->encode_int($sample_rate) .    //sample rate
			"\0\0\0\1" .                  //channels
			$output;
		ini_set('session.cache_limiter', 'none');
		header('Content-Length: ' . strlen($output));
		header('Content-Type: audio/basic');
		header('Content-Disposition: filename="' . $_REQUEST['n'] . '"');
		header('Expires: '.gmdate('D, d M Y H:i:s', strtotime('+1 year')).' GMT');
		header('Cache-Control: public, max-age='+365*24*60*60);
		header('Pragma: cache');

		print $output;
	}

	/**
	 * Encode integer
	 *
	 * @param int $n The number to encode
	 * @return string
	 * @access private
	 */
	function encode_int($n) {
		$s = '';
		for ($i = 3; $i >= 0; $i--) {
			$j = pow(256, $i);
			$s .= chr(floor($n / $j));
			if ($n > $j) $n -= $j;
		}
		return $s;
	}
}