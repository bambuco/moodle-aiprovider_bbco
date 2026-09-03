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

namespace aiprovider_bbcotest;

/**
 * Provider fixture that counts rate-limit checks.
 *
 * @package    aiprovider_bbco
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class counting_provider extends \core_ai\provider {
    /** @var int Number of rate-limit checks. */
    public int $ratechecks = 0;

    /** @var array Normalised processor result. */
    public array $result = [
        'success' => true,
        'generatedcontent' => 'Generated answer',
        'model' => 'test-model',
    ];

    /**
     * Return supported actions.
     *
     * @return string[]
     */
    public static function get_action_list(): array {
        return [\core_ai\aiactions\generate_text::class];
    }

    /**
     * Return a component-like name usable by the broker fixture.
     *
     * @return string
     */
    public function get_name(): string {
        return 'aiprovider_bbcotest';
    }

    /**
     * Count and allow one rate-limit query.
     *
     * @param \core_ai\aiactions\base $action AI action
     * @return array|bool
     */
    public function is_request_allowed(\core_ai\aiactions\base $action): array|bool {
        $this->ratechecks++;
        return true;
    }

    /**
     * Report that the fixture is configured.
     *
     * @return bool
     */
    public function is_provider_configured(): bool {
        return true;
    }
}
