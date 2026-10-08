<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * Why a delivery attempt was delayed or failed, or why a scheduled message was canceled (see Delivery status in the
 * docs). A message canceled when it was due carries the error code that would have refused it then, such as
 * `plan_daily_limit_reached`, which a model keeps as a string. Postilio may add codes: treat an unknown one on a
 * `deferred` event like UnknownTemporary, on a `bounced` one like UnknownPermanent.
 */
enum EmailEventReason: string
{
    /** the server refused for now (a 4xx reply), greylisting included; Postilio retries. */
    case RecipientServerTemporary = 'recipient_server_temporary';

    /** the server asks Postilio to slow down; Postilio retries later. */
    case RateLimitedByRecipient = 'rate_limited_by_recipient';

    /** the recipient's mailbox is full (temporary or permanent, see smtpCode). */
    case MailboxFull = 'mailbox_full';

    /** the server refused the message for good (a 5xx reply); see response and classification for why. */
    case RecipientRejected = 'recipient_rejected';

    /** We couldn't set up a secure connection to the recipient's mail server. We'll retry. */
    case TlsCertificateInvalid = 'tls_certificate_invalid';

    /** The secure connection to the recipient's mail server failed. We'll retry. */
    case TlsFailed = 'tls_failed';

    /** The recipient's mail server didn't respond in time. We'll retry. */
    case ConnectionTimeout = 'connection_timeout';

    /** We couldn't connect to the recipient's mail server. We'll retry. */
    case ConnectionFailed = 'connection_failed';

    /** A connection to one of the recipient's mail servers failed. We'll retry. */
    case Ipv6CandidateFailed = 'ipv6_candidate_failed';

    /** We couldn't find a reachable mail server for the recipient's domain. */
    case DnsOrMxFailed = 'dns_or_mx_failed';

    /** We're pacing delivery to this mailbox provider to protect deliverability. We'll retry. */
    case DeliveryPaced = 'delivery_paced';

    /** Delivery is paused on our side for the moment. We'll retry. */
    case DeliveryPaused = 'delivery_paused';

    /** We stopped retrying: the message could not be delivered within its retry period. */
    case RetryPeriodEnded = 'retry_period_ended';

    /** Delivery was delayed by a temporary problem. We'll retry. */
    case UnknownTemporary = 'unknown_temporary';

    /** We couldn't deliver this message. */
    case UnknownPermanent = 'unknown_permanent';

    /** you canceled it (DELETE /v1/emails/{id}, or in the portal). */
    case CanceledByRequest = 'canceled_by_request';

    /** Postilio restored its database from a backup, which never holds message content (see Data retention); send it again. */
    case LostInRestore = 'lost_in_restore';
}
