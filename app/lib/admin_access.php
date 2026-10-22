<?php

declare(strict_types=1);

/**
 * Core-owned authorization policy for restricted Admin participants.
 */
final class admin_access
{
    private const RESTRICTED_MODULES = [
        'accounts',
        'admin',
        'auth',
        'error_handler',
        'install',
        'modules',
        'updater',
    ];

    public static function isAssignable(string $module): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{0,62}$/', $module) === 1
            && !in_array($module, self::RESTRICTED_MODULES, true);
    }

    public static function canAccess(
        int $accountId,
        int $userLevel,
        string $module
    ): bool {
        if ($userLevel >= 9) {
            return true;
        }

        if ($userLevel < 7 || $accountId < 1 || !self::isAssignable($module)) {
            return false;
        }

        try {
            require_once APPROOT . '/models/accounts_model.php';
            return (new accounts_model())->has_admin_module_access(
                $accountId,
                $module
            );
        } catch (Throwable $error) {
            error_log('Admin module authorization failed: ' . $error->getMessage());
            return false;
        }
    }

    /**
     * @param array<int, string> $modules
     * @return array<int, string>
     */
    public static function filterForCurrentAccount(array $modules): array
    {
        $level = (int) ($_SESSION['user_level'] ?? 0);
        if ($level >= 9) {
            return $modules;
        }

        $accountId = (int) ($_SESSION['user_id'] ?? 0);
        if ($accountId < 1) {
            return [];
        }

        try {
            require_once APPROOT . '/models/accounts_model.php';
            $granted = (new accounts_model())->get_admin_modules_for_account(
                $accountId
            );
        } catch (Throwable $error) {
            error_log('Admin navigation authorization failed: ' . $error->getMessage());
            return [];
        }

        return array_values(array_filter(
            $modules,
            static fn (string $module): bool => self::isAssignable($module)
                && in_array($module, $granted, true)
        ));
    }
}
