<?php

declare(strict_types=1);

namespace App\Auth;

interface Authenticatable
{
    public function getAuthId(): int;

    public function getAuthPasswordHash(): string;

    public function getRole(): string;
}
