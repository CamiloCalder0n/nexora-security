<?php

namespace App\Modules\Analitica\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('analitica::dashboard');
    }
}
