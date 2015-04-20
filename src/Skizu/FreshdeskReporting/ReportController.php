<?php namespace FreshdeskReporting;

use Illuminate\Routing\Controller;

class ReportController extends Controller
{
    protected $source;
    protected $data;

    public function __construct(array $source, \Closure $transformer, $dataObject = null)
    {
        $this->source = $source;
        $this->data = ($dataObject) ? $dataObject : new \stdClass();

        $this->transform($transformer);
    }

    public function transform(\Closure $transformer)
    {
        array_walk($this->source, $transformer, $this->data);
    }

    public function getData() {
        return $this->data;
    }
}