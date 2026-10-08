<?php

declare(strict_types=1);

namespace Postilio\Enum;

/**
 * The stable error codes of the API, as listed in the docs' Errors and limits. Postilio may add codes: handle one you
 * do not know by the exception's class, which follows the status.
 */
enum ErrorCode: string
{
    /** 401: the key is missing, unknown or revoked. */
    case InvalidApiKey = 'invalid_api_key';

    /** 403: the key lacks the scope of this call. */
    case InsufficientScope = 'insufficient_scope';

    /** 403: the key may not be used from this address. */
    case ClientIpNotAllowed = 'client_ip_not_allowed';

    /** 403: Postilio suspended your organization: no project sends until it is resumed. */
    case OrganizationSuspended = 'organization_suspended';

    /** 403: the key's project is paused or suspended; your other projects keep sending. */
    case ProjectSuspended = 'project_suspended';

    /** 409: the `Idempotency-Key` was used for another request in the last 24 hours. */
    case IdempotencyKeyReusedWithDifferentRequest = 'idempotency_key_reused_with_different_request';

    /** 409: the project has this domain already. */
    case DomainExists = 'domain_exists';

    /** 409: the address is on the suppression list already. */
    case AddressAlreadySuppressed = 'address_already_suppressed';

    /** 413: the message is over 10 MB, see Message size. */
    case MessageTooLarge = 'message_too_large';

    /** 413: the message's size times its recipients is over 25 MB; send to fewer recipients at a time. */
    case MessageTooLargeForRecipients = 'message_too_large_for_recipients';

    /** 413: the request body is over what the endpoint reads (21 MB for sending, 1 MB elsewhere); it is not read further. */
    case PayloadTooLarge = 'payload_too_large';

    /** 409: your plan allows no more domains, see Plan limits. */
    case DomainLimitReached = 'domain_limit_reached';

    /** 409: the project has 10 webhook endpoints, or your plan allows no more. */
    case WebhookEndpointLimitReached = 'webhook_endpoint_limit_reached';

    /** 409: the delivery is still being retried. */
    case DeliveryStillPending = 'delivery_still_pending';

    /** 409: the message is not waiting for its `sendAt` (any more): it was not scheduled, was canceled, or is on its way. */
    case EmailNotScheduled = 'email_not_scheduled';

    /** 409: the project holds 10,000 scheduled messages, see Scheduled sending. */
    case ScheduledLimitReached = 'scheduled_limit_reached';

    /** 409: the project's scheduled messages would hold more than 256 MB together. */
    case ScheduledSizeLimitReached = 'scheduled_size_limit_reached';

    /** 409: resume the webhook endpoint first. */
    case EndpointPaused = 'endpoint_paused';

    /** 422: the sender's domain is not verified, or has been failing for over 72 hours. */
    case UnverifiedSenderDomain = 'unverified_sender_domain';

    /** 422: the key is restricted to other sending domains. */
    case SenderDomainNotAllowedForKey = 'sender_domain_not_allowed_for_key';

    /** 422: in the sandbox: a recipient outside your team and verified domains. */
    case SandboxRecipientNotAllowed = 'sandbox_recipient_not_allowed';

    /** 422: `sendAt` is less than a minute ahead, or in the past. */
    case SendAtTooSoon = 'send_at_too_soon';

    /** 422: `sendAt` is more than 30 days ahead. */
    case SendAtTooFar = 'send_at_too_far';

    /** 422: `cc` or `bcc` with more than one address in `to`, see Cc and Bcc. */
    case CcBccRequireSingleTo = 'cc_bcc_require_single_to';

    /** 422: removing a complaint from the suppression list needs a reason. */
    case ReasonRequired = 'reason_required';

    /** 422: a test email to an address that is neither confirmed for test emails nor a member's, see Test emails. */
    case TestRecipientNotConfirmed = 'test_recipient_not_confirmed';

    /** 429: in the sandbox: the day's recipients are used up. */
    case SandboxDailyLimitReached = 'sandbox_daily_limit_reached';

    /** 429: in the sandbox: this minute's recipients are used up. */
    case SandboxRateLimitReached = 'sandbox_rate_limit_reached';

    /** 429: the project's own limit: the day's recipients are used up. */
    case ProjectDailyLimitReached = 'project_daily_limit_reached';

    /** 429: the project's own limit: this minute's recipients are used up. */
    case ProjectRateLimitReached = 'project_rate_limit_reached';

    /** 429: your plan's month is used up, see Plan limits. */
    case PlanMonthlyLimitReached = 'plan_monthly_limit_reached';

    /** 429: your plan's day is used up. */
    case PlanDailyLimitReached = 'plan_daily_limit_reached';

    /** 429: your plan's minute is used up. */
    case PlanRateLimitReached = 'plan_rate_limit_reached';

    /** 429: the platform's safety limit per key or project: this minute's recipients are used up, see Platform limits. */
    case PlatformRateLimitReached = 'platform_rate_limit_reached';

    /** 429: the platform's safety limit per project: the day's recipients are used up. */
    case PlatformDailyLimitReached = 'platform_daily_limit_reached';

    /** 429: the project's test emails for the day (UTC) are used up. */
    case TestMailDailyLimitReached = 'test_mail_daily_limit_reached';

    /** 429: a domain can be checked once a minute. */
    case TooManyChecks = 'too_many_checks';

    /** 503: Postilio takes no new messages for now, to protect its storage; nothing was stored, so send it again after `Retry-After`. */
    case ServiceDegraded = 'service_degraded';

    /** 503: the domain check got no answer from DNS. */
    case DnsUnavailable = 'dns_unavailable';
}
