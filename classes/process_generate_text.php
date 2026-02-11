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

use core_ai\aiactions\responses\response_base;
use core_ai\provider;

/**
 * Class process text generation for BbCo provider.
 *
 * This processor delegates the actual request to a configured real AI provider.
 *
 * @package    aiprovider_bbco
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_text extends \core_ai\process_base {
    /**
     * The prompt to send to the AI service, extracted from the action parameters.
     *
     * @var string
     */
    public $prompt = '';

    /**
     * Query the AI service by delegating to the configured provider.
     *
     * @return array The response from the AI service.
     */
    protected function query_ai_api(): array {
        // Get the configured real provider
        $realprovider = \aiprovider_bbco\provider::get_real_provider();
        if ($realprovider === null) {
            return [
                'success' => false,
                'errorcode' => 500,
                'error' => 'No AI provider configured',
                'errormessage' => get_string('error_no_provider', 'aiprovider_bbco'),
            ];
        }

        // Get the processor for the real provider
        try {
            $classname = 'process_generate_text';
            $classpath = substr($realprovider::class, 0, strpos($realprovider::class, '\\') + 1);
            $processclass = $classpath . $classname;

            if (!class_exists($processclass)) {
                return [
                    'success' => false,
                    'errorcode' => 500,
                    'error' => 'Processor not found for action',
                    'errormessage' => get_string('error_processor_not_found', 'aiprovider_bbco'),
                ];
            }

            // Create the processor and process the action
            $action = $this->action->get_generate_text_action();

            $processor = new process_openai($realprovider, $action);
            $processor->prompt = $this->prompt;
            $response = $processor->query_ai_api();

            return $response;

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'errorcode' => 500,
                'error' => 'Error processing request in real provider',
                'errormessage' => $e->getMessage(),
            ];
        }
    }
}
