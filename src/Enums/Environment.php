<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Enums;

enum Environment: string
{
    case DEMO = 'demo';
    case PRODUCTION = 'production';

    /**
     * OAuth token host (POST /connect/token only).
     */
    public function accountsUrl(): string
    {
        return match ($this) {
            self::DEMO => 'https://demo-accounts.vivapayments.com',
            self::PRODUCTION => 'https://accounts.vivapayments.com',
        };
    }

    /**
     * API host for all Cloud Terminal /ecr/v1/ calls (Bearer auth, camelCase).
     */
    public function apiUrl(): string
    {
        return match ($this) {
            self::DEMO => 'https://demo-api.vivapayments.com',
            self::PRODUCTION => 'https://api.vivapayments.com',
        };
    }
}
