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
 * Tests for bbco provider routing and selection logic.
 *
 * @package    aiprovider_bbco
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \advanced_testcase {
    /** @var \core_ai\manager */
    private $manager;

    /**
     * Setup test case.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->manager = \core\di::get(\core_ai\manager::class);
    }

    /**
     * Test bbco is not configured when there are no eligible real providers.
     */
    public function test_is_provider_configured_without_real_providers(): void {
        $bbco = $this->create_bbco_provider();

        $this->assertFalse($bbco->is_provider_configured());
        $this->assertSame([], $bbco->get_real_providers());
        $this->assertNull($bbco->get_real_provider());
    }

    /**
     * Test preferred provider is selected first.
     */
    public function test_preferred_provider_selected_first(): void {
        $this->create_real_provider('\\aiprovider_openai\\provider', ['apikey' => 'openai-key']);
        $this->create_real_provider('\\aiprovider_deepseek\\provider', ['apikey' => 'deepseek-key']);

        $bbco = $this->create_bbco_provider([
            'preferredprovider' => 'aiprovider_deepseek',
        ]);

        $providers = $bbco->get_real_providers();

        $this->assertNotEmpty($providers);
        $this->assertEquals('aiprovider_deepseek', $providers[0]->get_name());
        $this->assertEquals('aiprovider_deepseek', $bbco->get_real_provider()->get_name());
    }

    /**
     * Test priority list is applied and remaining providers are appended.
     */
    public function test_providerpriority_orders_then_appends_remaining(): void {
        $this->create_real_provider('\\aiprovider_openai\\provider', ['apikey' => 'openai-key']);
        $this->create_real_provider('\\aiprovider_deepseek\\provider', ['apikey' => 'deepseek-key']);
        $this->create_real_provider('\\aiprovider_ollama\\provider', ['endpoint' => 'http://localhost:11434']);

        $bbco = $this->create_bbco_provider([
            'providerpriority' => 'aiprovider_ollama, aiprovider_openai',
        ]);

        $providers = $bbco->get_real_providers();
        $names = array_map(fn($provider) => $provider->get_name(), $providers);

        $this->assertSame('aiprovider_ollama', $names[0]);
        $this->assertSame('aiprovider_openai', $names[1]);
        $this->assertContains('aiprovider_deepseek', $names);
    }

    /**
     * Test component and classname forms are normalized.
     */
    public function test_provider_name_normalization_from_classname(): void {
        $this->create_real_provider('\\aiprovider_openai\\provider', ['apikey' => 'openai-key']);
        $this->create_real_provider('\\aiprovider_deepseek\\provider', ['apikey' => 'deepseek-key']);

        $bbco = $this->create_bbco_provider([
            'preferredprovider' => 'aiprovider_openai\\provider',
            'providerpriority' => 'aiprovider_deepseek\\provider',
        ]);

        $providers = $bbco->get_real_providers();

        $this->assertSame('aiprovider_openai', $providers[0]->get_name());
        $this->assertSame('aiprovider_deepseek', $providers[1]->get_name());
    }

    /**
     * Test disabled instances and disabled generate_text actions are not selected.
     */
    public function test_only_enabled_instances_and_actions_are_selected(): void {
        $disabled = $this->manager->create_provider_instance(
            classname: '\\aiprovider_openai\\provider',
            name: 'disabled-instance',
            enabled: false,
            config: ['apikey' => 'disabled-key'],
        );
        $actiondisabled = $this->create_real_provider('\\aiprovider_deepseek\\provider', ['apikey' => 'action-off']);
        $this->manager->set_action_state(
            $actiondisabled->provider,
            \core_ai\aiactions\generate_text::get_basename(),
            0,
            $actiondisabled->id,
        );
        $enabled = $this->create_real_provider('\\aiprovider_openai\\provider', ['apikey' => 'enabled-key']);

        $providers = $this->create_bbco_provider()->get_real_providers();

        $this->assertSame([$enabled->id], array_column($providers, 'id'));
        $this->assertNotContains($disabled->id, array_column($providers, 'id'));
    }

    /**
     * All eligible instances remain available when they use the same provider component.
     */
    public function test_multiple_instances_of_same_component_are_preserved(): void {
        $first = $this->create_real_provider('\\aiprovider_openai\\provider', ['apikey' => 'first-key']);
        $second = $this->create_real_provider('\\aiprovider_openai\\provider', ['apikey' => 'second-key']);

        $ids = array_column($this->create_bbco_provider()->get_real_providers(), 'id');

        $this->assertContains($first->id, $ids);
        $this->assertContains($second->id, $ids);
    }

    /**
     * Test the broker check does not consume the effective provider's rate limit.
     */
    public function test_rate_limit_is_not_consumed_by_broker(): void {
        $realprovider = $this->create_real_provider('\\aiprovider_openai\\provider', [
            'apikey' => 'openai-key',
            'enableuserratelimit' => true,
            'userratelimit' => 1,
        ]);
        $bbco = $this->create_bbco_provider();
        $action = new \core_ai\aiactions\generate_text(
            contextid: 1,
            userid: 42,
            prompttext: 'Rate-limit test',
        );

        $this->assertTrue($bbco->is_request_allowed($action));
        $this->assertTrue($realprovider->is_request_allowed($action));
        $this->assertIsArray($realprovider->is_request_allowed($action));
    }

    /**
     * Guest sessions share the same Moodle user quota because they use one guest user ID.
     */
    public function test_guest_sessions_share_user_rate_limit(): void {
        $this->setGuestUser();
        $realprovider = $this->create_real_provider('\\aiprovider_openai\\provider', [
            'apikey' => 'openai-key',
            'enableuserratelimit' => true,
            'userratelimit' => 1,
        ]);
        $firstsessionaction = new \core_ai\aiactions\generate_text(
            contextid: 1,
            userid: guest_user()->id,
            prompttext: 'First guest session',
        );
        $secondsessionaction = new \core_ai\aiactions\generate_text(
            contextid: 1,
            userid: guest_user()->id,
            prompttext: 'Second guest session',
        );

        $this->assertTrue($realprovider->is_request_allowed($firstsessionaction));
        $denied = $realprovider->is_request_allowed($secondsessionaction);
        $this->assertIsArray($denied);
        $this->assertSame(429, $denied['errorcode']);
    }

    /**
     * Create bbco provider test instance.
     *
     * @param array $config
     * @return provider
     */
    private function create_bbco_provider(array $config = []): provider {
        return $this->manager->create_provider_instance(
            classname: '\\aiprovider_bbco\\provider',
            name: 'bbco-test',
            enabled: true,
            config: $config,
        );
    }

    /**
     * Create a real provider instance for tests.
     *
     * @param string $classname
     * @param array $config
     * @return \core_ai\provider
     */
    private function create_real_provider(string $classname, array $config): \core_ai\provider {
        return $this->manager->create_provider_instance(
            classname: $classname,
            name: 'real-' . md5($classname . json_encode($config)),
            enabled: true,
            config: $config,
        );
    }
}
