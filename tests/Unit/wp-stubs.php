<?php
/**
 * Stubs for WordPress pluggable functions not loaded by the unit-test
 * bootstrap. They give the SUT enough surface to construct WP_Error
 * responses without dragging in pluggable.php and its dependencies.
 */

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return false;
    }
}
