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
 * Strings for component 'aiprovider_bbco', language 'es'
 *
 * @package    aiprovider_bbco
 * @category   string
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['error_no_provider'] = 'No hay un proveedor de IA configurado';
$string['error_no_provider_desc'] = 'No hay un proveedor de IA configurado. Por favor, configure al menos un proveedor de IA real (OpenAI, Azure AI, Ollama, etc.).';
$string['error_processingrequest'] = 'Error al procesar la solicitud en el proveedor real';
$string['error_processornotfound'] = 'Procesador no encontrado para la acción';
$string['error_processornotfound_desc'] = 'El procesador para la acción solicitada no se encontró en el proveedor de IA configurado.';
$string['pluginname'] = 'Proveedor proxy de IA de BbCo';
$string['privacy:metadata'] = 'El proveedor de inteligencia artificial de BbCo no es un proveedor real, sino una forma de interactuar con proveedores existentes. El complemento no almacena ningún dato personal.';
