<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
    }
    
    /**
     * Make a POST request with CSRF token
     */
    protected function postWithCsrf($uri, array $data = [], array $headers = [])
    {
        return $this->withSession(['_token' => 'test-token'])
            ->post($uri, array_merge($data, ['_token' => 'test-token']), $headers);
    }
    
    /**
     * Make a PUT request with CSRF token
     */
    protected function putWithCsrf($uri, array $data = [], array $headers = [])
    {
        return $this->withSession(['_token' => 'test-token'])
            ->put($uri, array_merge($data, ['_token' => 'test-token']), $headers);
    }
    
    /**
     * Make a DELETE request with CSRF token
     */
    protected function deleteWithCsrf($uri, array $data = [], array $headers = [])
    {
        return $this->withSession(['_token' => 'test-token'])
            ->delete($uri, array_merge($data, ['_token' => 'test-token']), $headers);
    }
    
    /**
     * Make a PATCH request with CSRF token
     */
    protected function patchWithCsrf($uri, array $data = [], array $headers = [])
    {
        return $this->withSession(['_token' => 'test-token'])
            ->patch($uri, array_merge($data, ['_token' => 'test-token']), $headers);
    }

    /**
     * Make a JSON POST request with CSRF token
     */
    protected function postJsonWithCsrf($uri, array $data = [], array $headers = [])
    {
        return $this->withSession(['_token' => 'test-token'])
            ->withHeaders(array_merge($headers, ['X-CSRF-TOKEN' => 'test-token']))
            ->postJson($uri, $data);
    }

    /**
     * Make a JSON PUT request with CSRF token
     */
    protected function putJsonWithCsrf($uri, array $data = [], array $headers = [])
    {
        return $this->withSession(['_token' => 'test-token'])
            ->withHeaders(array_merge($headers, ['X-CSRF-TOKEN' => 'test-token']))
            ->putJson($uri, $data);
    }

    /**
     * Make a JSON DELETE request with CSRF token
     */
    protected function deleteJsonWithCsrf($uri, array $data = [], array $headers = [])
    {
        return $this->withSession(['_token' => 'test-token'])
            ->withHeaders(array_merge($headers, ['X-CSRF-TOKEN' => 'test-token']))
            ->deleteJson($uri, $data);
    }

    /**
     * Make a JSON PATCH request with CSRF token
     */
    protected function patchJsonWithCsrf($uri, array $data = [], array $headers = [])
    {
        return $this->withSession(['_token' => 'test-token'])
            ->withHeaders(array_merge($headers, ['X-CSRF-TOKEN' => 'test-token']))
            ->patchJson($uri, $data);
    }
}
