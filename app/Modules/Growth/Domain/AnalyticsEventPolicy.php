<?php

declare(strict_types=1);

namespace App\Modules\Growth\Domain;

use App\Modules\Growth\Data\AnalyticsEvent;
use DomainException;

final class AnalyticsEventPolicy
{
    /** @var list<string> */
    private const TYPES = [
        'catalog.product_viewed',
        'cart.item_added',
        'checkout.started',
        'order.placed',
        'quotation.requested',
        'crm.lead_created',
        'contact.clicked',
    ];

    /** @var list<string> */
    private const PROHIBITED_KEYS = ['email', 'phone', 'name', 'address', 'ip', 'user_agent'];

    public function validate(AnalyticsEvent $event): void
    {
        if (! in_array($event->type, self::TYPES, true)
            || preg_match('/\A[a-z][a-z0-9._-]{0,49}\z/', $event->subjectType) !== 1
            || mb_strlen($event->identity, 'UTF-8') < 8 || mb_strlen($event->identity, 'UTF-8') > 200
            || ($event->consentEvidencePublicId !== null
                && preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $event->consentEvidencePublicId) !== 1)
            || count($event->attributes) > 20) {
            throw new DomainException('Analytics event contract is invalid.');
        }
        foreach ($event->attributes as $key => $value) {
            if (in_array(mb_strtolower($key, 'UTF-8'), self::PROHIBITED_KEYS, true)
                || preg_match('/\A[a-z][a-z0-9_]{0,49}\z/', $key) !== 1
                || (is_string($value) && mb_strlen($value, 'UTF-8') > 200)) {
                throw new DomainException('Analytics attributes contain prohibited or invalid data.');
            }
        }
    }
}
