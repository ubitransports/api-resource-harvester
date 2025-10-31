<?php

namespace Matawan\ApiResourceHarvester;

use Generator;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpClient\Exception\ClientException;

class ApiResourceHarvester
{
    private HttpClientInterface $client;
    private SerializerInterface $serializer;

    public function __construct(HttpClientInterface $client, SerializerInterface $serializer)
    {
        $this->client = $client;
        $this->serializer = $serializer;
    }

    /**
     * Retrieves all resources from a paginated API using concurrent requests (non-blocking when possible).
     *
     * @param string $initialUrl The URL of the first page.
     * @param string $outputFqcn The Fully Qualified Class Name (FQCN) of the output DTO (e.g., App\DTO\UserDTO::class).
     * @param string $itemsKey The JSON key containing the array of items (e.g., 'hydra:member', 'items').
     * @param string $nextUrlKey The JSON key holding the URL of the next page (e.g., 'hydra:next', 'next_page_url').
     * @return Generator|object[] Collection of DTO objects.
     */
    public function harvest(
        string $initialUrl,
        string $outputFqcn,
        string $itemsKey = 'hydra:member',
        string $nextUrlKey = 'hydra:next'
    ): Generator {
        $url = $initialUrl;

        /** @var ResponseInterface|null $currentResponse */
        $currentResponse = $this->client->request('GET', $url);
        $url = null;

        do {
            if ($currentResponse === null) {
                break;
            }

            try {
                $jsonContent = $currentResponse->getContent();
                $data = $currentResponse->toArray(false);

                $nextUrl = $data[$nextUrlKey] ?? null;
                $nextResponse = null;

                if ($nextUrl) {

                    $url = $nextUrl;
                    $nextResponse = $this->client->request('GET', $nextUrl);
                } else {
                    $url = null;
                }

                if (isset($data[$itemsKey])) {
                    $itemsJson = json_encode($data[$itemsKey]);

                    $objects = $this->serializer->deserialize(
                        $itemsJson,
                        $outputFqcn . '[]',
                        'json'
                    );

                    foreach ($objects as $itemDto) {
                        yield $itemDto;
                    }
                }

                $currentResponse = $nextResponse;

            } catch (ClientException $e) {
                error_log("ApiResourceHarvester HTTP Error: " . $e->getMessage());
                $currentResponse = null;
            } catch (\Exception $e) {
                error_log("ApiResourceHarvester Fatal Error: " . $e->getMessage());
                $currentResponse = null;
            }

        } while ($currentResponse !== null);
    }
}