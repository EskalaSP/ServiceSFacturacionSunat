<?php

namespace App\Services\Pdf;

enum PdfFormatConfig: string
{
    case A4 = 'a4';
    case A5 = 'a5';
    case TICKET_80 = 'ticket-80';
    case TICKET_58 = 'ticket-58';

    public function paperSize(): array|string
    {
        return match ($this) {
            self::A4 => 'a4',
            self::A5 => 'a5',
            self::TICKET_80 => [0, 0, 226.77, 841.89],  // ancho 80mm
            self::TICKET_58 => [0, 0, 164.41, 841.89],  // ancho 58mm
        };
    }

    public function orientation(): string
    {
        return 'portrait';
    }

    public function margins(): array
    {
        return match ($this) {
            self::A4 => ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15],
            self::A5 => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            self::TICKET_80 => ['top' => 5, 'right' => 5, 'bottom' => 5, 'left' => 5],
            self::TICKET_58 => ['top' => 3, 'right' => 3, 'bottom' => 3, 'left' => 3],
        };
    }

    public function viewName(): string
    {
        return 'pdf.formats.' . str_replace('-', '_', $this->value);
    }

    public function isTicket(): bool
    {
        return in_array($this, [self::TICKET_80, self::TICKET_58]);
    }

    public static function fromString(string $format): self
    {
        return self::from($format);
    }
}
