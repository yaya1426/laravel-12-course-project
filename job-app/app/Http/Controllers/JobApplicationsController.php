<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class JobApplicationsController extends Controller
{
    public function index()
    {
        return view('job-applications.index');
    }
}
