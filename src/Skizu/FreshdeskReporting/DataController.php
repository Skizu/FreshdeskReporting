<?php namespace FreshdeskReporting;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ParseException;
use GuzzleHttp\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Routing\Controller;

class DataControllerException extends Exception
{
}

class DataController extends Controller
{
    private $client;
    private $key;
    private $path;

    public function __construct($name, \Closure $filter, $path = false)
    {
        $this->client = new Client(['base_url' => env('FRESHDESK_API_URL')]);
        $this->key = md5("dataset_$name");
        $this->batch_size = env('REQUEST_BATCH_SIZE', 3);
        $this->path = ($path) ? $path : '/helpdesk/tickets.json';
        $this->data = $this->_fetchDataset($filter);
    }

    private function _fetchDataset($filter)
    {
        return Cache::get($this->key, function () use ($filter) {
            Cache::add($this->key, $data = $this->_getDataPartialRecursive($filter), 60);

            return $data;
        });
    }

    private function _getDataPartialRecursive($filter, $page = 0, $data = [])
    {

        $result = $this->_getDataPartial($filter, $page);

        if ([] !== $result['data']) {
            $data = array_merge($data,
                $this->_getDataPartialRecursive($filter, $result['current_page'], $result['data']));
        }

        return $data;
    }

    private function _getDataPartial($filter, $page)
    {
        $requests = [];
        $return = [];
        $current_page = $page;

        for ($i = 1; $i <= $this->batch_size; $i++) {
            $current_page = $page + $i;
            $requests[] = $request = $this->client->createRequest('GET', $this->path,
                ['auth' => [env('FRESHDESK_API_KEY'), 'X']]);
            $query = $request->getQuery();
            $query['page'] = $current_page;
        }

        $results = Pool::batch($this->client, $requests);

        try {
            foreach ($results->getSuccessful() as $result) {
                $dataset = array_filter($result->json(), $filter);

                $return = array_merge($return, $dataset);
            }
        } catch (ParseException $e) {
            throw new DataControllerException($e->getMessage(), $e->getCode(), $e);
        }

        return ['current_page' => $current_page, 'data' => $return];
    }

}