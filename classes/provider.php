<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace aiprovider_bbco;

/**
 * Main class for the BbCo AI provider is not a real provider but a way to interact with existing suppliers.
 *
 * @package    aiprovider_bbco
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /**
     * Get the actions that this provider supports.
     *
     * Returns only generate_text action.
     *
     * @return array An array of action class names.
     */
    public static function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
        ];
    }

    /**
     * Check this provider has the minimal configuration to work.
     *
     * At least one real AI provider must be configured and support generate_text.
     *
     * @return bool Return true if configured.
     */
    public function is_provider_configured(): bool {
        try {
            $manager = \core\di::get(\core_ai\manager::class);

            // Check that at least one real provider is configured and supports generate_text
            foreach ($manager->get_sorted_providers() as $provider) {
                if ($provider->get_name() !== 'aiprovider_bbco'
                    && $provider->is_provider_configured()
                    && in_array(\core_ai\aiactions\generate_text::class, $provider->get_action_list())) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get the first available real provider that is configured and supports generate_text.
     *
     * @return \core_ai\provider|null The configured provider or null if none available.
     */
    protected static function get_available_provider(): ?\core_ai\provider {
        try {
            $manager = \core\di::get(\core_ai\manager::class);

            foreach ($manager->get_sorted_providers() as $provider) {
                if ($provider->get_name() !== 'aiprovider_bbco'
                    && $provider->is_provider_configured()
                    && in_array(\core_ai\aiactions\generate_text::class, $provider->get_action_list())) {
                    return $provider;
                }
            }
        } catch (\Throwable $e) {
            // Log or handle exception if needed
        }

        return null;
    }

    /**
     * Is request allowed
     *
     * Delegates to the configured provider.
     *
     * @param \core_ai\aiactions\base $action
     * @return array|bool
     */
    public function is_request_allowed(\core_ai\aiactions\base $action): array|bool {
        $realProvider = self::get_available_provider();
        if ($realProvider !== null) {
            return $realProvider->is_request_allowed($action);
        }
        return false;
    }

    /**
     * Get the first available real provider that is configured.
     *
     * @return \core_ai\provider|null The configured provider or null if none available.
     */
    public static function get_real_provider(): ?\core_ai\provider {
        return self::get_available_provider();
    }
}
