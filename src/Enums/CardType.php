<?php

namespace Hawb\EscrApiConnector\Enums;

enum CardType: string
{
    case UNKNOWN = 'UNKNOWN';
    case PASSIVE = 'PASSIVE';
    case SMART_NO_CDZ = 'SMART_NO_CDZ';
    case SMART_CDZ = 'SMART_CDZ';
    case SMART_MAY_SP = 'SMART_MAY_SP';
    case SMART_PASSIVE = 'SMART_PASSIVE';
    case SMART_PASSIVE_EMULATION = 'SMART_PASSIVE_EMULATION';
}
