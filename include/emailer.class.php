<?php
require_once 'vendor/autoload.php';
class Jethro_Swift_Message extends Swift_Message
{
	public function setFrom($addresses, $name = null)
	{
		if (($name === null) && (is_array($addresses))) {
			// assumption: it's an associative array email => name
			$name = reset($addresses);
			$addresses = key($addresses);
		}
		// If OVERRIDE_EMAIL_FROM is set, use it as the actual From address,
		// and use the user-supplied address as Reply-to.
		if (ifdef('OVERRIDE_EMAIL_FROM')) {
			$this->addReplyTo($addresses, $name);
			$fromName = ifdef('OVERRIDE_EMAIL_FROM_NAME', '');
			parent::setFrom(OVERRIDE_EMAIL_FROM, $fromName);
		} else {
			parent::setFrom($addresses, $name);
		}
		return $this;
	}

    public function setTo($addresses, $name = null)
    {
		// Do some sanity checking that the parent does not
		if (is_array($addresses)) {
			foreach ($addresses as $k => $v) {
				if (is_int($k) && strlen($v) == 0) unset($addresses[$k]);
			}
		}
		if (empty($addresses)) return;
		if (!is_array($addresses) && empty($addresses)) return;

        return parent::setTo($addresses, $name);
    }
}

class Emailer
{
	
	static function newMessage()
	{
		return new Jethro_Swift_Message();
	}
	
	/**
	 * Send an email
	 * @param SwiftMessage $message
	 * @return mixed - TRUE on success, or an array of failed addresses on error.
	 */
	static function send($message)
	{
		try {
			if (defined('SMTP_SERVER')) {
				$port = defined('SMTP_PORT') ? SMTP_PORT : 25;
				$transport = new Swift_SmtpTransport(SMTP_SERVER, $port);
				if (defined('SMTP_USERNAME') && SMTP_USERNAME) $transport->setUsername(SMTP_USERNAME);
				if (defined('SMTP_PASSWORD') && SMTP_PASSWORD) $transport->setPassword(SMTP_PASSWORD);
				if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION) $transport->setEncryption(SMTP_ENCRYPTION);
			} else {
				$transport = new Swift_MailTransport();
			}

			$mailer = new Swift_Mailer($transport);

			$failures = Array();
			$numSent = $mailer->send($message, $failures);

			if (empty($failures) && $numSent) return TRUE;

			return $failures;
		} catch (Exception $e) {
			trigger_error("Could not send email: ".$e->getMessage(), E_USER_WARNING);
			return FALSE;
			
		}
	}
	
	static function validateAddress($email) {
		return Swift_Validate::email($email);
	}

	/**
	 * Test SMTP connectivity with a socket connect + EHLO.
	 *
	 * @return array{success: bool, error: string, greeting: string, ehlo: string}
	 */
	static function testConnection(): array
	{
		$server = ifdef('SMTP_SERVER', '');
		if ($server === '') {
			return ['success' => false, 'error' => 'SMTP_SERVER not configured', 'greeting' => '', 'ehlo' => ''];
		}

		$port = (int) ifdef('SMTP_PORT', 25);
		$encryption = ifdef('SMTP_ENCRYPTION', '');
		$host = ($encryption === 'ssl') ? 'ssl://' . $server : $server;

		$errno = 0;
		$errstr = '';
		$socket = @stream_socket_client("$host:$port", $errno, $errstr, 10);

		if (!$socket) {
			return ['success' => false, 'error' => $errstr ?: "Error $errno", 'greeting' => '', 'ehlo' => ''];
		}

		$greeting = self::_readSmtp($socket);
		if ($greeting === '' || $greeting[0] !== '2') {
			fclose($socket);
			return ['success' => false, 'error' => 'No SMTP greeting received', 'greeting' => $greeting, 'ehlo' => ''];
		}

		fwrite($socket, "EHLO jethro\r\n");
		$ehlo = self::_readSmtp($socket);

		fwrite($socket, "QUIT\r\n");
		fclose($socket);

		if ($ehlo === '' || $ehlo[0] !== '2') {
			return ['success' => false, 'error' => 'EHLO rejected', 'greeting' => $greeting, 'ehlo' => $ehlo];
		}

		return ['success' => true, 'error' => '', 'greeting' => $greeting, 'ehlo' => $ehlo];
	}

	private static function _readSmtp($socket): string
	{
		$response = '';
		while (!feof($socket) && ($line = fgets($socket, 512)) !== false) {
			$response .= $line;
			if (isset($line[3]) && $line[3] === ' ') {
				break;
			}
		}
		return trim($response);
	}
}
