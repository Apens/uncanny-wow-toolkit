<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Enum;

enum Region: string
{
    case EU = 'eu';
    case US = 'us';
    case KR = 'kr';
    case TW = 'tw';

    public function defaultLocale(): Locale
    {
        return match ($this) {
            self::EU => Locale::FR_FR,
            self::US => Locale::EN_US,
            self::KR => Locale::KO_KR,
            self::TW => Locale::ZH_TW,
        };
    }
}
