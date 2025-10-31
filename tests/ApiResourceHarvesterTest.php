<?php

namespace Matawan\ApiResourceHarvester\Tests;

use Matawan\ApiResourceHarvester\ApiResourceHarvester;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

class ApiResourceHarvesterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testHarvestWithMultiplePages()
    {
        $serializer = Mockery::mock(SerializerInterface::class);

        $response1 = new MockResponse(json_encode([
            'hydra:member' => [['id' => 1], ['id' => 2]],
            'hydra:next' => '/page/2',
        ]), ['http_code' => 200]);

        $response2 = new MockResponse(json_encode([
            'hydra:member' => [['id' => 3], ['id' => 4]],
        ]), ['http_code' => 200]);

        $httpClient = new MockHttpClient([$response1, $response2]);

        $serializer->shouldReceive('deserialize')->andReturnUsing(function ($data) {
            $items = json_decode($data, true);
            $dtos = [];
            foreach ($items as $item) {
                $dto = new \stdClass();
                $dto->id = $item['id'];
                $dtos[] = $dto;
            }
            return $dtos;
        });

        $harvester = new ApiResourceHarvester($httpClient, $serializer);
        $results = iterator_to_array($harvester->harvest('/page/1', 'stdClass'));

        $this->assertCount(4, $results);
        $this->assertEquals(1, $results[0]->id);
        $this->assertEquals(2, $results[1]->id);
        $this->assertEquals(3, $results[2]->id);
        $this->assertEquals(4, $results[3]->id);
    }

    public function testHarvestWithSinglePage()
    {
        $serializer = Mockery::mock(SerializerInterface::class);

        $response = new MockResponse(json_encode([
            'hydra:member' => [['id' => 1], ['id' => 2]],
        ]), ['http_code' => 200]);

        $httpClient = new MockHttpClient($response);

        $serializer->shouldReceive('deserialize')->andReturnUsing(function ($data) {
            $items = json_decode($data, true);
            $dtos = [];
            foreach ($items as $item) {
                $dto = new \stdClass();
                $dto->id = $item['id'];
                $dtos[] = $dto;
            }
            return $dtos;
        });

        $harvester = new ApiResourceHarvester($httpClient, $serializer);
        $results = iterator_to_array($harvester->harvest('/page/1', 'stdClass'));

        $this->assertCount(2, $results);
    }

    public function testHarvestWithNoItems()
    {
        $serializer = Mockery::mock(SerializerInterface::class);

        $response = new MockResponse(json_encode([
            'hydra:member' => [],
        ]), ['http_code' => 200]);

        $httpClient = new MockHttpClient($response);
        $serializer->shouldReceive('deserialize')->andReturn([]);

        $harvester = new ApiResourceHarvester($httpClient, $serializer);
        $results = iterator_to_array($harvester->harvest('/page/1', 'stdClass'));

        $this->assertCount(0, $results);
    }

    public function testHarvestWithCustomKeys()
    {
        $serializer = Mockery::mock(SerializerInterface::class);

        $response1 = new MockResponse(json_encode([
            'items' => [['id' => 1]],
            'next_page' => '/next',
        ]), ['http_code' => 200]);
        $response2 = new MockResponse(json_encode(['items' => [['id' => 2]]]), ['http_code' => 200]);

        $httpClient = new MockHttpClient([$response1, $response2]);

        $serializer->shouldReceive('deserialize')->andReturnUsing(function ($data) {
            $items = json_decode($data, true);
            $dtos = [];
            foreach ($items as $item) {
                $dto = new \stdClass();
                $dto->id = $item['id'];
                $dtos[] = $dto;
            }
            return $dtos;
        });

        $harvester = new ApiResourceHarvester($httpClient, $serializer);
        $results = iterator_to_array($harvester->harvest('/start', 'stdClass', 'items', 'next_page'));

        $this->assertCount(2, $results);
    }

    public function testHarvestWithHttpClientException()
    {
        $response = new MockResponse('', ['http_code' => 404]);
        $httpClient = new MockHttpClient($response);
        $serializer = Mockery::mock(SerializerInterface::class);

        $harvester = new ApiResourceHarvester($httpClient, $serializer);
        $results = iterator_to_array($harvester->harvest('/error-page', 'stdClass'));

        $this->assertCount(0, $results);
    }

    public function testHarvestWithDeserializationException()
    {
        $serializer = Mockery::mock(SerializerInterface::class);

        $response = new MockResponse(json_encode([
            'hydra:member' => [['id' => 1]],
        ]), ['http_code' => 200]);

        $httpClient = new MockHttpClient($response);

        $serializer->shouldReceive('deserialize')->andThrow(new \Exception('Deserialization failed'));

        $harvester = new ApiResourceHarvester($httpClient, $serializer);
        $results = iterator_to_array($harvester->harvest('/page/1', 'stdClass'));

        $this->assertCount(0, $results);
    }
}
