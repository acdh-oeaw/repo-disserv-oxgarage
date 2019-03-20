<?php

/**
 * The MIT License
 *
 * Copyright 2019 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 */

use EasyRdf\Graph;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use zozlak\util\Config;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');

$composer = require_once 'vendor/autoload.php';
$composer->addPsr4('acdhOeaw\\', __DIR__ . '/src/acdhOeaw');

$config = new Config('config.ini');
$formats = $config->get('conversions');

// extract target format and ARCHE id of the resource
$url = substr($_SERVER['REDIRECT_URL'], strlen($config->get('baseUrl')));
$url = explode('/', $url);
$format = array_shift($url);
if (!isset($formats[$format])) {
    header('HTTP/1.1 400 unsupported output format');
    exit('unsupported output format');
}
$url = implode('/', $url);
if (!preg_match('|^https?://|', $url)) {
    $url = $config->get('archeIdPrefix') . $url;
}

// resolve ARCHE id to the actual URI
$client = new Client(['allow_redirects' => false, 'verify' => false]);
do {
    $response = $client->head($url);
    $location = $response->getHeader('Location');
    if (is_array($location) && count($location) > 0) {
    	$url = $location[0];
    }
} while ($response->getStatusCode() >= 300 && $response->getStatusCode() < 400);

// prepare an oxgarage request data
$graph = new Graph();
$graph->parse(file_get_contents($url . '/fcr:metadata'));
$meta = $graph->resource($url);
$filename = (string) $meta->getLiteral('http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#filename');
$reqUrl = $config->get('oxgarageUrl') . $formats[$format] . '/conversion';
$data = [
    'multipart' => [
        ['name' => 'filetoconvert', 'filename' => $filename, 'contents' => fopen($url, 'r')]
    ]
];

// proxy an oxgarage a request
$output  = fopen('php://output', 'w');
$options = [
    'sink'       => $output,
    'on_headers' => function(Response $r) {
        header('HTTP/1.1 ' . $r->getStatusCode() . ' ' . $r->getReasonPhrase());
	foreach ($r->getHeaders() as $name => $values) {
            $skipResponseHeaders = ['connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'transfer-encoding', 'upgrade', 'host'];
            if (in_array(strtolower($name), $skipResponseHeaders)) {
                continue;
            }
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
    },
    'verify' => false,
];
$client = new Client($options);
try {
    $client->request('POST', $reqUrl, $data);
} catch (RequestException $e) { }

if (is_resource($output)) {
    fclose($output);
}

