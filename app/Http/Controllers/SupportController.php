<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(): View
    {
        return view('support.index');
    }
}
