<?php

namespace App\Modules\Analitica\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Analitica\Application\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): View
    {
        return view('analitica::dashboard', ['resumen' => $dashboard->resumen()]);
    }
}
