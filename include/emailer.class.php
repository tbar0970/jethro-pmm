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
			parent::setFrom(OVERRIDE_EMAIL_FROM, $name);
		} else {
			parent::setFrom($addresses, $name);
		}
		return $this;
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
}
