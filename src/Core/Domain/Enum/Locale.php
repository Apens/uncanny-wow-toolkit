<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Enum;

enum Locale: string
{
    case EN_US = 'en_US';
    case EN_GB = 'en_GB';
    case FR_FR = 'fr_FR';
    case DE_DE = 'de_DE';
    case ES_ES = 'es_ES';
    case ES_MX = 'es_MX';
    case IT_IT = 'it_IT';
    case RU_RU = 'ru_RU';
    case PT_BR = 'pt_BR';
    case KO_KR = 'ko_KR';
    case ZH_TW = 'zh_TW';
    case ZH_CN = 'zh_CN';
}
