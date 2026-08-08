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
 * Class process text generation for BbCo provider.
 *
 * This processor delegates the actual request to a configured real AI provider.
 *
 * @package    aiprovider_bbco
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_text extends \core_ai\process_base {
    /** @var string|null Request-specific system instruction, or null to preserve provider configuration. */
    private ?string $systeminstruction = null;

    /** @var array|null Identity of the provider used by the last attempt. */
    private ?array $effectiveprovider = null;

    /** @var array[] Closed provider attempts from the last process call. */
    private array $attempts = [];

    /**
     * Replace the effective provider's generate_text system instruction for this request only.
     *
     * @param string $systeminstruction Request-specific system instruction
     * @return void
     */
    public function set_system_instruction(string $systeminstruction): void {
        $this->systeminstruction = $systeminstruction;
    }

    /**
     * Return the effective provider instance used by the last attempt.
     *
     * @return array|null Component, instance ID and configured instance name.
     */
    public function get_effective_provider(): ?array {
        return $this->effectiveprovider;
    }

    /**
     * Return every provider attempt in execution order.
     *
     * @return array[]
     */
    public function get_attempts(): array {
        return $this->attempts;
    }

    /**
     * Query the AI service by delegating to the configured provider.
     *
     * @return array The response from the AI service.
     */
    protected function query_ai_api(): array {
        if (!($this->provider instanceof \aiprovider_bbco\provider)) {
            return [
                'success' => false,
                'errorcode' => 500,
                'error' => get_string('error_no_provider', 'aiprovider_bbco'),
                'errormessage' => get_string('error_invalidbroker', 'aiprovider_bbco'),
            ];
        }

        $providers = $this->get_real_providers();
        if (empty($providers)) {
            return [
                'success' => false,
                'errorcode' => 500,
                'error' => get_string('error_no_provider', 'aiprovider_bbco'),
                'errormessage' => get_string('error_no_provider_desc', 'aiprovider_bbco'),
            ];
        }

        $lastfailure = [
            'success' => false,
            'errorcode' => 500,
            'error' => get_string('error_processingrequest', 'aiprovider_bbco'),
            'errormessage' => get_string('error_processingrequest_desc', 'aiprovider_bbco'),
        ];

        foreach ($providers as $ordinal => $realprovider) {
            $this->effectiveprovider = [
                'component' => $realprovider->get_name(),
                'id' => (int) $realprovider->id,
                'name' => (string) $realprovider->name,
            ];
            $started = hrtime(true);
            try {
                $result = $this->process_with_provider($realprovider);
            } catch (\Throwable $e) {
                // Subclasses may override the protected seam without its normal exception guard.
                $result = [
                    'success' => false,
                    'errorcode' => 500,
                    'error' => 'provider_error',
                    'errormessage' => $e->getMessage(),
                    'recoverable' => false,
                ];
            } finally {
                $durationms = max(1, (int) ceil((hrtime(true) - $started) / 1_000_000));
            }
            $this->attempts[] = $this->build_attempt($ordinal + 1, $this->effectiveprovider, $result, $durationms);

            if (!empty($result['success'])) {
                return $result;
            }

            $lastfailure = $result;
            if (!$this->is_recoverable_failure($result)) {
                return $result;
            }
            debugging(
                'aiprovider_bbco: provider fallback from ' . $realprovider->get_name()
                . ' because: ' . ($result['errormessage'] ?? 'unknown error'),
                DEBUG_DEVELOPER
            );
        }

        return $lastfailure;
    }

    /**
     * Build a stable, in-memory trace without persisting provider data.
     *
     * @param int $ordinal Attempt number
     * @param array $identity Provider identity
     * @param array $result Normalised result
     * @param int $durationms Monotonic elapsed milliseconds
     * @return array
     */
    private function build_attempt(int $ordinal, array $identity, array $result, int $durationms): array {
        return [
            'attemptordinal' => $ordinal,
            'providercomponent' => $identity['component'],
            'providerinstanceid' => $identity['id'],
            'providername' => $identity['name'],
            'success' => !empty($result['success']),
            'errorcode' => $result['errorcode'] ?? null,
            'errormessage' => $result['errormessage'] ?? null,
            'model' => $result['model'] ?? null,
            'prompttokens' => $result['prompttokens'] ?? null,
            'completiontokens' => $result['completiontokens'] ?? null,
            'finishreason' => $result['finishreason'] ?? null,
            'durationms' => $durationms,
        ];
    }

    /**
     * Return the providers eligible for attempts.
     *
     * This seam keeps routing tests independent from real HTTP providers.
     *
     * @return \core_ai\provider[]
     */
    protected function get_real_providers(): array {
        return $this->provider->get_real_providers();
    }

    /**
     * Process prompt with one specific real provider.
     *
     * @param \core_ai\provider $realprovider
     * @return array
     */
    protected function process_with_provider(\core_ai\provider $realprovider): array {
        try {
            $processclass = $this->get_generate_text_process_class($realprovider);
            if ($processclass === null) {
                return [
                    'success' => false,
                    'errorcode' => 500,
                    'error' => get_string('error_processornotfound', 'aiprovider_bbco'),
                    'errormessage' => get_string('error_processornotfound_desc', 'aiprovider_bbco', $realprovider->get_name()),
                    'recoverable' => false,
                ];
            }

            // Every attempt gets a fresh action and provider configuration.
            $action = clone $this->prepare_delegated_action();
            $requestprovider = $this->prepare_request_provider($realprovider, $action);
            $processor = new $processclass($requestprovider, $action);
            $response = $processor->process();

            return $this->response_to_array($response);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'errorcode' => 500,
                'error' => get_string('error_processingrequest', 'aiprovider_bbco'),
                'errormessage' => $e->getMessage(),
                'recoverable' => false,
            ];
        }
    }

    /**
     * Resolve provider processor class for generate_text.
     *
     * @param \core_ai\provider $realprovider
     * @return string|null
     */
    private function get_generate_text_process_class(\core_ai\provider $realprovider): ?string {
        $classpath = substr($realprovider::class, 0, strpos($realprovider::class, '\\') + 1);
        $processclass = $classpath . 'process_generate_text';

        if (!class_exists($processclass)) {
            return null;
        }

        return $processclass;
    }

    /**
     * Prepare delegated action for real providers.
     *
     * @return \core_ai\aiactions\base
     */
    private function prepare_delegated_action(): \core_ai\aiactions\base {
        if (method_exists($this->action, 'get_generate_text_action')) {
            $factory = [$this->action, 'get_generate_text_action'];
            return call_user_func($factory);
        }

        return $this->action;
    }

    /**
     * Prepare request-local provider configuration without mutating the configured instance.
     *
     * @param \core_ai\provider $realprovider Effective provider
     * @param \core_ai\aiactions\base $action Delegated generate_text action
     * @return \core_ai\provider
     */
    private function prepare_request_provider(
        \core_ai\provider $realprovider,
        \core_ai\aiactions\base $action
    ): \core_ai\provider {
        if ($this->systeminstruction === null) {
            return $realprovider;
        }

        $actionconfig = $realprovider->actionconfig;
        $actionconfig[$action::class]['settings']['systeminstruction'] = $this->systeminstruction;
        return $realprovider->with(actionconfig: $actionconfig);
    }

    /**
     * Decide whether trying another provider is safe and potentially useful.
     *
     * Only server-side failures (5xx) are retried. Client errors (all 4xx, explicitly
     * including 429) normally describe the request, authentication or quota and retrying
     * them against another provider could duplicate charge or circumvent a rate limit.
     * Unknown/non-HTTP codes are therefore also treated conservatively as final.
     *
     * @param array $failure Normalised processor failure.
     * @return bool
     */
    private function is_recoverable_failure(array $failure): bool {
        $errorcode = (int)($failure['errorcode'] ?? 0);
        if ($errorcode < 500 || $errorcode > 599) {
            return false;
        }

        return !array_key_exists('recoverable', $failure) || $failure['recoverable'] === true;
    }

    /**
     * Convert core response object to processor array expected by process_base.
     *
     * @param \core_ai\aiactions\responses\response_base $response
     * @return array
     */
    private function response_to_array(\core_ai\aiactions\responses\response_base $response): array {
        if ($response->get_success()) {
            $responsearr = $response->get_response_data();
            $responsearr['success'] = true;
            if (empty($responsearr['model'])) {
                $responsearr['model'] = $response->get_model_used();
            }
            return $responsearr;
        }

        return [
            'success' => false,
            'errorcode' => $response->get_errorcode(),
            'error' => $response->get_error(),
            'errormessage' => $response->get_errormessage(),
        ];
    }
}
