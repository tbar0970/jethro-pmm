<?php

class Emailer
{
	
	static function newMessage()
	{
		require_once 'include/swiftmailer/swift_required.php';
		return Swift_Message::newInstance();
	}
	
	/**
	 * Send an email
	 * @param SwiftMessage $message
	 * @return mixed - TRUE on success, or an array of failed addresses on error.
	 */
	static function send($message) {
		require_once 'include/swiftmailer/swift_required.php';
		if (defined('SMTP_SERVER')) {
			$port = defined('SMTP_PORT') ? SMTP_PORT : 25;
			$transport = Swift_SmtpTransport::newInstance(SMTP_SERVER, $port);
			if (defined('SMTP_USERNAME') && SMTP_USERNAME) $transport->setUsername(SMTP_USERNAME);
			if (defined('SMTP_PASSWORD') && SMTP_PASSWORD) $transport->setPassword(SMTP_PASSWORD);
			if (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION) $transport->setEncryption(SMTP_ENCRYPTION);
		} else {
			$transport = Swift_MailTransport::newInstance();
		}

		$mailer = Swift_Mailer::newInstance($transport);

		$failures = Array();
		$numSent = $mailer->send($message, $failures);

		if (empty($failures) && $numSent) return TRUE;

		return $failures;
	}
	
	static function validateAddress($email) {
		require_once 'include/swiftmailer/swift_required.php';
		return Swift_Validate::email($email);
	}
}
