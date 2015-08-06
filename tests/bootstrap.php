<?php
/**
 * Stakhanovist
 *
 * @link        https://github.com/stakhanovist/queue
 * @copyright   Copyright (c) 2015, Stakhanovist
 * @license     http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */
chdir(__DIR__);

if (!file_exists('../vendor/autoload.php')) {
    throw new \RuntimeException('vendor/autoload.php not found. Run a composer install.');
}
