<?php

if (!(defined('WP_CLI') && WP_CLI)) {
    return;
}

class Algolia_Command {

}

WP_CLI::add_command('algolia', 'Algolia_Command');
