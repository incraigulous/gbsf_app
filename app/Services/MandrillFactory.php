<?php
namespace App\Services;

use Mandrill;

class MandrillFactory {
    static function build() {
        return new Mandrill(MANDRILL_KEY);
    }
}