<?php

namespace Metacomet\EnvSync\Tests\Unit\Providers;

use Exception;
use Metacomet\EnvSync\Providers\AwsSecretsManagerProvider;
use Metacomet\EnvSync\Tests\TestCase;

class AwsSecretsManagerProviderTest extends TestCase
{
    protected AwsSecretsManagerProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new AwsSecretsManagerProvider;
    }

    public function test_it_has_correct_name()
    {
        $this->assertEquals('AWS Secrets Manager', $this->provider->getName());
    }

    public function test_it_has_correct_install_instructions()
    {
        $instructions = $this->provider->getInstallInstructions();
        $this->assertStringContainsString('awscli', $instructions);
        $this->assertStringContainsString('https://docs.aws.amazon.com', $instructions);
    }

    public function test_it_has_correct_auth_instructions()
    {
        $instructions = $this->provider->getAuthInstructions();
        $this->assertStringContainsString('aws configure', $instructions);
        $this->assertStringContainsString('AWS_ACCESS_KEY_ID', $instructions);
        $this->assertStringContainsString('aws sso login', $instructions);
    }

    public function test_it_checks_availability()
    {
        // This test checks if the method runs without error
        // Actual availability depends on AWS SDK being installed
        $available = $this->provider->isAvailable();
        $this->assertIsBool($available);
    }

    public function test_push_requires_aws_sdk()
    {
        if (class_exists('\Aws\SecretsManager\SecretsManagerClient')) {
            $this->markTestSkipped('AWS SDK is installed, cannot test missing SDK error');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AWS SDK for PHP is not installed');

        $this->provider->push([
            'environment' => 'testing',
            'force' => true,
        ]);
    }

    public function test_pull_requires_aws_sdk()
    {
        if (class_exists('\Aws\SecretsManager\SecretsManagerClient')) {
            $this->markTestSkipped('AWS SDK is installed, cannot test missing SDK error');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AWS SDK for PHP is not installed');

        $this->provider->pull([
            'environment' => 'testing',
        ]);
    }

    public function test_list_requires_aws_sdk()
    {
        if (class_exists('\Aws\SecretsManager\SecretsManagerClient')) {
            $this->markTestSkipped('AWS SDK is installed, cannot test missing SDK error');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AWS SDK for PHP is not installed');

        $this->provider->list([]);
    }

    public function test_delete_requires_aws_sdk()
    {
        if (class_exists('\Aws\SecretsManager\SecretsManagerClient')) {
            $this->markTestSkipped('AWS SDK is installed, cannot test missing SDK error');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AWS SDK for PHP is not installed');

        $this->provider->delete([
            'environment' => 'testing',
        ]);
    }

    public function test_exists_requires_aws_sdk()
    {
        if (class_exists('\Aws\SecretsManager\SecretsManagerClient')) {
            $this->markTestSkipped('AWS SDK is installed, cannot test missing SDK error');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AWS SDK for PHP is not installed');

        $this->provider->exists([
            'environment' => 'testing',
        ]);
    }

    public function test_is_authenticated_requires_aws_sdk()
    {
        if (class_exists('\Aws\SecretsManager\SecretsManagerClient')) {
            $this->markTestSkipped('AWS SDK is installed, cannot test missing SDK error');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AWS SDK for PHP is not installed');

        $this->provider->isAuthenticated();
    }
}
