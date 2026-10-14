<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Services\Search\GlobalSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, GlobalSearch $search): View
    {
        $q = trim((string) $request->input('q'));

        return view('crm.search', ['q' => $q, 'results' => $search->search($q, $request->user())]);
    }
}
