<?php

/**
 * Plugin Name:     Algolia Custom Integration
 * Description:     Add Algolia Search feature
 * Text Domain:     algolia-custom-integration
 * Version:         1.0.0
 *
 * @package         Algolia_Custom_Integration
 */

require_once __DIR__ . '/vendor/autoload.php';

global $algolia;

$algolia = \Algolia\AlgoliaSearch\SearchClient::create("INSERTID", "INSERTAPIKEY");

