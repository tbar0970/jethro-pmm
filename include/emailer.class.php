<?php
require_once 'vendor/autoload.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * SwiftMailer-compatible message facade over Symfony Mime's Email.
 *
 * Callers still use SwiftMailer's method names (setSubject, setFrom, setTo,
 * setBody, addPart, attach, ...). Those are mapped onto Symfony Mime here so
 * that the scripts keep their fluent message-building style unchanged.
 */
class Jethro_Email extends Email
{
	/**
	 * Accepts the SwiftMailer forms: setFrom($email), setFrom($email, $name)
	 * or setFrom(array($email => $name)). Applies OVERRIDE_EMAIL_FROM the same
	 * way the old Jethro_Swift_Message did.
	 */
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
			parent::addReplyTo(self::_address($addresses, $name));
			$fromName = ifdef('OVERRIDE_EMAIL_FROM_NAME', '');
			parent::from(new Address(OVERRIDE_EMAIL_FROM, $fromName));
		} else {
			parent::from(self::_address($addresses, $name));
		}
		return $this;
	}

	public function setTo($addresses, $name = null)
	{
		// Sanity-check that the parent did not.
		if (is_array($addresses)) {
			foreach ($addresses as $k => $v) {
				if (is_int($k) && strlen($v) == 0) unset($addresses[$k]);
			}
		}
		if (empty($addresses)) return $this;

		return parent::to(...self::_addresses($addresses, $name));
	}

	// -- SwiftMailer method-name aliases ---------------------------------

	public function setSubject($subject)
	{
		return parent::subject($subject);
	}

	public function setCc($addresses, $name = null)
	{
		return parent::cc(...self::_addresses($addresses, $name));
	}

	public function setBcc($addresses, $name = null)
	{
		return parent::bcc(...self::_addresses($addresses, $name));
	}

	public function setReplyTo($addresses, $name = null)
	{
		return parent::replyTo(...self::_addresses($addresses, $name));
	}

	// -- Helpers ---------------------------------------------------------

	/** @return Address */
	private static function _address($address, $name = null): Address
	{
		if ($address instanceof Address) return $address;
		return new Address((string) $address, (string) ($name ?? ''));
	}

	/** @return Address[] */
	private static function _addresses($addresses, $name = null): array
	{
		if ($addresses instanceof Address) return [$addresses];
		if (is_array($addresses)) {
			$out = [];
			foreach ($addresses as $k => $v) {
				if (is_int($k)) {
					if ($v === '' || $v === null) continue;
					$out[] = new Address((string) $v);
				} else {
					if ($k === '' || $k === null) continue;
					$out[] = new Address((string) $k, (string) $v);
				}
			}
			return $out;
		}
		if ($addresses === '' || $addresses === null) return [];
		return [new Address((string) $addresses, (string) ($name ?? ''))];
	}
}

class Emailer
{
	static function newMessage()
	{
		return new Jethro_Email();
	}

	/**
	 * Send an email.
	 * @param Email $message
	 * @return bool TRUE on success, FALSE on error.
	 */
	static function send($message)
	{
		try {
			$mailer = new Mailer(self::_transport());
			$mailer->send($message);
			return TRUE;
		} catch (\Throwable $e) {
			trigger_error("Could not send email: ".$e->getMessage(), E_USER_WARNING);
			return FALSE;
		}
	}

	private static function _transport()
	{
		if (defined('SMTP_SERVER') && SMTP_SERVER) {
			$port = (defined('SMTP_PORT') && SMTP_PORT) ? SMTP_PORT : 25;
			$encryption = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : '';
			$scheme = ($encryption === 'ssl') ? 'smtps' : 'smtp';
			$userinfo = '';
			if (defined('SMTP_USERNAME') && SMTP_USERNAME) {
				$userinfo = SMTP_USERNAME;
				if (defined('SMTP_PASSWORD') && SMTP_PASSWORD) $userinfo .= ':' . SMTP_PASSWORD;
				$userinfo .= '@';
			}
			return Transport::fromDsn($scheme . '://' . $userinfo . SMTP_SERVER . ':' . $port);
		}
		return Transport::fromDsn('native://default');
	}

	static function validateAddress($email) {
		try {
			Address::create((string) $email);
			return TRUE;
		} catch (RfcComplianceException $e) {
			return FALSE;
		}
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
