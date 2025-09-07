<?php

if (! function_exists('auth_user')) {
    function auth_user(): array {
        return [
            'id'   => (int) ($_SERVER['AUTH_USER_ID'] ?? 0),
            'role' => (string) ($_SERVER['AUTH_ROLE'] ?? ''),
        ];
    }
}

if (! function_exists('auth_is')) {
    function auth_is(string ...$roles): bool {
        $role = (string) ($_SERVER['AUTH_ROLE'] ?? '');
        return in_array($role, $roles, true);
    }
}
