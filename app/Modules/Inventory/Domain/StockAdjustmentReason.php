<?php

namespace App\Modules\Inventory\Domain;

enum StockAdjustmentReason: string
{
    case InitialStock = 'initial_stock';
    case Purchase = 'purchase';
    case CorrectionIn = 'correction_in';
    case CorrectionOut = 'correction_out';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case DamagedRecovered = 'damaged_recovered';
    case LostRecovered = 'lost_recovered';
    case DamagedDisposed = 'damaged_disposed';
    case LostWriteOff = 'lost_write_off';
    case OtherIn = 'other_in';
    case OtherOut = 'other_out';

    public function movementType(): string
    {
        return 'adjustment_'.$this->value;
    }

    public function movementQuantity(int $quantity): int
    {
        return match ($this) {
            self::InitialStock,
            self::Purchase,
            self::CorrectionIn,
            self::DamagedRecovered,
            self::LostRecovered,
            self::OtherIn => $quantity,
            default => -$quantity,
        };
    }
}
