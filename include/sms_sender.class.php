<?php
Class SMS_Sender
{
	/**
	 * Send an SMS message
	 * @param string $message
	 * @param array $recips	Array of person records
	 * @return array('success' => bool, 'successes' => array, 'failures', array)
	 */
	public static function send($message, $recips)
	{
		$response = '';
		$success = false;
		$content = SMS_HTTP_POST_TEMPLATE;
		$content = str_replace('_USER_MOBILE_', urlencode($GLOBALS['user_system']->getCurrentUser('mobile_tel')), $content);
		$content = str_replace('_USER_EMAIL_', urlencode($GLOBALS['user_system']->getCurrentUser('email')), $content);
		$content = str_replace('_MESSAGE_', urlencode($message), $content);

		$mobile_tels = Array();
		foreach ($recips as $recip) {
			if (strlen($recip['mobile_tel'])) {
				$mobile_tels[] = $recip['mobile_tel'];
			}
		}
		$mobile_tels = array_unique($mobile_tels);

		$content = str_replace('_RECIPIENTS_COMMAS_', urlencode(implode(',', $mobile_tels)), $content);
		$content = str_replace('_RECIPIENTS_NEWLINES_', urlencode(implode("\n", $mobile_tels)), $content);
		if (ifdef('SMS_RECIPIENT_ARRAY_PARAMETER')) {
			$content = str_replace('_RECIPIENTS_ARRAY_', SMS_RECIPIENT_ARRAY_PARAMETER . '[]=' . implode('&' . SMS_RECIPIENT_ARRAY_PARAMETER . '[]=', $mobile_tels), $content);
		}
		if (strlen(ifdef('SMS_LOCAL_PREFIX'))
				&& strlen(ifdef('SMS_INTERNATIONAL_PREFIX'))
				&& FALSE !== strpos(SMS_HTTP_POST_TEMPLATE, '_RECIPIENTS_INTERNATIONAL')
		) {
			$intls = Array();
			foreach ($mobile_tels as $t) {
				$intls[] = self::internationalizeNumber($t);
			}
			$content = str_replace('_RECIPIENTS_INTERNATIONAL_COMMAS_', urlencode(implode(',', $intls)), $content);
			$content = str_replace('_RECIPIENTS_INTERNATIONAL_NEWLINES_', urlencode(implode("\n", $intls)), $content);
			if (ifdef('SMS_RECIPIENT_ARRAY_PARAMETER')) {
				$content = str_replace('_RECIPIENTS_INTERNATIONAL_ARRAY_', SMS_RECIPIENT_ARRAY_PARAMETER . '[]=' . implode('&' . SMS_RECIPIENT_ARRAY_PARAMETER . '[]=', $intls), $content);
			}
		}

		$header = "" . ifdef('SMS_HTTP_HEADER_TEMPLATE', '');
		$header = $header . "Content-Length: " . strlen($content) . "\r\n"
				. "Content-Type: application/x-www-form-urlencoded\r\n";

		$opts = Array(
			'http' => Array(
				'method' => 'POST',
				'content' => $content,
				'header' => $header
			)
		);
		// Convert errors to exceptions so we can catch them
		set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
			// error was suppressed with the @-operator
			if (0 === error_reporting()) {
				return false;
			}
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});
		try {
			$fp = @fopen(SMS_HTTP_URL, 'r', false, stream_context_create($opts));
			if (!$fp) {
				$http_error = "ERROR: Unable to connect to SMS Server.<br>" . join("<br>", $http_response_header);
				return array("success" => false, "successes" => array(), "failures" => array(), "rawresponse" => $http_error);
			} else {
				$response = stream_get_contents($fp);
				fclose($fp);
			}
		} catch (Exception $e) {
			$error = "ERROR: Unable to connect to SMS Server. " + $e->getMessage();
			return array("success" => false, "successes" => array(), "failures" => array(), "rawresponse" => $error);
		}
		restore_error_handler(); // Restore system error_handler
		$success = !empty($response);
		$successes = $failures = Array();
		if ($success) {
			$response = str_replace("\r", '', $response);
			if (SMS_HTTP_RESPONSE_OK_REGEX) {
				foreach ($recips as $id => $recip) {
					$reps['_RECIPIENT_INTERNATIONAL_'] = self::internationalizeNumber($recip['mobile_tel']);
					$reps['_RECIPIENT_'] = $recip['mobile_tel'];
					$pattern = '/' . str_replace(array_keys($reps), array_values($reps), SMS_HTTP_RESPONSE_OK_REGEX) . '/m';
					if (preg_match($pattern, $response)) {
						$successes[$id] = $recip;
					} else {
						$failures[$id] = $recip;
					}
				}
				self::logSuccess(count($successes), $message);
			} else {
				self::logSuccess(count($mobile_tels), $message);
			}
		}

		return array("success" => $success, "successes" => $successes, "failures" => $failures, "rawresponse" => $response, "rawrequest" => $opts);
	}

	/**
	 * Returns the international version of the supplied number
	 * @see config: SMS_LOCAL_PREFIX SMS_INTERNATIONAL_PREFIX
	 * @param string $number  Number in local format
	 * @return string  Nummber in international format, if prefixes configured, else unchanged number
	 */
	private static function internationalizeNumber($number)
	{
		if (strlen(ifdef('SMS_LOCAL_PREFIX'))
			&& strlen(ifdef('SMS_INTERNATIONAL_PREFIX'))
			&& (0 === strpos($number, SMS_LOCAL_PREFIX))
		) {
			$number = SMS_INTERNATIONAL_PREFIX . substr($number, strlen(SMS_LOCAL_PREFIX));
		}
		return $number;
	}

	private static function logSuccess($recip_count, $message)
	{
		if (defined('SMS_SEND_LOGFILE') && ($file = constant('SMS_SEND_LOGFILE'))) {
			$msg_trunc = strlen($message) > 30 ? substr($message, 0, 27) . '...' : $message;
			error_log(date('Y-m-d H:i') . ': ' . $GLOBALS['user_system']->getCurrentUser('username') . ' (#' . $GLOBALS['user_system']->getCurrentUser('id') . ') to ' . (int) $recip_count . ' recipients: "' . $msg_trunc . "\"\n", 3, $file);
		}
	}
}

