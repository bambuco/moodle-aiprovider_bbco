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
 * Class process_openai
 *
 * @package    aiprovider_bbco
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_openai extends \aiprovider_openai\process_generate_text {
    /**
     * The prompt to send to the IA. It should be set before calling the process method.
     * It is public so it can be set from the outside, but it is not defined in the constructor
     * because the main class does not know about it and it is specific to this implementation.
     *
     * @var string
     */
    public $prompt = '';

    /**
     * Get the system instruction for the IA.
     * In this case, it returns the prompt that should be set before calling the process method.
     *
     * @return string The system instruction.
     */
    #[\Override]
    protected function get_system_instruction(): string {
        return $this->prompt;
    }
}
