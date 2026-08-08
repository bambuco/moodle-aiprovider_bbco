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

/**
 * English language pack for BbCo AI provider is not an real provider but a way to interact with existing suppliers.
 *
 * @package    aiprovider_bbco
 * @category   string
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['error_no_provider'] = 'No AI provider configured';
$string['error_no_provider_desc'] = 'No AI provider is configured. Please configure at least one real AI provider (OpenAI, Azure AI, Ollama, etc.).';
$string['error_invalidbroker'] = 'Invalid broker provider instance received by BbCo processor.';
$string['error_processingrequest'] = 'Error processing request in real provider';
$string['error_processingrequest_desc'] = 'The AI request could not be completed by any configured provider.';
$string['error_processornotfound'] = 'Processor not found for action';
$string['error_processornotfound_desc'] = 'The generate_text processor was not found in provider {$a}.';
$string['pluginname'] = 'BbCo AI proxy provider.';
$string['privacy:metadata'] = 'The BbCo AI provider is not a real provider but a way to interact with existing suppliers plugin doesn\'t store any personal data.';
