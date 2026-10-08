<?php

declare(strict_types=1);

namespace Postilio\Exception;

/** 429: the organization's plan allows no more this month, day or minute (plan_monthly_limit_reached, plan_daily_limit_reached, plan_rate_limit_reached). */
final class PlanLimitException extends RateLimitException {}
