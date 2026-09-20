<?php

namespace App\Services;

use App\Models\Span;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PersonalSpanCache
{
    public const CACHE_TTL_SECONDS = 604800;

    public static function key(string $userId): string
    {
        return 'user.'.$userId.'.personal_span';
    }

    public function rememberFor(User $user): ?Span
    {
        if ($user->relationLoaded('personalSpan')) {
            return $user->personalSpan;
        }

        if (! $user->personal_span_id) {
            $user->setRelation('personalSpan', null);

            return null;
        }

        $attributes = Cache::remember(self::key($user->id), self::CACHE_TTL_SECONDS, function () use ($user) {
            $span = Span::query()->find($user->personal_span_id);

            return $span?->getAttributes();
        });

        if (! $attributes) {
            $user->setRelation('personalSpan', null);

            return null;
        }

        $span = (new Span())->newFromBuilder($attributes);
        $user->setRelation('personalSpan', $span);

        return $span;
    }

    public function forgetForUser(?string $userId): void
    {
        if ($userId) {
            Cache::forget(self::key($userId));
        }
    }

    public function forgetForSpan(Span $span): void
    {
        if ($span->is_personal_span && $span->owner_id) {
            $this->forgetForUser($span->owner_id);
        }
    }
}
