<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
class PageController extends Controller {
    public function notfound() {
        return Inertia::render('NotFound');
    }
}