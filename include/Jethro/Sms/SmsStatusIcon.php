<?php

declare(strict_types=1);

namespace Jethro\Sms;

use Sms\SmsStatus;

/**
 * Canonical icon for each SMS delivery status.
 *
 * Single source of truth — used by renderSmsDeliveryStatusIndicator() for the
 * per-person Messages tab and by the admin Messages page for badge icons.
 *
 * @see \Sms\SmsStatus
 */
enum SmsStatusIcon: string
{
    case DELIVERED = 'delivered';
    case SENT = 'sent';
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case TEST_MESSAGE = 'test-message';
    case IN_PROGRESS = 'in-progress';
    case SCHEDULED = 'scheduled';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case UNKNOWN = 'unknown';
    case OPTED_OUT = 'opted-out';

    public function icon(): string
    {
        return match($this) {
            self::DELIVERED => '✓✓',
            self::SENT => '✓',
            self::QUEUED, self::SENDING, self::TEST_MESSAGE, self::IN_PROGRESS => '⏳',
            self::SCHEDULED => '🕐',
            self::FAILED => '✗',
            self::CANCELLED, self::OPTED_OUT => '⊘',
            self::UNKNOWN => '',
        };
    }

    public static function fromStatus(\Sms\SmsStatus $status): self
    {
        return match ($status) {
            \Sms\SmsStatus::DELIVERED => self::DELIVERED,
            \Sms\SmsStatus::SENT => self::SENT,
            \Sms\SmsStatus::QUEUED => self::QUEUED,
            \Sms\SmsStatus::SENDING => self::SENDING,
            \Sms\SmsStatus::TEST_MESSAGE => self::TEST_MESSAGE,
            \Sms\SmsStatus::DELIVERY_IN_PROGRESS => self::IN_PROGRESS,
            \Sms\SmsStatus::SCHEDULED => self::SCHEDULED,
            \Sms\SmsStatus::FAILED => self::FAILED,
            \Sms\SmsStatus::CANCELLED => self::CANCELLED,
            \Sms\SmsStatus::UNKNOWN => self::UNKNOWN,
            \Sms\SmsStatus::OPTED_OUT => self::OPTED_OUT,
        };
    }
 }
