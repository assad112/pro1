<?php
declare(strict_types=1);

const ROLE_CLIENT = 'client';
const ROLE_PROVIDER = 'provider';
const ROLE_ADMIN = 'admin';

function role_label(string $role): string
{
    return t('role.' . $role);
}
