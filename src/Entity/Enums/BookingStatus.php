<?php

namespace Drupal\booking\Entity\Enums;


enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => t('En attente'),
            self::CONFIRMED => t('Confirmé'),
            self::CANCELLED => t('Annulé'),
            self::COMPLETED => t('Terminé'),
        };
    }

    public static function labels(): array
    {
        return [
            self::PENDING->value => t('En attente'),
            self::CONFIRMED->value => t('Confirmé'),
            self::CANCELLED->value => t('Annulé'),
            self::COMPLETED->value => t('Terminé'),
        ];
    }
}