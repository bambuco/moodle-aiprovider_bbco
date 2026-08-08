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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/counting_provider.php');
require_once(__DIR__ . '/fixtures/process_generate_text.php');

/**
 * Tests for bbco generate_text processor.
 *
 * @package    aiprovider_bbco
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(process_generate_text::class)]
final class process_generate_text_test extends \advanced_testcase {
    /** @var \core_ai\manager */
    private $manager;

    /** @var provider */
    private provider $provider;

    /** @var \core_ai\aiactions\generate_text */
    private \core_ai\aiactions\generate_text $action;

    /**
     * Setup test case.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->manager = \core\di::get(\core_ai\manager::class);
        $this->provider = $this->manager->create_provider_instance(
            classname: '\\aiprovider_bbco\\provider',
            name: 'bbco-test',
            enabled: true,
            config: [],
        );

        $this->action = new \core_ai\aiactions\generate_text(
            contextid: 1,
            userid: 2,
            prompttext: 'User prompt body',
        );
    }

    /**
     * Test query returns no-provider error when there are no eligible providers.
     */
    public function test_process_without_real_provider(): void {
        $processor = new process_generate_text($this->provider, $this->action);
        $result = $processor->process();

        $this->assertFalse($result->get_success());
        $this->assertNotSame(0, $result->get_errorcode());
        $this->assertNotEmpty($result->get_error());
        $this->assertNotEmpty($result->get_errormessage());
    }

    /**
     * Test instruction composition for free prompt mode.
     */
    public function test_compose_prompt_with_instruction(): void {
        $processor = new process_generate_text($this->provider, $this->action);

        $method = new \ReflectionMethod($processor, 'compose_prompt_with_instruction');
        $result = $method->invoke($processor, 'Follow these rules', 'Answer my question');

        $this->assertStringContainsString('<SYSTEM_INSTRUCTION_START>', $result);
        $this->assertStringContainsString('Follow these rules', $result);
        $this->assertStringContainsString('<USER_PROMPT_START>', $result);
        $this->assertStringContainsString('Answer my question', $result);
    }

    /**
     * Test prompt mutation on delegated action.
     */
    public function test_set_action_prompt_text(): void {
        $processor = new process_generate_text($this->provider, $this->action);

        $method = new \ReflectionMethod($processor, 'set_action_prompt_text');
        $method->invoke($processor, $this->action, 'Mutated prompt');

        $this->assertSame('Mutated prompt', $this->action->get_configuration('prompttext'));
    }

    /**
     * Test conservative fallback classification, including explicit handling of rate limits.
     */
    public function test_only_server_failures_are_recoverable(): void {
        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'is_recoverable_failure');

        $this->assertTrue($method->invoke($processor, ['errorcode' => 500]));
        $this->assertTrue($method->invoke($processor, ['errorcode' => 503]));
        $this->assertFalse($method->invoke($processor, ['errorcode' => 400]));
        $this->assertFalse($method->invoke($processor, ['errorcode' => 429]));
        $this->assertFalse($method->invoke($processor, ['errorcode' => 400, 'recoverable' => true]));
        $this->assertFalse($method->invoke($processor, ['errorcode' => 429, 'recoverable' => true]));
        $this->assertFalse($method->invoke($processor, ['errorcode' => -1]));
        $this->assertFalse($method->invoke($processor, ['errorcode' => 500, 'recoverable' => false]));
    }

    /**
     * 429 and other 4xx failures are terminal, while 5xx permits exactly one next attempt.
     *
     * @param int $firstcode First provider code
     * @param int $expectedattempts Expected number of provider attempts
     * @param bool $expectsuccess Expected final result
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fallback_provider')]
    public function test_fallback_contract(int $firstcode, int $expectedattempts, bool $expectsuccess): void {
        $processor = new class ($this->provider, $this->action) extends process_generate_text {
            /** @var \core_ai\provider[] Providers presented to the broker. */
            public array $providers = [];

            /** @var array[] Queued normalised outcomes. */
            public array $outcomes = [];

            /** @var int Number of attempted providers. */
            public int $attempts = 0;

            /**
             * Return test providers.
             *
             * @return \core_ai\provider[]
             */
            protected function get_real_providers(): array {
                return $this->providers;
            }

            /**
             * Return the next test outcome.
             *
             * @param \core_ai\provider $realprovider Provider being attempted
             * @return array
             */
            protected function process_with_provider(\core_ai\provider $realprovider): array {
                $this->attempts++;
                return array_shift($this->outcomes);
            }
        };
        $processor->providers = [$this->provider, $this->provider];
        $processor->outcomes = [
            [
                'success' => false,
                'errorcode' => $firstcode,
                'error' => 'provider_error',
                'errormessage' => 'First provider failed',
            ],
            [
                'success' => true,
                'generatedcontent' => 'Fallback answer',
                'model' => 'test-model',
            ],
        ];

        $result = $processor->process();
        if ($expectedattempts > 1) {
            $this->assertDebuggingCalled();
        }

        $this->assertSame($expectedattempts, $processor->attempts);
        $this->assertSame($expectsuccess, $result->get_success());
        $attempts = $processor->get_attempts();
        $this->assertCount($expectedattempts, $attempts);
        $this->assertSame(range(1, $expectedattempts), array_column($attempts, 'attemptordinal'));
        $this->assertGreaterThan(0, $attempts[0]['durationms']);
    }

    /**
     * Every effective provider attempt performs exactly one rate-limit check.
     */
    public function test_rate_limiter_is_checked_once_per_attempt(): void {
        $firstprovider = new \aiprovider_bbcotest\counting_provider(true, 'first', '[]', id: 101);
        $firstprovider->result = [
            'success' => false,
            'errorcode' => 503,
            'error' => 'server_failure',
            'errormessage' => 'First provider unavailable',
        ];
        $secondprovider = new \aiprovider_bbcotest\counting_provider(true, 'second', '[]', id: 102);
        $broker = new class (true, 'broker', '[]', id: 103) extends provider {
            /** @var \core_ai\provider[] Providers returned to the processor. */
            public array $realproviders = [];

            /**
             * Return the configured fixtures.
             *
             * @return \core_ai\provider[]
             */
            public function get_real_providers(): array {
                return $this->realproviders;
            }
        };
        $broker->realproviders = [$firstprovider, $secondprovider];

        $processor = new process_generate_text($broker, $this->action);
        $result = $processor->process();
        $this->assertDebuggingCalled();

        $this->assertTrue($result->get_success());
        $this->assertSame(1, $firstprovider->ratechecks);
        $this->assertSame(1, $secondprovider->ratechecks);
    }

    /**
     * Fallback cases.
     *
     * @return array
     */
    public static function fallback_provider(): array {
        return [
            '429 does not fallback' => [429, 1, false],
            'other 4xx does not fallback' => [400, 1, false],
            '5xx falls back once' => [503, 2, true],
        ];
    }
}
