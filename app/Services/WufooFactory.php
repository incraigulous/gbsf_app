<?php
namespace App\Services;

use Adamlc\Wufoo\WufooApiWrapper;

class WufooFactory {
    static function build() {
        return new WufooApiWrapper(WUFOO_KEY, WUFOO_SUBDOMAIN);
    }
}