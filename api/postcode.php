<?php

require_once __DIR__ . '/PostcodeService.php';

// The entrypoint for the postcode search API. It delegates work to a reusable service class.
$service = new PostcodeService();
$service->handleRequest();
