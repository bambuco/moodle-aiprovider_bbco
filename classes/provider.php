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
        return !empty($this->get_real_providers());
    }

    /**
     * Get all available real providers that are configured and support generate_text.
     *
     * Providers are ordered by:
     * 1) preferredprovider config key
     * 2) providerpriority config key (comma-separated)
     * 3) core_ai provider order
     *
     * @return \core_ai\provider[] Ordered list of configured real providers.
     */
    public function get_real_providers(): array {
        $eligibleproviders = [];
        try {
            $manager = \core\di::get(\core_ai\manager::class);

            foreach ($manager->get_sorted_providers() as $provider) {
                if (
                    $provider->get_name() !== 'aiprovider_bbco'
                    && $provider->enabled
                    && $provider->is_provider_configured()
                    && $manager->is_action_enabled(
                        $provider->provider,
                        \core_ai\aiactions\generate_text::class,
                        $provider->id ?? 0,
                    )
                    && in_array(\core_ai\aiactions\generate_text::class, $provider->get_action_list())
                ) {
                    $eligibleproviders[] = $provider;
                }
            }
        } catch (\Throwable $e) {
            debugging('aiprovider_bbco: failed while discovering providers: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }

        if (empty($eligibleproviders)) {
            return [];
        }

        $ordered = [];
        $selectedids = [];
        foreach ($this->get_selection_preferences() as $providername) {
            foreach ($eligibleproviders as $provider) {
                if ($provider->get_name() === $providername && !isset($selectedids[$provider->id])) {
                    $ordered[] = $provider;
                    $selectedids[$provider->id] = true;
                }
            }
        }

        // Append any provider not explicitly listed in selection preferences.
        foreach ($eligibleproviders as $provider) {
            if (!isset($selectedids[$provider->id])) {
                $ordered[] = $provider;
            }
        }

        return $ordered;
    }

    /**
     * Get the first available real provider that is configured.
     *
     * @return \core_ai\provider|null The configured provider or null if none available.
     */
    public function get_real_provider(): ?\core_ai\provider {
        $providers = $this->get_real_providers();
        $provider = reset($providers);
        return $provider === false ? null : $provider;
    }

    /**
     * Build provider selection preferences from instance config.
     *
     * Supported config keys:
     * - preferredprovider: aiprovider_openai or aiprovider_openai\provider
     * - providerpriority: comma-separated provider list
     *
     * @return string[]
     */
    private function get_selection_preferences(): array {
        $selection = [];

        if (!empty($this->config['preferredprovider'])) {
            $preferred = $this->normalise_provider_name((string)$this->config['preferredprovider']);
            if (!empty($preferred)) {
                $selection[] = $preferred;
            }
        }

        if (!empty($this->config['providerpriority'])) {
            $prioritylist = explode(',', (string)$this->config['providerpriority']);
            foreach ($prioritylist as $candidate) {
                $normalised = $this->normalise_provider_name($candidate);
                if (!empty($normalised)) {
                    $selection[] = $normalised;
                }
            }
        }

        return array_values(array_unique($selection));
    }

    /**
     * Normalise provider names to component form (aiprovider_xxx).
     *
     * @param string $providername
     * @return string|null
     */
    private function normalise_provider_name(string $providername): ?string {
        $providername = trim($providername);
        if ($providername === '') {
            return null;
        }

        if (str_ends_with($providername, '\\provider')) {
            return substr($providername, 0, -9);
        }

        return $providername;
    }
}
