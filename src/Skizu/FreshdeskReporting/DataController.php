<?php namespace FreshdeskReporting;

use Cache;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ParseException;
use GuzzleHttp\Pool;

class DataControllerException extends Exception
{
}

class DataController extends Controller
{
    private $client;
    private $key;

    public function __construct($path)
    {
        $this->client = new Client(['base_url' => env('FRESHDESK_API_URL')]);
        $this->key = md5("dataset_$path");
        $this->batch_size = 3;
        $this->data = $this->_fetchDataset($path);
    }

    private function _fetchDataset($path)
    {
        return Cache::get($this->key, function () use ($path) {
            Cache::add($this->key, $data = $this->_getDataPartialRecursive($path), 60);

            return $data;
        });
    }

    private function _getDataPartialRecursive($path, $page = 0, $data = [])
    {

        $result = $this->_getDataPartial($path, $page);

        if ([] !== $result['data']) {
            $data = array_merge($data,
                $this->_getDataPartialRecursive($path, $result['current_page'], $result['data']));
        }

        return $data;
    }

    private function _getDataPartial($path, $page)
    {
        $requests = [];
        $return = [];
        $current_page = $page;
        $size = (isset($batch_size)) ? $batch_size : env('REQUEST_BATCH_SIZE');

        for ($i = 1; $i <= $size; $i++) {
            $current_page = $page + $i;
            $requests[] = $request = $this->client->createRequest('GET', $path,
                ['auth' => [env('FRESHDESK_API_KEY'), 'X']]);
            $query = $request->getQuery();
            $query['page'] = $current_page;
        }

        $results = Pool::batch($this->client, $requests);

        try {
            foreach ($results->getSuccessful() as $result) {
                $return = array_merge($return, $message = $result->json());
            }
        } catch (ParseException $e) {
            throw new DataControllerException($e->getMessage(), $e->getCode(), $e);
        }

        return ['current_page' => $current_page, 'data' => $return];
    }

}
