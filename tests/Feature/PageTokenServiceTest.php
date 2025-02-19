<?php

namespace Tests\Feature;

use App\Services\TokenService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PageTokenServiceTest extends TestCase
{
    public function test_token_retrieval()
    {
        File::shouldReceive('exists')->andReturnTrue();
        File::shouldReceive('lines')->andReturn(collect([
            'CAAQAA',
            'CAEQAA',
            // ... other tokens
        ]));

        $service = new TokenService();
        
        $this->assertEquals(0, $service->getOffsetFromNextToken('CAAQAA'));
        $this->assertNull($service->getOffsetFromNextToken('INVALID'));
    }
}