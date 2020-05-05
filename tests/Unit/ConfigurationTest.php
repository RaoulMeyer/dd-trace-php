<?php

namespace DDTrace\Tests\Unit;

use DDTrace\Configuration;

final class ConfigurationTest extends BaseTestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->cleanUpEnvs();
    }

    protected function tearDown()
    {
        $this->cleanUpEnvs();
        parent::tearDown();
    }

    private function cleanUpEnvs()
    {
        putenv('DD_DISTRIBUTED_TRACING');
        putenv('DD_INTEGRATIONS_DISABLED');
        putenv('DD_PRIORITY_SAMPLING');
        putenv('DD_SAMPLING_RATE');
        putenv('DD_SERVICE_MAPPING');
        putenv('DD_TRACE_ANALYTICS_ENABLED');
        putenv('DD_TRACE_DEBUG');
        putenv('DD_TRACE_ENABLED');
        putenv('DD_TRACE_SAMPLE_RATE');
        putenv('DD_TRACE_SAMPLING_RULES');
    }

    public function testTracerEnabledByDefault()
    {
        $this->assertTrue(Configuration::get()->isEnabled());
        $this->assertTrue(\ddtrace_config_trace_enabled());
    }

    public function testTracerDisabled()
    {
        putenv('DD_TRACE_ENABLED=false');
        $this->assertFalse(Configuration::get()->isEnabled());
        $this->assertFalse(\ddtrace_config_trace_enabled());
    }

    public function testDebugModeDisabledByDefault()
    {
        $this->assertFalse(Configuration::get()->isDebugModeEnabled());
        $this->assertFalse(\ddtrace_config_debug_enabled());
    }

    public function testDebugModeCanBeEnabled()
    {
        putenv('DD_TRACE_DEBUG=true');
        $this->assertTrue(Configuration::get()->isDebugModeEnabled());
        $this->assertTrue(\ddtrace_config_debug_enabled());
    }

    public function testDistributedTracingEnabledByDefault()
    {
        $this->assertTrue(Configuration::get()->isDistributedTracingEnabled());
        $this->assertTrue(\ddtrace_config_distributed_tracing_enabled());
    }

    public function testDistributedTracingDisabled()
    {
        putenv('DD_DISTRIBUTED_TRACING=false');
        $this->assertFalse(Configuration::get()->isDistributedTracingEnabled());
        $this->assertFalse(\ddtrace_config_distributed_tracing_enabled());
    }

    public function testPrioritySamplingEnabledByDefault()
    {
        $this->assertTrue(Configuration::get()->isPrioritySamplingEnabled());
        $this->assertTrue(\ddtrace_config_priority_sampling_enabled());
    }

    public function testPrioritySamplingDisabled()
    {
        putenv('DD_PRIORITY_SAMPLING=false');
        $this->assertFalse(Configuration::get()->isPrioritySamplingEnabled());
        $this->assertFalse(\ddtrace_config_priority_sampling_enabled());
    }

    public function testAllIntegrationsEnabledByDefault()
    {
        $this->assertTrue(Configuration::get()->isIntegrationEnabled('any_one'));
        $this->assertTrue(\ddtrace_config_integration_enabled('any_one'));
    }

    public function testIntegrationsDisabled()
    {
        putenv('DD_INTEGRATIONS_DISABLED=one,two');
        $this->assertFalse(Configuration::get()->isIntegrationEnabled('one'));
        $this->assertFalse(Configuration::get()->isIntegrationEnabled('two'));
        $this->assertTrue(Configuration::get()->isIntegrationEnabled('three'));
        $this->assertFalse(\ddtrace_config_integration_enabled('one'));
        $this->assertFalse(\ddtrace_config_integration_enabled('two'));
        $this->assertTrue(\ddtrace_config_integration_enabled('three'));
    }

    public function testIntegrationsDisabledIfGlobalDisabled()
    {
        putenv('DD_INTEGRATIONS_DISABLED=one');
        putenv('DD_TRACE_ENABLED=false');
        $this->assertFalse(Configuration::get()->isIntegrationEnabled('one'));
        $this->assertFalse(Configuration::get()->isIntegrationEnabled('two'));
        $this->assertFalse(\ddtrace_config_integration_enabled('one'));
        $this->assertFalse(\ddtrace_config_integration_enabled('two'));
    }

    public function testAppNameFallbackPriorities()
    {
        // we do not support these fallbacks anymore; testing that we ignore them
        putenv('ddtrace_app_name');
        putenv('DD_TRACE_APP_NAME');
        $this->assertSame(
            'fallback_name',
            Configuration::get()->appName('fallback_name')
        );
        $this->assertSame(
            'fallback_name',
            \ddtrace_config_app_name('fallback_name')
        );

        putenv('ddtrace_app_name=foo_app');
        $this->assertSame('fallback_name', Configuration::get()->appName('fallback_name'));
        $this->assertSame('fallback_name', \ddtrace_config_app_name('fallback_name'));

        Configuration::clear();
        putenv('ddtrace_app_name=foo_app');
        putenv('DD_TRACE_APP_NAME=bar_app');
        $this->assertSame('fallback_name', Configuration::get()->appName('fallback_name'));
        $this->assertSame('fallback_name', \ddtrace_config_app_name('fallback_name'));
    }

    public function testServiceName()
    {
        putenv('DD_SERVICE_NAME');
        putenv('DD_TRACE_APP_NAME');
        putenv('ddtrace_app_name');
        Configuration::clear();

        $this->assertSame('__default__', Configuration::get()->appName('__default__'));
        $this->assertSame('__default__', \ddtrace_config_app_name('__default__'));

        putenv('DD_SERVICE_NAME=my_app');
        $this->assertSame('my_app', Configuration::get()->appName('my_app'));
        $this->assertSame('my_app', \ddtrace_config_app_name('my_app'));
    }

    public function testServiceNameHasPrecedenceOverDeprecatedMethods()
    {
        Configuration::clear();

        putenv('DD_SERVICE_NAME=my_app');
        putenv('DD_TRACE_APP_NAME=wrong_app');
        putenv('ddtrace_app_name=wrong_app');
        $this->assertSame('my_app', Configuration::get()->appName('my_app'));
        $this->assertSame('my_app', \ddtrace_config_app_name('my_app'));
    }

    public function testAnalyticsDisabledByDefault()
    {
        $this->assertFalse(Configuration::get()->isAnalyticsEnabled());
        $this->assertFalse(\ddtrace_config_analytics_enabled());
    }

    public function testAnalyticsCanBeGloballyEnabled()
    {
        putenv('DD_TRACE_ANALYTICS_ENABLED=true');
        $this->assertTrue(Configuration::get()->isAnalyticsEnabled());
        $this->assertTrue(\ddtrace_config_analytics_enabled());
    }

    /**
     * @dataProvider dataProviderTestTraceSamplingRules
     * @param mixed $rules
     * @param array $expected
     */
    public function testTraceSamplingRules($rules, $expected)
    {
        if (false !== $rules) {
            putenv('DD_TRACE_SAMPLING_RULES=' . $rules);
        }

        $this->assertSame($expected, Configuration::get()->getSamplingRules());
        $this->assertSame($expected, \ddtrace_config_sampling_rules());
    }

    public function dataProviderTestTraceSamplingRules()
    {
        return [
            'DD_TRACE_SAMPLING_RULES not defined' => [
                false,
                [],
            ],
            'DD_TRACE_SAMPLING_RULES empty string' => [
                '',
                [],
            ],
            'DD_TRACE_SAMPLING_RULES not a valid json' => [
                '[a!}',
                [],
            ],
            'DD_TRACE_SAMPLING_RULES empty array' => [
                '[]',
                [],
            ],
            'DD_TRACE_SAMPLING_RULES empty object' => [
                '[{}]',
                [],
            ],
            'DD_TRACE_SAMPLING_RULES only rate' => [
                '[{"sample_rate": 0.3}]',
                [
                    [
                        'service' => '.*',
                        'name' => '.*',
                        'sample_rate' => 0.3,
                    ],
                ],
            ],
            'DD_TRACE_SAMPLING_RULES service defined' => [
                '[{"service": "my_service", "sample_rate": 0.3}]',
                [
                    [
                        'service' => 'my_service',
                        'name' => '.*',
                        'sample_rate' => 0.3,
                    ],
                ],
            ],
            'DD_TRACE_SAMPLING_RULES named defined' => [
                '[{"name": "my_name", "sample_rate": 0.3}]',
                [
                    [
                        'service' => '.*',
                        'name' => 'my_name',
                        'sample_rate' => 0.3,
                    ],
                ],
            ],
            'DD_TRACE_SAMPLING_RULES multiple values keeps order' => [
                '[{"name": "my_name", "sample_rate": 0.3}, {"service": "my_service", "sample_rate": 0.7}]',
                [
                    [
                        'service' => '.*',
                        'name' => 'my_name',
                        'sample_rate' => 0.3,
                    ],
                    [
                        'service' => 'my_service',
                        'name' => '.*',
                        'sample_rate' => 0.7,
                    ],
                ],
            ],
            'DD_TRACE_SAMPLING_RULES values converted to proper type' => [
                '[{"name": 1, "sample_rate": "0.3"}]',
                [
                    [
                        'service' => '.*',
                        'name' => '1',
                        'sample_rate' => 0.3,
                    ],
                ],
            ],
            'DD_TRACE_SAMPLING_RULES regex can be provided' => [
                '[{"name": "^a.*b$", "sample_rate": 0.3}]',
                [
                    [
                        'service' => '.*',
                        'name' => '^a.*b$',
                        'sample_rate' => 0.3,
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataProviderTestTraceSampleRate
     * @param mixed $envs
     * @param array $expected
     */
    public function testTraceSampleRate($envs, $expected)
    {
        foreach ($envs as $env) {
            putenv($env);
        }

        $this->assertSame($expected, Configuration::get()->getSamplingRate());
        $this->assertSame($expected, \ddtrace_config_sampling_rate());
    }

    public function dataProviderTestTraceSampleRate()
    {
        return [
            'defaults to 1.0 when nothing is set' => [
                [],
                1.0,
            ],
            'DD_TRACE_SAMPLE_RATE can be set' => [
                [
                    'DD_TRACE_SAMPLE_RATE=0.7',
                ],
                0.7,
            ],
            'DD_TRACE_SAMPLE_RATE has a minimum of 0.0' => [
                [
                    'DD_TRACE_SAMPLE_RATE=-0.1',
                ],
                0.0,
            ],
            'DD_TRACE_SAMPLE_RATE has a maximum of 1.0' => [
                [
                    'DD_TRACE_SAMPLE_RATE=1.1',
                ],
                1.0,
            ],
            'deprecated DD_SAMPLING_RATE can still be used' => [
                [
                    'DD_SAMPLING_RATE=0.7',
                ],
                0.7,
            ],
            'DD_TRACE_SAMPLE_RATE wins over deprecated DD_SAMPLING_RATE' => [
                [
                    'DD_SAMPLING_RATE=0.3',
                    'DD_TRACE_SAMPLE_RATE=0.7',
                ],
                0.7,
            ],
        ];
    }

    /**
     * @dataProvider dataProviderTestServiceMapping
     * @param mixed $envs
     * @param array $expected
     */
    public function testTraceServiceMapping($env, $expected)
    {
        if (false !== $env) {
            putenv("DD_SERVICE_MAPPING=$env");
        }

        $this->assertSame($expected, Configuration::get()->getServiceMapping());
        $this->assertSame($expected, \ddtrace_config_service_mapping());
    }

    public function dataProviderTestServiceMapping()
    {
        return [
            'not set' => [
                false,
                [],
            ],
            'empty' => [
                false,
                [],
            ],
            'one service mapping' => [
                'service1:service2',
                [ 'service1' => 'service2' ],
            ],
            'multiple service mappings' => [
                'service1:service2,service3:service4',
                [ 'service1' => 'service2', 'service3' => 'service4' ],
            ],
            'tolerant to extra whitespace' => [
                'service1 :    service2 ,         service3 : service4                    ',
                [ 'service1' => 'service2', 'service3' => 'service4' ],
            ],
        ];
    }

    public function testUriAsResourceNameEnabledDefault()
    {
        $this->assertTrue(Configuration::get()->isURLAsResourceNameEnabled());
        $this->assertTrue(\ddtrace_config_url_resource_name_enabled());
    }

    public function testUriAsResourceNameCanBeDisabled()
    {
        putenv('DD_TRACE_URL_AS_RESOURCE_NAMES_ENABLED=false');
        $this->assertFalse(Configuration::get()->isURLAsResourceNameEnabled());
        $this->assertFalse(\ddtrace_config_url_resource_name_enabled());
    }
}
