<?php

namespace PremiereRelayArchive\Enums;

enum BackfillMode
{
    case Ids;
    case Payload;

    public function toString(): string
    {
        return match ($this) {
            self::Ids => 'ids',
            self::Payload => 'payload',
        };
    }
}
