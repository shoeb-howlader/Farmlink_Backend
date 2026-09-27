<?php

test('it returns api v1 status successfully', function () {
    $response = $this->getJson('/api/v1/status');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Farmlink API v1 is operational',
            'data' => [
                'status' => 'healthy',
                'version' => 'v1',
            ],
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'status',
                'version',
                'timestamp',
            ],
        ]);
});
