<?php
use Pider\Spider;
use Pider\Http\Response;

class ExampleSpider extends Spider {
    protected $domains = ['www.example.com'];
    protected $name = "ExampleSpider";

    public function start_requests():array {
        echo "hello world";
        return ['http://www.example.com'];
    }

    public function parse(Response $response) {
        echo $response->getText();
        echo "hello";
        echo "hello2";
        echo "request";
        print('print');
        print_r("example spider");
        var_dump([1=>'hello',2=>'test']);
    }

}
